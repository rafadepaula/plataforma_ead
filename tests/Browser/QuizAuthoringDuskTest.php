<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E coverage of the Gestor-facing question builder on
 * `quizzes/edit.blade.php`: the `QuizBuilder.js` client-side
 * behaviors (type-driven options UI, add/remove option cloning, the
 * min-2-options guard, marking an option correct) plus the full
 * create/edit/reorder round-trip through the real HTTP endpoints.
 *
 * Grouped as ONE lifecycle chain per `testing-conventions`/`laravel-dusk`:
 * a single Gestor session drives every UI interaction, then the actual
 * save/edit/reorder round-trip, checkpointing the DB at each write.
 */
class QuizAuthoringDuskTest extends DuskTestCase
{
    public function test_gestor_quiz_question_builder_ui_and_save_flow_lifecycle(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create();

        $this->browse(function (Browser $browser) use ($gestor, $quiz): void {
            $browser->loginAs($gestor)
                ->visit(route('quizzes.edit', $quiz))
                ->waitFor('@new-question')
                ->click('@new-question')
                ->waitForModalShown('question-create-modal');

            // 1. Default type is single_choice with 2 blank option rows.
            //    Marking the first correct applies `.is-correct` to its row.
            $browser->assertVisible('@option-correct-create-0')
                ->assertVisible('@option-correct-create-1')
                ->click('@option-correct-create-0');

            $this->assertTrue(
                $browser->script(
                    "return document.querySelector('[dusk=\"option-correct-create-0\"]').closest('[data-option-row]').classList.contains('is-correct');"
                )[0]
            );
            $this->assertFalse(
                $browser->script(
                    "return document.querySelector('[dusk=\"option-correct-create-1\"]').closest('[data-option-row]').classList.contains('is-correct');"
                )[0]
            );

            // 2. Adding an option clones the template: a fresh, unchecked
            //    3rd row appears with `__INDEX__` substituted to `2`.
            $browser->click('@add-option-create');
            $browser->waitUntil(
                "document.querySelectorAll('[data-options-list=\"create\"] [data-option-row]').length === 3"
            );
            $this->assertSame(
                'options[2][option_text]',
                $browser->script(
                    "return document.querySelectorAll('[data-options-list=\"create\"] [data-option-row]')[2].querySelector('input[type=\"text\"]').getAttribute('name');"
                )[0]
            );
            $this->assertFalse(
                $browser->script(
                    "return document.querySelectorAll('[data-options-list=\"create\"] [data-option-row]')[2].classList.contains('is-correct');"
                )[0]
            );

            // 3. Removing rows down to 2 works; removing the 3rd one more
            //    time is blocked with the min-2-options toast and the row
            //    count never drops below 2.
            $browser->script(
                "document.querySelectorAll('[data-options-list=\"create\"] [data-option-row]')[2].querySelector('[data-remove-option-btn]').click();"
            );
            $browser->waitUntil(
                "document.querySelectorAll('[data-options-list=\"create\"] [data-option-row]').length === 2"
            );

            $browser->script(
                "document.querySelectorAll('[data-options-list=\"create\"] [data-option-row]')[0].querySelector('[data-remove-option-btn]').click();"
            );
            $browser->waitForText('Uma questão precisa de ao menos 2 opções.');
            $this->assertSame(
                2,
                $browser->script(
                    "return document.querySelectorAll('[data-options-list=\"create\"] [data-option-row]').length;"
                )[0]
            );

            // 4. Switching to true_false shows exactly 2 readonly rows with
            //    add/remove hidden.
            $browser->select('@question-type-create', 'true_false');
            $browser->waitUntil(
                "document.querySelector('[data-add-option-btn=\"create\"]').classList.contains('d-none')"
            );
            $this->assertSame(
                2,
                $browser->script(
                    "return document.querySelectorAll('[data-options-list=\"create\"] [data-option-row]').length;"
                )[0]
            );
            $this->assertTrue(
                $browser->script(
                    "return Array.from(document.querySelectorAll('[data-options-list=\"create\"] [data-remove-option-btn]')).every(b => b.classList.contains('d-none'));"
                )[0]
            );

            // 5. Switching to essay hides the entire options block.
            $browser->select('@question-type-create', 'essay');
            $browser->waitUntil(
                "document.querySelector('[data-options-container=\"create\"]').classList.contains('d-none')"
            );

            // 6. Back to single_choice, fill in the question and save —
            //    the create-via-modal leg of the full save flow.
            $browser->select('@question-type-create', 'single_choice')
                ->waitUntil("!document.querySelector('[data-options-container=\"create\"]').classList.contains('d-none')")
                ->type('@question-text-create', 'Qual é a capital do Brasil?')
                ->type('@option-text-create-0', 'Brasília')
                ->type('@option-text-create-1', 'São Paulo')
                ->click('@option-correct-create-0')
                ->click('@question-submit-create');

            $browser->waitForText('Questão criada com sucesso.')
                ->waitFor('@question-list');
        });

        $question = QuizQuestion::query()->where('quiz_id', $quiz->id)->firstOrFail();
        $this->assertSame('Qual é a capital do Brasil?', $question->question_text);
        $this->assertSame('single_choice', $question->type);
        $this->assertSame(1, $question->options()->where('is_correct', true)->count());

        // 7. Edit the just-created question via its modal — the edit leg
        //    of the full save flow.
        $this->browse(function (Browser $browser) use ($gestor, $quiz, $question): void {
            $suffix = 'edit-'.$question->id;

            $browser->loginAs($gestor)
                ->visit(route('quizzes.edit', $quiz))
                ->waitFor('@question-list')
                ->click('@edit-question-'.$question->id)
                ->waitForModalShown('question-edit-modal-'.$question->id)
                ->clear('@question-text-'.$suffix)
                ->type('@question-text-'.$suffix, 'Qual é a capital federal do Brasil?')
                ->click('@question-submit-'.$suffix)
                ->waitForText('Questão atualizada com sucesso.')
                ->waitFor('@question-list')
                ->assertSeeIn('@question-list', 'Qual é a capital federal do Brasil?');
        });

        $this->assertSame('Qual é a capital federal do Brasil?', $question->fresh()->question_text);

        // 8. Reorder via the same `ModuleReorder.js` `persistOrder()` path
        //    the reorderable list already reuses — round-trips through the
        //    real `quiz-questions.reorder` endpoint.
        $secondQuestion = QuizQuestion::factory()->for($quiz)->singleChoice()->create([
            'question_text' => 'Segunda questão',
            'order_index' => 1,
        ]);

        $this->browse(function (Browser $browser) use ($gestor, $quiz, $question, $secondQuestion): void {
            $browser->loginAs($gestor)
                ->visit(route('quizzes.edit', $quiz))
                ->waitFor('@question-list')
                ->script(
                    "(function () {
                        var list = document.querySelector('[dusk=\"question-list\"]');
                        var dragged = document.querySelector('[data-id=\"{$secondQuestion->id}\"]');
                        var target = document.querySelector('[data-id=\"{$question->id}\"]');
                        list.insertBefore(dragged, target);
                        window.ModuleReorder.persistOrder(list);
                    })();"
                );

            $browser->waitForText('Ordem atualizada com sucesso.')
                ->refresh()
                ->waitFor('@question-list');
        });

        $this->assertSame(0, $secondQuestion->fresh()->order_index);
        $this->assertSame(1, $question->fresh()->order_index);
    }

    /**
     * UC11's exception flow 6.1 ("Questão Objetiva sem Opção Correta
     * Marcada"): submitting a `single_choice` question with no option
     * checked as correct is rejected server-side (HTTP 422 /
     * `StoreQuizQuestionRequest`'s cross-field rule), and the validation
     * message comes back rendered on the page (`layout.alerts`'s
     * `$errors->any()` block — the app-wide convention every form,
     * modal-hosted or not, relies on; see `quizzes-conventions`) instead
     * of the question ever being persisted or a success toast appearing.
     */
    public function test_question_without_a_correct_option_is_rejected_with_a_422_validation_error(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create();

        $this->browse(function (Browser $browser) use ($gestor, $quiz): void {
            $browser->loginAs($gestor)
                ->visit(route('quizzes.edit', $quiz))
                ->waitFor('@new-question')
                ->click('@new-question')
                ->waitForModalShown('question-create-modal')
                ->type('@question-text-create', 'Qual é a capital do Brasil?')
                ->type('@option-text-create-0', 'Brasília')
                ->type('@option-text-create-1', 'São Paulo')
                // Neither `@option-correct-create-0` nor `-1` is clicked —
                // a single_choice question with zero correct options.
                ->click('@question-submit-create');

            $browser->waitForText('Questões de escolha única devem ter exatamente 1 opção correta.')
                ->assertDontSee('Questão criada com sucesso.');
        });

        $this->assertSame(0, QuizQuestion::query()->where('quiz_id', $quiz->id)->count());
    }
}
