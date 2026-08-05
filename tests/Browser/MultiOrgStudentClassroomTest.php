<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-07 RF19/RF20 — E2E coverage of the student learning experience:
 * "Meus Cursos" grouped by Organization, opening the classroom, manually
 * completing a text lesson, and seeing the course progress bar reflect it.
 */
class MultiOrgStudentClassroomTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_student_sees_enrollments_grouped_by_org_and_completes_a_text_lesson(): void
    {
        $orgA = Organization::factory()->create(['name' => 'Organização A']);
        $orgB = Organization::factory()->create(['name' => 'Organização B']);

        $courseA = Course::factory()->create(['org_id' => $orgA->id, 'is_published' => true]);
        $courseB = Course::factory()->create(['org_id' => $orgB->id, 'is_published' => true]);

        $module = Module::factory()->create(['course_id' => $courseA->id]);
        $lesson = Lesson::factory()->richText()->create([
            'module_id' => $module->id,
            'is_published' => true,
        ]);

        $student = User::factory()->create();
        $student->assignRole(RolesEnum::ALUNO->value);

        $courseA->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);
        $courseB->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->browse(function (Browser $browser) use ($student, $orgA, $courseA, $lesson): void {
            $browser->loginAs($student)
                ->visit('/meus-cursos')
                ->waitFor('@org-group-'.$orgA->id)
                ->assertSee('Organização A')
                ->assertSee('Organização B')
                ->assertVisible('@student-course-'.$courseA->id)
                ->click('@open-classroom-'.$courseA->id)
                ->waitFor('@lesson-'.$lesson->id)
                ->assertSeeIn('@course-progress-label', '0%')
                ->click('@open-lesson-'.$lesson->id)
                ->waitFor('@mark-complete-button')
                ->assertVisible('@mark-complete-button')
                ->click('@mark-complete-button')
                ->waitUntilMissing('@mark-complete-button')
                ->assertVisible('@lesson-completed-badge')
                ->visit(route('classroom.show', $courseA))
                ->waitFor('@course-progress-bar')
                ->assertSeeIn('@course-progress-label', '100%')
                ->assertVisible('@lesson-completed-'.$lesson->id);
        });

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
        ]);

        $this->assertDatabaseHas('course_user', [
            'user_id' => $student->id,
            'course_id' => $courseA->id,
            'progress_percentage' => 100,
        ]);
    }
}
