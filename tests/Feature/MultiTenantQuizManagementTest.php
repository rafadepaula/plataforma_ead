<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use Tests\TestCase;

/**
 * Cross-org isolation coverage for quiz authoring (SPEC-24), plus the four
 * question-type CRUD happy paths and the reorder dense-reassignment rule.
 * This intentionally overlaps some scenarios already covered by
 * `QuizManagementTest.php` — that file cannot be deleted/renamed without
 * explicit approval (project rule), so this file exists purely to satisfy
 * SPEC-24's named-file acceptance criterion while staying additive.
 */
class MultiTenantQuizManagementTest extends TestCase
{
    private function quizLesson(Organization $org): Lesson
    {
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();

        return Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
    }

    // Cross-org isolation: Quiz itself

    public function test_gestor_of_org_a_cannot_view_a_quiz_belonging_to_org_b(): void
    {
        $orgB = Organization::factory()->create();
        $lessonB = $this->quizLesson($orgB);
        $quizB = Quiz::factory()->for($lessonB)->create();

        $this->actingAsOrgUser(role: RolesEnum::GESTOR->value);

        $this->get(route('quizzes.edit', $quizB))->assertForbidden();
    }

    public function test_gestor_of_org_a_cannot_update_a_quiz_belonging_to_org_b(): void
    {
        $orgB = Organization::factory()->create();
        $lessonB = $this->quizLesson($orgB);
        $quizB = Quiz::factory()->for($lessonB)->create(['title' => 'Original da Org B']);

        $this->actingAsOrgUser(role: RolesEnum::GESTOR->value);

        $this->put(route('quizzes.update', $quizB), [
            'title' => 'Sequestrado pela Org A',
            'min_score_percentage' => 70,
        ])->assertForbidden();

        $this->assertDatabaseHas('quizzes', ['id' => $quizB->id, 'title' => 'Original da Org B']);
    }

    public function test_gestor_of_org_a_cannot_delete_a_quiz_belonging_to_org_b(): void
    {
        $orgB = Organization::factory()->create();
        $lessonB = $this->quizLesson($orgB);
        $quizB = Quiz::factory()->for($lessonB)->create();

        $this->actingAsOrgUser(role: RolesEnum::GESTOR->value);

        $this->delete(route('quizzes.destroy', $quizB))->assertForbidden();

        $this->assertDatabaseHas('quizzes', ['id' => $quizB->id]);
    }

    // Cross-org isolation: QuizQuestion CRUD via direct route hits

    public function test_gestor_of_org_a_cannot_create_a_question_on_org_bs_quiz(): void
    {
        $orgB = Organization::factory()->create();
        $quizB = Quiz::factory()->for($this->quizLesson($orgB))->create();

        $this->actingAsOrgUser(role: RolesEnum::GESTOR->value);

        $this->post(route('quiz-questions.store', $quizB), [
            'question_text' => 'Invasão',
            'type' => 'single_choice',
            'options' => [
                ['option_text' => 'A', 'is_correct' => true],
                ['option_text' => 'B', 'is_correct' => false],
            ],
        ])->assertForbidden();

        $this->assertSame(0, $quizB->questions()->count());
    }

    public function test_gestor_of_org_a_cannot_update_a_question_belonging_to_org_bs_quiz(): void
    {
        $orgB = Organization::factory()->create();
        $quizB = Quiz::factory()->for($this->quizLesson($orgB))->create();
        $questionB = QuizQuestion::factory()->for($quizB)->singleChoice()->create(['question_text' => 'Original']);
        $optionB = QuizOption::factory()->for($questionB, 'question')->correct()->create();

        $this->actingAsOrgUser(role: RolesEnum::GESTOR->value);

        $this->put(route('quiz-questions.update', $questionB), [
            'question_text' => 'Sequestrado',
            'type' => 'single_choice',
            'options' => [
                ['id' => $optionB->id, 'option_text' => 'A', 'is_correct' => true],
                ['option_text' => 'B', 'is_correct' => false],
            ],
        ])->assertForbidden();

        $this->assertDatabaseHas('quiz_questions', ['id' => $questionB->id, 'question_text' => 'Original']);
    }

    public function test_gestor_of_org_a_cannot_delete_a_question_belonging_to_org_bs_quiz(): void
    {
        $orgB = Organization::factory()->create();
        $quizB = Quiz::factory()->for($this->quizLesson($orgB))->create();
        $questionB = QuizQuestion::factory()->for($quizB)->singleChoice()->create();

        $this->actingAsOrgUser(role: RolesEnum::GESTOR->value);

        $this->delete(route('quiz-questions.destroy', $questionB))->assertForbidden();

        $this->assertDatabaseHas('quiz_questions', ['id' => $questionB->id]);
    }

    public function test_gestor_of_org_a_cannot_reorder_questions_belonging_to_org_bs_quiz(): void
    {
        $orgB = Organization::factory()->create();
        $quizB = Quiz::factory()->for($this->quizLesson($orgB))->create();
        $firstB = QuizQuestion::factory()->for($quizB)->singleChoice()->create(['order_index' => 0]);
        $secondB = QuizQuestion::factory()->for($quizB)->singleChoice()->create(['order_index' => 1]);

        $this->actingAsOrgUser(role: RolesEnum::GESTOR->value);

        $this->postJson(route('quiz-questions.reorder', $quizB), [
            'ordered_ids' => [$secondB->id, $firstB->id],
        ])->assertForbidden();

        $this->assertSame(0, $firstB->fresh()->order_index);
        $this->assertSame(1, $secondB->fresh()->order_index);
    }

    // Options payload isolation: an org-A gestor cannot upsert options onto an org-B option id

