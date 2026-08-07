<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
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

    public function test_a_student_who_is_not_enrolled_cannot_access_the_classroom(): void
    {
        $orgA = Organization::factory()->create(['name' => 'Organização A']);

        $courseA = Course::factory()->create(['org_id' => $orgA->id, 'is_published' => true]);

        $notEnrolledStudent = User::factory()->create(['org_id' => $orgA->id]);
        $notEnrolledStudent->assignRole(RolesEnum::ALUNO->value);

        $cancelledStudent = User::factory()->create(['org_id' => $orgA->id]);
        $cancelledStudent->assignRole(RolesEnum::ALUNO->value);
        $courseA->students()->attach($cancelledStudent->id, ['enrolled_at' => now(), 'status' => 'cancelled']);

        $this->browse(function (Browser $browser) use ($notEnrolledStudent, $courseA): void {
            $browser->loginAs($notEnrolledStudent)
                ->visit(route('classroom.show', $courseA))
                ->assertSee('403');
        });

        $this->browse(function (Browser $browser) use ($cancelledStudent, $courseA): void {
            $browser->loginAs($cancelledStudent)
                ->visit(route('classroom.show', $courseA))
                ->assertSee('403');
        });
    }

    public function test_completing_an_already_completed_lesson_is_idempotent(): void
    {
        $orgA = Organization::factory()->create(['name' => 'Organização A']);

        $courseA = Course::factory()->create(['org_id' => $orgA->id, 'is_published' => true]);

        $module = Module::factory()->create(['course_id' => $courseA->id]);
        $lesson = Lesson::factory()->richText()->create([
            'module_id' => $module->id,
            'is_published' => true,
        ]);

        $student = User::factory()->create();
        $student->assignRole(RolesEnum::ALUNO->value);

        $courseA->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->browse(function (Browser $browser) use ($student, $courseA, $lesson): void {
            $browser->loginAs($student)
                ->visit(route('classroom.show', $courseA))
                ->waitFor('@lesson-'.$lesson->id)
                ->click('@open-lesson-'.$lesson->id)
                ->waitFor('@mark-complete-button')
                ->click('@mark-complete-button')
                ->waitUntilMissing('@mark-complete-button')
                ->assertVisible('@lesson-completed-badge');
        });

        $progressAfterFirstClick = DB::table('course_user')
            ->where('user_id', $student->id)
            ->where('course_id', $courseA->id)
            ->value('progress_percentage');

        // A second "complete" call cannot be re-triggered from the UI: once
        // `is_completed` is true, `ClassroomController@showLesson` re-renders
        // the button with the `hidden` attribute (SPEC-07 RF20's
        // `:hidden="$isCompleted ?? false"` in
        // `resources/views/classroom/partials/_text-image.blade.php`), and
        // `LessonPlayer.reflectCompletion()` never re-shows it — there is no
        // legitimate UI path back to a clickable button. So, after
        // reloading the page (proving the completed state survives a fresh
        // render), fire the exact same POST `lessons.complete` request the
        // button would have issued — via a synchronous XHR carrying the
        // page's own CSRF token — to exercise a genuine second network call
        // against `MarkLessonCompleteAction`.
        $this->browse(function (Browser $browser) use ($student, $courseA, $lesson): void {
            $browser->loginAs($student)
                ->visit(route('classroom.lesson', $lesson))
                ->waitFor('@lesson-completed-badge')
                ->assertVisible('@lesson-completed-badge')
                ->assertMissing('@mark-complete-button')
                ->script(sprintf(
                    "var xhr = new XMLHttpRequest();
                    xhr.open('POST', %s, false);
                    xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name=\"csrf-token\"]').getAttribute('content'));
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    xhr.setRequestHeader('Accept', 'application/json');
                    xhr.send();",
                    json_encode(route('lessons.complete', $lesson))
                ));

            $browser->visit(route('classroom.show', $courseA))
                ->waitFor('@course-progress-bar');
        });

        $progressAfterSecondCall = DB::table('course_user')
            ->where('user_id', $student->id)
            ->where('course_id', $courseA->id)
            ->value('progress_percentage');

        $this->assertDatabaseCount('lesson_progress', 1);

        $this->assertSame($progressAfterFirstClick, $progressAfterSecondCall);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
        ]);
    }
}
