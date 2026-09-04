<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\User;
use Tests\TestCase;

/**
 *  the Professor role's read-facing surfaces: the
 * `professor.courses.index` "Meus Cursos" listing (pivot-scoped — ONLY the
 * rows of `course_professor`, never the whole Organization's catalog) and
 * the `student.enrolled`-gated classroom/forum, which now also admit an
 * assigned Professor (`User::teaches()`) even though he has no
 * `course_user` row.
 */
class ProfessorCourseAccessTest extends TestCase
{
    /**
     * Attaches a Professor to a Course through the same call shape the
     * assignment panel uses (`CourseProfessorController::store()`).
     */
    private function assignProfessor(User $professor, Course $course, ?User $actor = null): void
    {
        $course->professors()->attach($professor->id, ['assigned_by' => $actor?->id]);
    }

    public function test_assigned_professor_lists_only_their_assigned_courses(): void
    {
        $org = Organization::factory()->create();
        $professor = User::factory()->professor()->create(['org_id' => $org->id]);

        // Fixtures are created BEFORE `actingAs()` so `OrgScope`'s
        // `creating` hook never overwrites the explicit `org_id`.
        $assignedCourse = Course::factory()->create([
            'org_id' => $org->id,
            'title' => 'Curso Atribuído ao Professor',
        ]);
        $foreignCourse = Course::factory()->create([
            'org_id' => $org->id,
            'title' => 'Curso de Outro Professor',
        ]);

        // Same Organization for both Courses: the pivot — not the tenant
        // scope — is what must keep the unassigned Course out of the list.
        $this->assignProfessor($professor, $assignedCourse);
        $otherProfessor = User::factory()->professor()->create(['org_id' => $org->id]);
        $this->assignProfessor($otherProfessor, $foreignCourse);

        $this->actingAs($professor);

        $this->get(route('professor.courses.index'))
            ->assertOk()
            ->assertSee('Curso Atribuído ao Professor')
            ->assertDontSee('Curso de Outro Professor');
    }

    public function test_professor_without_assignments_renders_the_empty_state(): void
    {
        $org = Organization::factory()->create();
        $professor = User::factory()->professor()->create(['org_id' => $org->id]);
        // A sibling Course of the same Organization nobody assigned him to.
        Course::factory()->create(['org_id' => $org->id]);

        $this->actingAs($professor);

        $this->get(route('professor.courses.index'))
            ->assertOk()
            ->assertSee('Nenhum curso atribuído a você.', false);
    }

    public function test_assigned_professor_accesses_classroom_and_forum(): void
    {
        $org = Organization::factory()->create();
        $professor = User::factory()->professor()->create(['org_id' => $org->id]);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $this->assignProfessor($professor, $course);

        $this->actingAs($professor);

        // No `course_user` row at all: docência substitutes enrollment.
        $this->get(route('classroom.show', $course))->assertOk();
        $this->get(route('forum.index', $course))->assertOk();
    }

    public function test_unassigned_professor_from_the_same_org_is_forbidden_on_classroom_and_forum(): void
    {
        $org = Organization::factory()->create();
        $professor = User::factory()->professor()->create(['org_id' => $org->id]);
        $course = Course::factory()->create(['org_id' => $org->id]);

        $this->actingAs($professor);

        // `EnsureStudentIsEnrolled`'s Professor branch is a bare 403 (no
        // catalog redirect exists for a teacher) on both page-shaped routes.
        $this->get(route('classroom.show', $course))->assertForbidden();
        $this->get(route('forum.index', $course))->assertForbidden();
    }

    public function test_only_professors_reach_the_professor_course_listing(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->aluno()->create();
        $gestor = User::factory()->gestor()->create(['org_id' => $org->id]);

        $this->actingAs($aluno);
        $this->get(route('professor.courses.index'))->assertForbidden();

        $this->actingAs($gestor);
        $this->get(route('professor.courses.index'))->assertForbidden();
    }

    public function test_professor_role_is_registered_for_the_platform_roles_enum(): void
    {
        $org = Organization::factory()->create();
        $professor = User::factory()->professor()->create(['org_id' => $org->id]);

        $this->assertTrue($professor->hasRole(RolesEnum::PROFESSOR->value));
        $this->assertNotNull($professor->org_id);
    }

    /**
     * Docência abre o `student.enrolled` para o Professor, mas FAZER
     * prova continua exclusivo do Aluno matriculado: sem o guard do
     * `StudentQuizController`, um Professor atribuído criaria
     * `QuizAttempt` em nome próprio — tentativa que desaguaria na fila
     * de correção que ele mesmo atende.
     */
    public function test_assigned_professor_cannot_take_a_quiz_as_if_a_student(): void
    {
        $org = Organization::factory()->create();
        $professor = User::factory()->professor()->create(['org_id' => $org->id]);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $this->assignProfessor($professor, $course);

        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        Quiz::factory()->for($lesson)->create(['allow_retries' => true]);

        $this->actingAs($professor);

        $this->get(route('student.quizzes.show', $lesson))->assertForbidden();
        $this->post(route('student.quizzes.submit', $lesson), ['answers' => []])->assertForbidden();
        $this->assertDatabaseCount('quiz_attempts', 0);
    }
}