    public function test_options_payload_targeting_another_orgs_option_id_is_isolated(): void
    {
        $orgB = Organization::factory()->create();
        $quizB = Quiz::factory()->for($this->quizLesson($orgB))->create();
        $questionB = QuizQuestion::factory()->for($quizB)->singleChoice()->create();
        $optionB = QuizOption::factory()->for($questionB, 'question')->incorrect()->create(['option_text' => 'Opção da Org B']);

        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $ownQuiz = Quiz::factory()->for($this->quizLesson($org))->create();
        $ownQuestion = QuizQuestion::factory()->for($ownQuiz)->singleChoice()->create();

        // Attempting to smuggle org B's option id into an update on the
        // gestor's OWN question must not let it reach across tenants:
        // `UpdateQuizQuestionRequest`'s `options.*.id` rule only checks
        // `exists:quiz_options,id`, so the id itself validates — the real
        // isolation guard is that `QuizQuestionController::update()` only
        // ever queries `$quizQuestion->options()->where('id', ...)`,
        // scoped to the (already policy-authorized) OWN question. Org B's
        // option must therefore be left completely untouched.
        $this->put(route('quiz-questions.update', $ownQuestion), [
            'question_text' => 'Minha questão',
            'type' => 'single_choice',
            'options' => [
                ['id' => $optionB->id, 'option_text' => 'Sequestrado', 'is_correct' => true],
                ['option_text' => 'Nova opção', 'is_correct' => false],
            ],
        ])->assertRedirect(route('quizzes.edit', $ownQuiz));

        $this->assertDatabaseHas('quiz_options', [
            'id' => $optionB->id,
            'option_text' => 'Opção da Org B',
            'is_correct' => false,
        ]);
        $this->assertDatabaseMissing('quiz_options', ['option_text' => 'Sequestrado']);
    }

    // 4 question-type CRUD happy paths

    public function test_gestor_can_create_a_single_choice_question(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $quiz = Quiz::factory()->for($this->quizLesson($org))->create();

        $this->post(route('quiz-questions.store', $quiz), [
            'question_text' => 'Capital do Brasil?',
            'type' => 'single_choice',
            'options' => [
                ['option_text' => 'Brasília', 'is_correct' => true],
                ['option_text' => 'São Paulo', 'is_correct' => false],
            ],
        ])->assertRedirect(route('quizzes.edit', $quiz));

        $question = QuizQuestion::query()->where('quiz_id', $quiz->id)->firstOrFail();
        $this->assertSame('single_choice', $question->type);
        $this->assertSame(1, $question->options()->where('is_correct', true)->count());
        $this->assertSame(2, $question->options()->count());
    }

    public function test_gestor_can_create_a_multiple_choice_question(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $quiz = Quiz::factory()->for($this->quizLesson($org))->create();

        $this->post(route('quiz-questions.store', $quiz), [
            'question_text' => 'Quais são números primos?',
            'type' => 'multiple_choice',
            'options' => [
                ['option_text' => '2', 'is_correct' => true],
                ['option_text' => '3', 'is_correct' => true],
                ['option_text' => '4', 'is_correct' => false],
            ],
        ])->assertRedirect(route('quizzes.edit', $quiz));

        $question = QuizQuestion::query()->where('quiz_id', $quiz->id)->firstOrFail();
        $this->assertSame('multiple_choice', $question->type);
        $this->assertSame(2, $question->options()->where('is_correct', true)->count());
        $this->assertSame(3, $question->options()->count());
    }

    public function test_gestor_can_create_a_true_false_question(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $quiz = Quiz::factory()->for($this->quizLesson($org))->create();

        $this->post(route('quiz-questions.store', $quiz), [
            'question_text' => 'O Sol é uma estrela.',
            'type' => 'true_false',
            'options' => [
                ['option_text' => 'Verdadeiro', 'is_correct' => true],
                ['option_text' => 'Falso', 'is_correct' => false],
            ],
        ])->assertRedirect(route('quizzes.edit', $quiz));

        $question = QuizQuestion::query()->where('quiz_id', $quiz->id)->firstOrFail();
        $this->assertSame('true_false', $question->type);
        $this->assertSame(2, $question->options()->count());
        $this->assertSame(1, $question->options()->where('is_correct', true)->count());
    }

    public function test_gestor_can_create_an_essay_question_with_no_options(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $quiz = Quiz::factory()->for($this->quizLesson($org))->create();

        $this->post(route('quiz-questions.store', $quiz), [
            'question_text' => 'Disserte sobre o tema estudado.',
            'type' => 'essay',
        ])->assertRedirect(route('quizzes.edit', $quiz));

        $question = QuizQuestion::query()->where('quiz_id', $quiz->id)->firstOrFail();
        $this->assertSame('essay', $question->type);
        $this->assertSame(0, $question->options()->count());
    }

    // Reorder dense-reassignment

    public function test_reordering_questions_reassigns_dense_zero_based_indices_regardless_of_original_order_index(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $quiz = Quiz::factory()->for($this->quizLesson($org))->create();

        $first = QuizQuestion::factory()->for($quiz)->singleChoice()->create(['order_index' => 0]);
        $second = QuizQuestion::factory()->for($quiz)->singleChoice()->create(['order_index' => 5]);
        $third = QuizQuestion::factory()->for($quiz)->singleChoice()->create(['order_index' => 12]);

        $this->postJson(route('quiz-questions.reorder', $quiz), [
            'ordered_ids' => [$third->id, $first->id, $second->id],
        ])->assertOk();

        $this->assertSame(0, $third->fresh()->order_index);
        $this->assertSame(1, $first->fresh()->order_index);
        $this->assertSame(2, $second->fresh()->order_index);
    }
}
