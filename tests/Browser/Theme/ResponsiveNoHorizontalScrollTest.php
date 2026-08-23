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
 * Acceptance-criteria guardrail from the mobile and accessibility
 * guidelines: no screen may produce horizontal
 * scroll at 320/375/768/1024/1440. `document.body.scrollWidth` is compared
 * against `window.innerWidth` — the moment the body is wider than the
 * viewport, a horizontal scrollbar exists.
 *
 * Covers the 4 priority Aluno screens plus a representative sample of
 * management screens (Admin/Gestor), per the plan's open question on
 * exhaustive coverage — this is not every screen in the app, but a
 * cross-section spanning both actor profiles.
 */
class ResponsiveNoHorizontalScrollTest extends DuskTestCase
{
    private const WIDTHS = [320, 375, 768, 1024, 1440];

    public function test_login_screen_has_no_horizontal_scroll(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit(route('login'))->waitFor('@login-form');

            $this->assertNoHorizontalScrollAtEveryWidth($browser);
        });
    }

    public function test_dashboard_screen_has_no_horizontal_scroll(): void
    {
        $admin = $this->admin();

        $this->browse(function (Browser $browser) use ($admin): void {
            $browser->loginAs($admin)
                ->visit(route('admin.dashboard'))
                ->waitFor('@admin-dashboard');

            $this->assertNoHorizontalScrollAtEveryWidth($browser);
        });
    }

    public function test_student_courses_index_has_no_horizontal_scroll(): void
    {
        [$student, $course] = $this->studentWithClassroom();

        $this->browse(function (Browser $browser) use ($student, $course): void {
            $browser->loginAs($student)
                ->visit(route('student.courses.index'))
                ->waitFor('@student-course-'.$course->id, 10);

            $this->assertNoHorizontalScrollAtEveryWidth($browser);
        });
    }

    public function test_classroom_show_has_no_horizontal_scroll(): void
    {
        [$student, $course, $lesson] = $this->studentWithClassroom();

        $this->browse(function (Browser $browser) use ($student, $course, $lesson): void {
            $browser->loginAs($student)
                ->visit(route('classroom.show', $course))
                ->waitFor('@open-lesson-'.$lesson->id, 10);

            $this->assertNoHorizontalScrollAtEveryWidth($browser);
        });
    }

    public function test_classroom_lesson_has_no_horizontal_scroll(): void
    {
        [$student, , $lesson] = $this->studentWithClassroom();

        $this->browse(function (Browser $browser) use ($student, $lesson): void {
            $browser->loginAs($student)
                ->visit(route('classroom.lesson', $lesson))
                ->waitFor('@back-to-classroom', 10);

            $this->assertNoHorizontalScrollAtEveryWidth($browser);
        });
    }

    public function test_student_quiz_show_has_no_horizontal_scroll(): void
    {
        [$student, , , $quizLesson] = $this->studentWithQuiz();

        $this->browse(function (Browser $browser) use ($student, $quizLesson): void {
            $browser->loginAs($student)
                ->visit(route('student.quizzes.show', $quizLesson))
                ->waitFor('@quiz-attempt-form', 10);

            $this->assertNoHorizontalScrollAtEveryWidth($browser);
        });
    }

    public function test_courses_index_management_screen_has_no_horizontal_scroll(): void
    {
        [$gestor, $course] = $this->gestorWithCourse();

        $this->browse(function (Browser $browser) use ($gestor, $course): void {
            $browser->resize(375, 900)
                ->loginAs($gestor)
                ->visit(route('courses.index'))
                ->waitFor('@course-row-'.$course->id);

            $courseRowSelector = '[dusk="course-row-'.$course->id.'"]';
            $layout = $browser->script(sprintf(<<<'JS'
                const rowSelector = %s;
                const rows = document.querySelectorAll(rowSelector);
                const row = rows[0];
                const table = row.closest('table');
                const tableHead = table.querySelector('thead');
                const firstHeader = tableHead.querySelector('th');
                const firstCell = row.querySelector('td[data-label="Título"]');
                const body = table.querySelector('tbody');
                const rowRect = row.getBoundingClientRect();
                const cellRect = firstCell.getBoundingClientRect();
                const labelStyle = getComputedStyle(firstCell, '::before');
                const labelColorParts = labelStyle.color.match(/[\d.]+/g) ?? [];
                const labelColorAlpha = labelColorParts.length >= 4 ? Number(labelColorParts[3]) : 1;

                return {
                    selectorCount: rows.length,
                    rowDisplay: getComputedStyle(row).display,
                    rowWidth: rowRect.width,
                    rowHeight: rowRect.height,
                    cellWidth: cellRect.width,
                    cellHeight: cellRect.height,
                    bodyDisplay: getComputedStyle(body).display,
                    headerDisplay: getComputedStyle(tableHead).display,
                    headerPosition: getComputedStyle(tableHead).position,
                    headerWidth: tableHead.getBoundingClientRect().width,
                    headerHeight: tableHead.getBoundingClientRect().height,
                    headerOverflow: getComputedStyle(tableHead).overflow,
                    headerAriaHidden: tableHead.getAttribute('aria-hidden'),
                    firstHeaderDisplay: getComputedStyle(firstHeader).display,
                    labelContent: labelStyle.content,
                    labelDisplay: labelStyle.display,
                    labelVisibility: labelStyle.visibility,
                    labelOpacity: Number(labelStyle.opacity),
                    labelColor: labelStyle.color,
                    labelColorAlpha,
                };
            JS, json_encode($courseRowSelector, JSON_THROW_ON_ERROR)))[0];

            self::assertSame(1, $layout['selectorCount']);
            self::assertSame('block', $layout['rowDisplay']);
            self::assertSame('grid', $layout['bodyDisplay']);
            self::assertGreaterThan(0, $layout['rowWidth']);
            self::assertLessThanOrEqual(375, $layout['rowWidth']);
            self::assertGreaterThan(0, $layout['rowHeight']);
            self::assertGreaterThan(0, $layout['cellWidth']);
            self::assertGreaterThan(0, $layout['cellHeight']);
            self::assertNotSame('none', $layout['headerDisplay']);
            self::assertSame('absolute', $layout['headerPosition']);
            self::assertLessThanOrEqual(1, $layout['headerWidth']);
            self::assertLessThanOrEqual(1, $layout['headerHeight']);
            self::assertSame('hidden', $layout['headerOverflow']);
            self::assertNull($layout['headerAriaHidden']);
            self::assertNotSame('none', $layout['firstHeaderDisplay']);
            self::assertSame('"Título"', $layout['labelContent']);
            self::assertNotSame('none', $layout['labelDisplay']);
            self::assertSame('visible', $layout['labelVisibility']);
            self::assertGreaterThan(0, $layout['labelOpacity']);
            self::assertNotSame('transparent', $layout['labelColor']);
            self::assertGreaterThan(0, $layout['labelColorAlpha']);

            $browser->assertVisible('@manage-modules-'.$course->id)
                ->assertVisible('@manage-completion-rules-'.$course->id)
                ->assertVisible('@edit-course-'.$course->id)
                ->assertVisible('@delete-course-'.$course->id)
                ->assertDisabled('@delete-course-'.$course->id)
                ->assertMissing('#course-actions-toggle-'.$course->id);

            foreach (['manage-modules', 'manage-completion-rules', 'edit-course'] as $action) {
                $actionSelector = '[dusk="'.$action.'-'.$course->id.'"]';
                $actionHasFocus = $browser->script(
                    'document.querySelector('.json_encode($actionSelector, JSON_THROW_ON_ERROR).').focus(); return document.activeElement === document.querySelector('.json_encode($actionSelector, JSON_THROW_ON_ERROR).');'
                )[0];

                self::assertTrue($actionHasFocus);
            }

            $this->assertNoHorizontalScrollAtEveryWidth($browser);
        });
    }

    public function test_organizations_index_management_screen_has_no_horizontal_scroll(): void
    {
        $admin = $this->admin();

        $this->browse(function (Browser $browser) use ($admin): void {
            $browser->loginAs($admin)
                ->visit(route('organizations.index'))
                ->waitFor('@topbar-profile-link');

            $this->assertNoHorizontalScrollAtEveryWidth($browser);
        });
    }

    public function test_admin_users_index_management_screen_has_no_horizontal_scroll(): void
    {
        $admin = $this->admin();

        $this->browse(function (Browser $browser) use ($admin): void {
            $browser->loginAs($admin)
                ->visit(route('admin.users.index'))
                ->waitFor('@topbar-profile-link');

            $this->assertNoHorizontalScrollAtEveryWidth($browser);
        });
    }

    private function assertNoHorizontalScrollAtEveryWidth(Browser $browser): void
    {
        foreach (self::WIDTHS as $width) {
            $browser->resize($width, 900);

            $overflow = $browser->script(
                'return document.body.scrollWidth > window.innerWidth;'
            )[0];

            self::assertFalse(
                $overflow,
                "Horizontal scroll detected at {$width}px on ".$browser->driver->getCurrentURL()
            );
        }

        $browser->resize(1920, 1080);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        return $admin;
    }

    /**
     * @return array{0: User, 1: Course}
     */
    private function gestorWithCourse(): array
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $course = Course::factory()->create([
            'org_id' => $org->id,
            'title' => 'Curso Responsivo',
            'is_published' => true,
        ]);
        $module = Module::factory()->for($course)->create();
        Lesson::factory()->richText()->for($module)->create(['is_published' => true]);

        $student = User::factory()->create(['org_id' => $org->id]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        return [$gestor, $course];
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
