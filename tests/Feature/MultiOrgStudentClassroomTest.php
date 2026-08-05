<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * SPEC-07 RF19/RF20 — "Meus Cursos" (student.courses.index) grouped by
 * Organization, and the `student.enrolled`-gated classroom/lesson routes:
 * a multi-org Aluno only sees/accesses Courses they hold an
 * active/completed `course_user` enrollment for, and Admin/Gestor access
 * the classroom via the same middleware (distinct role, not policy).
 */
class MultiOrgStudentClassroomTest extends TestCase
{
    private function makeAluno(): User
    {
        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        return $aluno;
    }

    public function test_student_courses_index_groups_enrollments_by_organization(): void
    {
        $aluno = $this->makeAluno();

        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $courseA = Course::factory()->create(['org_id' => $orgA->id]);
        $courseB = Course::factory()->create(['org_id' => $orgB->id]);

        $aluno->courses()->attach($courseA->id, ['status' => 'active', 'enrolled_at' => now()]);
        $aluno->courses()->attach($courseB->id, ['status' => 'active', 'enrolled_at' => now()]);

        $this->actingAs($aluno);

        $response = $this->get(route('student.courses.index'));

        $response->assertOk();
        $response->assertSee($courseA->title);
        $response->assertSee($courseB->title);
    }

    public function test_student_courses_index_excludes_cancelled_enrollments(): void
    {
        $aluno = $this->makeAluno();

        $org = Organization::factory()->create();
        $activeCourse = Course::factory()->create(['org_id' => $org->id]);
        $cancelledCourse = Course::factory()->create(['org_id' => $org->id]);

        $aluno->courses()->attach($activeCourse->id, ['status' => 'active', 'enrolled_at' => now()]);
        $aluno->courses()->attach($cancelledCourse->id, ['status' => 'cancelled', 'enrolled_at' => now()]);

        $this->actingAs($aluno);

        $response = $this->get(route('student.courses.index'));

        $response->assertOk();
        $response->assertSee($activeCourse->title);
        $response->assertDontSee($cancelledCourse->title);
    }

    public function test_gestor_cannot_access_student_courses_index(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $response = $this->get(route('student.courses.index'));

        $response->assertForbidden();
    }

    public function test_enrolled_student_can_view_the_classroom(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->richText()->create(['is_published' => true]);

        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        $this->actingAs($aluno);

        $response = $this->get(route('classroom.show', $course));

        $response->assertOk();
        $response->assertSee($lesson->title);
    }

    public function test_student_from_another_org_without_enrollment_is_forbidden_from_the_classroom(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        Module::factory()->for($course)->create();

        $this->actingAs($aluno);

        $response = $this->get(route('classroom.show', $course));

        $response->assertForbidden();
    }

    public function test_enrolled_student_can_view_a_lesson_with_its_progress_state(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->richText()->create(['is_published' => true]);

        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        LessonProgress::query()->create([
            'user_id' => $aluno->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
            'completed_at' => now(),
        ]);

        $this->actingAs($aluno);

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        $response->assertSee($lesson->title);
    }

    public function test_enrolled_student_cannot_view_an_unpublished_lesson_directly(): void
    {
        $aluno = $this->makeAluno();
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->richText()->create(['is_published' => false]);

        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        $this->actingAs($aluno);

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertNotFound();
    }

    public function test_gestor_from_a_different_org_cannot_view_the_classroom(): void
    {
        $courseOrg = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $courseOrg->id]);
        Module::factory()->for($course)->create();

        $otherOrg = Organization::factory()->create();
        $this->actingAsOrgUser($otherOrg, RolesEnum::GESTOR->value);

        $response = $this->get(route('classroom.show', $course));

        $response->assertForbidden();
    }
}
