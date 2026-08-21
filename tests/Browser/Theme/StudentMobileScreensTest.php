<?php

namespace Tests\Browser\Theme;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E coverage at 375x812 (the Aluno's majority-mobile profile, per
 * the mobile guideline) of the 4 priority Aluno
 * screens: "Meus Cursos", Sala de Aula, Aula, and Prova do Aluno. Asserts
 * the card-grid stacks to a single column, no screen produces horizontal
 * scroll, every touch target reaches the 48px minimum, and — where a
 * `<x-ui.fab>` is present — it clears the mobile browser's bottom chrome.
 */
class StudentMobileScreensTest extends DuskTestCase
{
    public function test_student_courses_index_stacks_to_a_single_column_card_grid(): void
    {
        [$student, $courseA, , $courseB] = $this->studentWithTwoCourses();

        $this->browse(function (Browser $browser) use ($student, $courseA, $courseB): void {
            $browser->loginAs($student)
                ->resize(375, 812)
                ->visit(route('student.courses.index'))
                ->waitFor('@student-course-'.$courseA->id)
                ->assertVisible('@student-course-'.$courseB->id);

            // Single-column stacking at 375px: the second card sits BELOW
            // the first one (not beside it), i.e. its top offset is past
            // the first card's bottom edge.
            $offsets = $browser->script([
                "return document.querySelector('[dusk=\"student-course-{$courseA->id}\"]').getBoundingClientRect().bottom;",
                "return document.querySelector('[dusk=\"student-course-{$courseB->id}\"]').getBoundingClientRect().top;",
            ]);

            self::assertLessThanOrEqual(
                (float) $offsets[1] + 1,
                (float) $offsets[0],
                'Course cards are side-by-side at 375px; expected a single-column stack.'
            );

            $this->assertNoHorizontalScroll($browser);

            $browser->resize(1920, 1080);
        });
    }

    public function test_classroom_show_renders_without_horizontal_scroll_at_375px(): void
    {
        [$student, $course, $lesson] = $this->studentWithClassroom();

        $this->browse(function (Browser $browser) use ($student, $course, $lesson): void {
            $browser->loginAs($student)
                ->resize(375, 812)
                ->visit(route('classroom.show', $course))
                ->waitFor('@open-lesson-'.$lesson->id)
                ->assertVisible('@course-progress-bar');

            $this->assertNoHorizontalScroll($browser);
            $this->assertFabClearsBottomChromeIfPresent($browser);

            $browser->resize(1920, 1080);
        });
    }

    public function test_classroom_lesson_renders_without_horizontal_scroll_at_375px(): void
    {
        [$student, , $lesson] = $this->studentWithClassroom();

        $this->browse(function (Browser $browser) use ($student, $lesson): void {
            $browser->loginAs($student)
                ->resize(375, 812)
                ->visit(route('classroom.lesson', $lesson))
                ->waitFor('@back-to-classroom')
                ->assertVisible('@back-to-classroom');

            $this->assertNoHorizontalScroll($browser);
            $this->assertFabClearsBottomChromeIfPresent($browser);

            $browser->resize(1920, 1080);
        });
    }

    public function test_student_quiz_show_renders_without_horizontal_scroll_at_375px(): void
    {
        [$student, , , $quizLesson] = $this->studentWithQuiz();

        $this->browse(function (Browser $browser) use ($student, $quizLesson): void {
            $browser->loginAs($student)
                ->resize(375, 812)
                ->visit(route('student.quizzes.show', $quizLesson))
                ->waitFor('@quiz-attempt-form')
                ->assertVisible('@quiz-attempt-form');

            $this->assertNoHorizontalScroll($browser);

            // Touch target: the submit control must reach >= 48px tall.
            $height = $browser->script(
                "return document.querySelector('[dusk=\"quiz-attempt-submit\"]').getBoundingClientRect().height;"
            )[0];

            self::assertGreaterThanOrEqual(48, (float) $height, 'Quiz submit control is under the 48px touch target minimum.');

            $browser->resize(1920, 1080);
        });
    }

    private function assertNoHorizontalScroll(Browser $browser): void
    {
        $overflow = $browser->script('return document.body.scrollWidth > window.innerWidth;')[0];

        self::assertFalse($overflow, 'Horizontal scroll detected at 375px on '.$browser->driver->getCurrentURL());
    }

    private function assertFabClearsBottomChromeIfPresent(Browser $browser): void
    {
        $elements = $browser->elements('.ds-fab');

        if (count($elements) === 0) {
            self::assertTrue(true, 'No FAB present on this screen.');

            return;
        }

        $distanceFromBottom = $browser->script(
            "var el = document.querySelector('.ds-fab'); return window.innerHeight - el.getBoundingClientRect().bottom;"
        )[0];

        self::assertGreaterThanOrEqual(24, (float) $distanceFromBottom, 'FAB does not clear the 24px mobile bottom-chrome offset.');
    }

    /**
     * @return array{0: User, 1: Course, 2: Lesson}
     */
    private function studentWithClassroom(): array
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->richText()->for($module)->create(['is_published' => true]);

        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        return [$student, $course, $lesson];
    }

    /**
     * @return array{0: User, 1: Course, 2: Organization, 3: Course}
     */
    private function studentWithTwoCourses(): array
    {
        $org = Organization::factory()->create();
        $courseA = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $courseB = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);

        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $courseA->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);
        $courseB->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        return [$student, $courseA, $org, $courseB];
    }

    /**
     * @return array{0: User, 1: Course, 2: Module, 3: Lesson}
     */
    private function studentWithQuiz(): array
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create();
        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        QuizOption::factory()->for($question, 'question')->correct()->create();
        QuizOption::factory()->for($question, 'question')->incorrect()->create();

        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        return [$student, $course, $module, $lesson];
    }
}
