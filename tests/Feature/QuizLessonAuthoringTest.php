<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The lesson form is THE surface for quiz authoring: a `type = quiz`
 * lesson embeds the full quiz payload (rules + questions + options) in
 * the lesson request itself, persisted transactionally in the same submit
 * by `SaveQuizForLessonAction`. Covers the single-submit persistence, the
 * upsert/delete-missing sync semantics on update, the quiz-title sync to
 * the lesson title, the cleanup when the lesson type switches away from
 * quiz, the validation bounds and the professor reach (`LessonPolicy`
 * governs; the standalone `quizzes.*` screens remain functional).
 */
class QuizLessonAuthoringTest extends TestCase
{
    private function makeCourseAndModule(): array
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->inOrg($org->id)->create();
        $module = Module::factory()->for($course)->create();

        return [$org, $course, $module];
    }

    /**
     * @return array<string, mixed>
     */
    private function quizPayload(array $overrides = []): array
    {
        return [
            'instructions' => 'Leia com atenção.',
            'min_score_percentage' => 70,
            ...$overrides,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function questionPayload(): array
    {
        return [
            [
                'question_text' => 'Qual é a capital do Brasil?',
                'type' => 'single_choice',
                'options' => [
                    ['option_text' => 'Brasília', 'is_correct' => '1'],
                    ['option_text' => 'Rio de Janeiro', 'is_correct' => '0'],
                ],
            ],
            [
                'question_text' => 'Explique o ciclo da água.',
                'type' => 'essay',
            ],
        ];
    }

    public function test_lesson_form_renders_quiz_as_an_enabled_option(): void
    {
        [, , $module] = $this->makeCourseAndModule();

        $this->get(route('modules.lessons.create', $module))
            ->assertOk()
            ->assertSee('<option value="quiz"', false)
            ->assertSee('Quiz', false)
            ->assertDontSee('Quiz (em breve)')
            ->assertDontSee('em uma etapa futura');
    }

    public function test_lesson_form_embeds_the_quiz_authoring_section_on_create(): void
    {
        [, , $module] = $this->makeCourseAndModule();

        $this->get(route('modules.lessons.create', $module))
            ->assertOk()
            ->assertSee('lesson-quiz-section', false)
            ->assertSee('Nota mínima para aprovação (%)', false)
            ->assertSee('dusk="quiz-min-score"', false)
            ->assertSee('dusk="quiz-allow-retries"', false)
            ->assertSee('data-lesson-question-template', false);
    }

    public function test_edit_form_populates_the_builder_from_the_persisted_quiz(): void
    {
        [, , $module] = $this->makeCourseAndModule();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'title' => 'Prova Final']);
        $quiz = $lesson->quiz()->create([
            'title' => 'Prova Final',
            'instructions' => 'Instruções persistidas',
            'min_score_percentage' => 80,
            'allow_retries' => false,
        ]);
        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create([
            'question_text' => 'Questão persistida',
            'order_index' => 0,
        ]);
        QuizOption::factory()->for($question, 'question')->create(['option_text' => 'Opção A', 'is_correct' => true]);

        $this->get(route('lessons.edit', $lesson))
            ->assertOk()
            ->assertSee('Instruções persistidas', false)
            ->assertSee('Questão persistida', false)
            ->assertSee('Opção A', false);
    }

    public function test_storing_a_quiz_lesson_persists_quiz_questions_and_options_in_one_request(): void
    {
        [, , $module] = $this->makeCourseAndModule();

        $this->post(route('modules.lessons.store', $module), [
            'title' => 'Prova do Módulo',
            'type' => 'quiz',
            'quiz' => $this->quizPayload(),
            'questions' => $this->questionPayload(),
        ])->assertRedirect(route('modules.lessons.index', $module));

        $lesson = $module->lessons()->where('title', 'Prova do Módulo')->firstOrFail();
        $quiz = $lesson->quiz;

        $this->assertNotNull($quiz);
        // The quiz title is synced to the lesson title (single title field).
        $this->assertSame('Prova do Módulo', $quiz->title);
        $this->assertSame(70, $quiz->min_score_percentage);
        $this->assertSame('Leia com atenção.', $quiz->instructions);

        $questions = $quiz->questions()->orderBy('order_index')->get();
        $this->assertCount(2, $questions);
        $this->assertSame([0, 1], $questions->pluck('order_index')->all());

        $choice = $questions->firstWhere('type', 'single_choice');
        $this->assertSame('Qual é a capital do Brasil?', $choice->question_text);
        $this->assertCount(2, $choice->options);
        $this->assertSame(1, $choice->options()->where('is_correct', true)->count());

        $essay = $questions->firstWhere('type', 'essay');
        $this->assertCount(0, $essay->options);
    }

    public function test_updating_a_quiz_lesson_without_a_quiz_payload_stays_on_the_normal_redirect(): void
    {
        [, , $module] = $this->makeCourseAndModule();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz']);

        $this->put(route('lessons.update', $lesson), [
            'title' => $lesson->title,
            'type' => 'quiz',
        ])->assertRedirect(route('modules.lessons.index', $module));

        $this->assertFalse($lesson->fresh()->quiz()->exists());
    }

    public function test_updating_a_quiz_lesson_without_a_questions_payload_syncs_meta_but_keeps_questions(): void
    {
        [, , $module] = $this->makeCourseAndModule();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz']);
        $quiz = $lesson->quiz()->create(['title' => 'Prova', 'min_score_percentage' => 70]);
        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        QuizOption::factory()->for($question, 'question')->create(['is_correct' => true]);

        $this->put(route('lessons.update', $lesson), [
            'title' => 'Prova do Módulo Revisada',
            'type' => 'quiz',
            'quiz' => ['min_score_percentage' => 90],
        ])->assertRedirect(route('modules.lessons.index', $module));

        $quiz = $lesson->fresh()->quiz;
        $this->assertSame(90, $quiz->min_score_percentage);
        // Meta sync also re-syncs the title to the (renamed) lesson title.
        $this->assertSame('Prova do Módulo Revisada', $quiz->title);
        $this->assertSame($question->id, $quiz->questions()->first()->id);
        $this->assertCount(1, $quiz->questions()->first()->options()->get());
    }

    public function test_updating_a_quiz_lesson_syncs_questions_with_upsert_and_delete_missing_semantics(): void
    {
        [, , $module] = $this->makeCourseAndModule();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz']);
        $quiz = $lesson->quiz()->create(['title' => 'Prova', 'min_score_percentage' => 70]);

        $keptQuestion = QuizQuestion::factory()->for($quiz)->singleChoice()->create([
            'question_text' => 'Mantida',
            'order_index' => 0,
        ]);
        $keptOption = QuizOption::factory()->for($keptQuestion, 'question')->create(['option_text' => 'Original', 'is_correct' => true]);
        $removedOption = QuizOption::factory()->for($keptQuestion, 'question')->create(['option_text' => 'Removida']);
        $removedQuestion = QuizQuestion::factory()->for($quiz)->essay()->create(['order_index' => 1]);

        $this->put(route('lessons.update', $lesson), [
            'title' => $lesson->title,
            'type' => 'quiz',
            'quiz' => ['min_score_percentage' => 70],
            'questions' => [
                [
                    'id' => $keptQuestion->id,
                    'question_text' => 'Mantida (editada)',
                    'type' => 'multiple_choice',
                    'options' => [
                        ['id' => $keptOption->id, 'option_text' => 'Original editada', 'is_correct' => '1'],
                        ['option_text' => 'Nova opção', 'is_correct' => '1'],
                    ],
                ],
                [
                    'question_text' => 'Questão nova',
                    'type' => 'single_choice',
                    'options' => [
                        ['option_text' => 'Correta', 'is_correct' => '1'],
                        ['option_text' => 'Incorreta', 'is_correct' => '0'],
                    ],
                ],
            ],
        ])->assertRedirect(route('modules.lessons.index', $module));

        $this->assertDatabaseMissing('quiz_questions', ['id' => $removedQuestion->id]);
        $this->assertDatabaseMissing('quiz_options', ['id' => $removedOption->id]);

        $keptQuestion->refresh();
        $this->assertSame('Mantida (editada)', $keptQuestion->question_text);
        $this->assertSame(0, $keptQuestion->order_index);
        $this->assertSame('multiple_choice', $keptQuestion->type);
        $this->assertCount(2, $keptQuestion->options()->get());
        $this->assertSame(2, $keptQuestion->options()->where('is_correct', true)->count());
        $this->assertDatabaseMissing('quiz_options', ['id' => $keptOption->id, 'option_text' => 'Original']);
        $this->assertDatabaseHas('quiz_options', ['id' => $keptOption->id, 'option_text' => 'Original editada']);

        $newQuestion = $quiz->questions()->where('question_text', 'Questão nova')->first();
        $this->assertNotNull($newQuestion);
        $this->assertSame(1, $newQuestion->order_index);
    }

    public function test_updating_with_an_empty_questions_payload_deletes_all_questions(): void
    {
        [, , $module] = $this->makeCourseAndModule();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz']);
        $quiz = $lesson->quiz()->create(['title' => 'Prova', 'min_score_percentage' => 70]);
        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        QuizOption::factory()->for($question, 'question')->create(['is_correct' => true]);

        $this->put(route('lessons.update', $lesson), [
            'title' => $lesson->title,
            'type' => 'quiz',
            'quiz' => ['min_score_percentage' => 70],
            'questions' => [],
        ])->assertRedirect(route('modules.lessons.index', $module));

        $this->assertDatabaseMissing('quiz_questions', ['id' => $question->id]);
        $this->assertDatabaseCount('quiz_options', 0);
    }

    public function test_min_score_percentage_must_be_between_0_and_100(): void
    {
        [, , $module] = $this->makeCourseAndModule();

        foreach ([101, -1] as $invalid) {
            $response = $this->post(route('modules.lessons.store', $module), [
                'title' => 'Prova inválida '.Str::random(4),
                'type' => 'quiz',
                'quiz' => ['min_score_percentage' => $invalid],
            ]);

            $response->assertSessionHasErrors('quiz.min_score_percentage');
        }

        $this->assertDatabaseCount('quizzes', 0);
    }

    public function test_a_choice_question_without_a_correct_option_is_rejected(): void
    {
        [, , $module] = $this->makeCourseAndModule();

        $this->post(route('modules.lessons.store', $module), [
            'title' => 'Prova sem gabarito',
            'type' => 'quiz',
            'quiz' => ['min_score_percentage' => 70],
            'questions' => [
                [
                    'question_text' => 'Sem correta',
                    'type' => 'single_choice',
                    'options' => [
                        ['option_text' => 'A', 'is_correct' => '0'],
                        ['option_text' => 'B', 'is_correct' => '0'],
                    ],
                ],
            ],
        ])->assertSessionHasErrors();

        $this->assertDatabaseCount('quizzes', 0);
        $this->assertDatabaseMissing('lessons', ['title' => 'Prova sem gabarito']);
    }

    public function test_switching_lesson_type_away_from_quiz_deletes_the_quiz_and_its_questions(): void
    {
        [, , $module] = $this->makeCourseAndModule();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz']);
        $quiz = $lesson->quiz()->create(['title' => 'Prova', 'min_score_percentage' => 70]);
        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        QuizOption::factory()->for($question, 'question')->create(['is_correct' => true]);

        $this->put(route('lessons.update', $lesson), [
            'title' => $lesson->title,
            'type' => 'content',
        ])->assertRedirect(route('modules.lessons.index', $module));

        $this->assertDatabaseMissing('quizzes', ['id' => $quiz->id]);
        $this->assertDatabaseMissing('quiz_questions', ['id' => $question->id]);
        $this->assertDatabaseCount('quiz_options', 0);
    }

    public function test_a_question_id_from_another_quiz_is_ignored_not_hijacked(): void
    {
        [, , $module] = $this->makeCourseAndModule();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz']);
        $quiz = $lesson->quiz()->create(['title' => 'Prova', 'min_score_percentage' => 70]);

        $otherLesson = Lesson::factory()->for($module)->create(['type' => 'quiz']);
        $otherQuiz = $otherLesson->quiz()->create(['title' => 'Outra prova', 'min_score_percentage' => 50]);
        $foreignQuestion = QuizQuestion::factory()->for($otherQuiz)->singleChoice()->create();

        $this->put(route('lessons.update', $lesson), [
            'title' => $lesson->title,
            'type' => 'quiz',
            'quiz' => ['min_score_percentage' => 70],
            'questions' => [
                [
                    'id' => $foreignQuestion->id,
                    'question_text' => 'Sequestro',
                    'type' => 'single_choice',
                    'options' => [
                        ['option_text' => 'A', 'is_correct' => '1'],
                        ['option_text' => 'B', 'is_correct' => '0'],
                    ],
                ],
            ],
        ])->assertRedirect(route('modules.lessons.index', $module));

        // The foreign id is ignored: a NEW question is created on the target
        // quiz and the other quiz's question is untouched.
        $foreignQuestion->refresh();
        $this->assertSame($otherQuiz->id, $foreignQuestion->quiz_id);
        $this->assertNotSame('Sequestro', $foreignQuestion->question_text);
        $this->assertDatabaseHas('quiz_questions', ['quiz_id' => $quiz->id, 'question_text' => 'Sequestro']);
    }

    public function test_assigned_professor_can_author_the_embedded_quiz_from_the_lesson_form(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->inOrg($org->id)->create();
        $module = Module::factory()->for($course)->create();
        $professor = User::factory()->professor()->inOrg($org->id)->create();
        $course->professors()->attach($professor->id, ['assigned_by' => $professor->id]);
        $this->actingAs($professor);

        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz']);

        $this->get(route('lessons.edit', $lesson))
            ->assertOk()
            ->assertViewIs('modules.lessons.edit')
            ->assertSee('Nota mínima para aprovação (%)', false);

        $this->put(route('lessons.update', $lesson), [
            'title' => 'Prova do Professor',
            'type' => 'quiz',
            'quiz' => ['min_score_percentage' => 60],
            'questions' => [
                [
                    'question_text' => 'Questão do professor',
                    'type' => 'true_false',
                    'options' => [
                        ['option_text' => 'Verdadeiro', 'is_correct' => '1'],
                        ['option_text' => 'Falso', 'is_correct' => '0'],
                    ],
                ],
            ],
        ])->assertRedirect(route('modules.lessons.index', $module));

        $quiz = $lesson->fresh()->quiz;
        $this->assertNotNull($quiz);
        $this->assertSame('Prova do Professor', $quiz->title);
        $this->assertSame(60, $quiz->min_score_percentage);
        $this->assertCount(1, $quiz->questions()->get());
    }

    public function test_unassigned_professor_cannot_author_the_embedded_quiz(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->inOrg($org->id)->create();
        Module::factory()->for($course)->create();
        $professor = User::factory()->professor()->inOrg($org->id)->create();
        $this->actingAs($professor);

        $otherCourse = Course::factory()->inOrg($org->id)->create();
        $otherModule = Module::factory()->for($otherCourse)->create();
        $lesson = Lesson::factory()->for($otherModule)->create(['type' => 'quiz']);

        $this->get(route('lessons.edit', $lesson))->assertForbidden();
        $this->put(route('lessons.update', $lesson), [
            'title' => 'Invasão',
            'type' => 'quiz',
            'quiz' => ['min_score_percentage' => 50],
        ])->assertForbidden();
        $this->assertFalse($lesson->fresh()->quiz()->exists());
    }

    public function test_the_standalone_quiz_screens_remain_functional(): void
    {
        [, , $module] = $this->makeCourseAndModule();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz']);

        $this->get(route('quizzes.create', $lesson))
            ->assertOk()
            ->assertViewIs('quizzes.create');

        $this->post(route('quizzes.store', $lesson), [
            'title' => 'Prova Final',
            'instructions' => 'Leia com atenção.',
            'min_score_percentage' => 80,
        ])->assertRedirect(route('quizzes.edit', $lesson->fresh()->quiz));

        $quiz = $lesson->fresh()->quiz;
        $this->assertSame('Prova Final', $quiz->title);
        $this->assertSame(80, $quiz->min_score_percentage);

        $this->get(route('quizzes.edit', $quiz))
            ->assertOk()
            ->assertViewIs('quizzes.edit');
    }
}
