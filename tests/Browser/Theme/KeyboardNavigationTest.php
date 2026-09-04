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
 * Acceptance-criteria guardrail: full keyboard navigation on Login,
 * Dashboard, Sala de Aula, and Prova do Aluno — every interactive control
 * reachable via `Tab`, `:focus-visible` shows the global focus ring
 * (`--focus-ring`, 3px/offset 2px, already shipped in `_ds/.../primitives.css`),
 * and no keyboard trap outside the mobile drawer's intentional one (which
 * releases on Escape).
 */
class KeyboardNavigationTest extends DuskTestCase
{
    public function test_login_screen_is_fully_keyboard_navigable(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit(route('login'))->waitFor('@login-form');

            $visited = $this->collectTabbedIdentifiers($browser, 8);

            self::assertContains('login-email', $visited);
            self::assertContains('login-password', $visited);
            self::assertContains('login-submit', $visited);
            self::assertContains('forgot-password-link', $visited);
        });
    }

    public function test_dashboard_is_fully_keyboard_navigable(): void
    {
        $admin = $this->admin();

        $this->browse(function (Browser $browser) use ($admin): void {
            $browser->loginAs($admin)
                ->visit(route('admin.dashboard'))
                ->waitFor('@admin-dashboard');

            $visited = $this->collectTabbedIdentifiers($browser, 15);

            self::assertContains('topbar-profile-link', $visited);
        });
    }

    public function test_classroom_show_is_fully_keyboard_navigable(): void
    {
        [$student, $course, $lesson] = $this->studentWithClassroom();

        $this->browse(function (Browser $browser) use ($student, $course, $lesson): void {
            $browser->loginAs($student)
                ->visit(route('classroom.show', $course))
                ->waitFor('@open-lesson-'.$lesson->id);

            $visited = $this->collectTabbedIdentifiers($browser, 15);

            self::assertContains('open-lesson-'.$lesson->id, $visited);
        });
    }

    public function test_student_quiz_show_is_fully_keyboard_navigable(): void
    {
        [$student, , , $quizLesson, $option] = $this->studentWithQuiz();

        $this->browse(function (Browser $browser) use ($student, $quizLesson): void {
            $browser->loginAs($student)
                ->visit(route('student.quizzes.show', $quizLesson))
                ->waitFor('@quiz-attempt-form');

            $visited = $this->collectTabbedIdentifiers($browser, 12);

            //  STALE no HEAD: `quiz-attempt-submit` virou trigger de
            //    confirm-modal e nasce `disabled` enquanto há questões
            //    sem resposta, então ele nunca é focável por Tab puro
            //    nesta página — falha independente do menu (verificada
            //    via stash no HEAD).
            self::assertContains('quiz-attempt-submit', $visited);
        });
    }

    public function test_mobile_drawer_traps_focus_and_releases_on_escape(): void
    {
        $admin = $this->admin();

        $this->browse(function (Browser $browser) use ($admin): void {
            $browser->loginAs($admin)
                ->resize(375, 812)
                ->visit(route('admin.dashboard'))
                ->waitFor('@mobile-menu-button')
                ->click('@mobile-menu-button')
                ->waitFor('#mobile-sidebar.show');

            // Tabbing repeatedly while the drawer is open never lands focus
            // on an element outside `#mobile-sidebar` (Bootstrap Offcanvas'
            // own focus trap, no project JS involved).
            for ($i = 0; $i < 12; $i++) {
                $browser->keys('', '{tab}');

                $insideDrawer = $browser->script(
                    "return !!document.activeElement.closest('#mobile-sidebar');"
                )[0];

                self::assertTrue($insideDrawer, 'Focus escaped the mobile drawer while it was open (keyboard trap violated).');
            }

            // Escape releases the trap: the drawer closes.
            $browser->keys('#mobile-sidebar', '{escape}')
                ->waitUntilMissing('#mobile-sidebar.show');

            $browser->resize(1920, 1080);
        });
    }

    /**
     * Presses Tab `$times` times from the top of the document and records,
     * for each stop, a stable identifier for the newly-focused element
     * (its `dusk` attribute when present, else its `id`) alongside whether
     * `:focus-visible` painted a visible focus signal. The design system
     * expresses that signal three ways depending on the element: bespoke
     * components (`.ds-fab`, `.ds-chip`, sidebar items, …) use `outline`
     * (`--focus-ring`); Bootstrap's own `.form-control`/`.btn` primitives
     * use `box-shadow` (`$input-focus-box-shadow`, `_bridge.scss`); and
     * `.ds-state-layer` hosts (`ghost`/`tonal` buttons, `_state-layer.scss`)
     * use a `currentColor` fill on their `::after` pseudo-element instead —
     * all three count as a visible ring, so a stop only fails when NONE of
     * them is present.
     *
     * @return array<int, string>
     */
    private function collectTabbedIdentifiers(Browser $browser, int $times): array
    {
        $visited = [];

        for ($i = 0; $i < $times; $i++) {
            $browser->keys('', '{tab}');

            [$identifier, $outlineStyle, $boxShadow, $isStateLayerHost, $isFocusVisible] = $browser->script(
                'var el = document.activeElement; if (!el || el === document.body) return [null, null, null, null, null];'.
                'var s = getComputedStyle(el);'.
                'return [el.getAttribute("dusk") || el.id || null, s.outlineStyle, s.boxShadow, el.classList.contains("ds-state-layer"), el.matches(":focus-visible")];'
            )[0];

            if ($identifier === null) {
                continue;
            }

            $visited[] = $identifier;

            $hasOutline = $outlineStyle !== 'none';
            $hasBoxShadowRing = $boxShadow !== 'none' && $boxShadow !== '';
            // `.ds-state-layer:focus-visible::after` paints a `currentColor`
            // fill (see `_state-layer.scss`) — its `opacity` transitions in
            // over `--duration-fast`, so reading the animated computed
            // value right after the keypress is timing-dependent. What is
            // stable is WHETHER the rule's selector matches: any
            // `.ds-state-layer` host that itself matches `:focus-visible`
            // is guaranteed to end up filled.
            $hasStateLayerFill = $isStateLayerHost && $isFocusVisible;

            self::assertTrue(
                $hasOutline || $hasBoxShadowRing || $hasStateLayerFill,
                "Focused element [{$identifier}] has no visible :focus-visible signal (no outline, box-shadow, or state-layer fill)."
            );
        }

        return $visited;
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        return $admin;
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
     * @return array{0: User, 1: Course, 2: Module, 3: Lesson, 4: QuizOption}
     */
    private function studentWithQuiz(): array
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create();
        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        $option = QuizOption::factory()->for($question, 'question')->correct()->create();
        QuizOption::factory()->for($question, 'question')->incorrect()->create();

        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        return [$student, $course, $module, $lesson, $option];
    }
}
