<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Database\Seeder;

class QuizSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $quizLesson = Lesson::where('type', 'quiz')->first();
        if (! $quizLesson) {
            return;
        }

        Quiz::withoutEvents(function () use ($quizLesson): void {
            QuizAttempt::withoutEvents(function () use ($quizLesson): void {
                QuizAnswer::withoutEvents(function () use ($quizLesson): void {
                    // 1. Create Quiz
                    $quiz = Quiz::firstOrCreate(
                        ['lesson_id' => $quizLesson->id],
                        [
                            'title' => 'Avaliação de Conhecimentos EAD',
                            'instructions' => 'Responda atentamente a todas as questões antes de finalizar a prova.',
                            'allow_retries' => true,
                            'max_attempts' => 3,
                            'time_limit_minutes' => 45,
                            'show_correct_answers' => true,
                            'min_score_percentage' => 70,
                        ]
                    );

                    // 2. Questions
                    $q1 = QuizQuestion::firstOrCreate(
                        [
                            'quiz_id' => $quiz->id,
                            'question_text' => 'Qual é o principal benefício da arquitetura Multitenant no Laravel?',
                        ],
                        [
                            'type' => 'single_choice',
                            'order_index' => 1,
                        ]
                    );

                    $opt1 = QuizOption::firstOrCreate(
                        [
                            'question_id' => $q1->id,
                            'option_text' => 'Isolamento de dados por organização com código compartilhado',
                        ],
                        ['is_correct' => true]
                    );
                    QuizOption::firstOrCreate(
                        [
                            'question_id' => $q1->id,
                            'option_text' => 'Necessidade de criar um servidor dedicado por usuário',
                        ],
                        ['is_correct' => false]
                    );
                    QuizOption::firstOrCreate(
                        [
                            'question_id' => $q1->id,
                            'option_text' => 'Desativação completa de rotas autenticadas',
                        ],
                        ['is_correct' => false]
                    );

                    $q2 = QuizQuestion::firstOrCreate(
                        [
                            'quiz_id' => $quiz->id,
                            'question_text' => 'O Laravel Sail utiliza containers Docker para o ambiente de desenvolvimento local.',
                        ],
                        [
                            'type' => 'true_false',
                            'order_index' => 2,
                        ]
                    );

                    $optTrue = QuizOption::firstOrCreate(
                        [
                            'question_id' => $q2->id,
                            'option_text' => 'Verdadeiro',
                        ],
                        ['is_correct' => true]
                    );
                    QuizOption::firstOrCreate(
                        [
                            'question_id' => $q2->id,
                            'option_text' => 'Falso',
                        ],
                        ['is_correct' => false]
                    );

                    $q3 = QuizQuestion::firstOrCreate(
                        [
                            'quiz_id' => $quiz->id,
                            'question_text' => 'Explique brevemente por que a idempotência é indispensável em Database Seeders.',
                        ],
                        [
                            'type' => 'essay',
                            'order_index' => 3,
                        ]
                    );

                    // 3. Quiz Attempt & Answers
                    $aluno = User::where('email', 'aluno.alpha@plataforma.com')->first();
                    $gestor = User::where('email', 'gestor.acme@plataforma.com')->first();

                    if ($aluno) {
                        $attempt = QuizAttempt::firstOrCreate(
                            [
                                'quiz_id' => $quiz->id,
                                'user_id' => $aluno->id,
                            ],
                            [
                                'score_percentage' => 100.00,
                                'is_passed' => true,
                                'status' => 'graded',
                                'started_at' => now()->subHours(2),
                                'completed_at' => now()->subHour(),
                            ]
                        );

                        // Answer Q1
                        QuizAnswer::firstOrCreate(
                            [
                                'attempt_id' => $attempt->id,
                                'question_id' => $q1->id,
                            ],
                            [
                                'selected_option_ids' => [$opt1->id],
                                'essay_answer' => null,
                                'is_correct' => true,
                                'graded_by' => null,
                                'graded_at' => now()->subHour(),
                            ]
                        );

                        // Answer Q2
                        QuizAnswer::firstOrCreate(
                            [
                                'attempt_id' => $attempt->id,
                                'question_id' => $q2->id,
                            ],
                            [
                                'selected_option_ids' => [$optTrue->id],
                                'essay_answer' => null,
                                'is_correct' => true,
                                'graded_by' => null,
                                'graded_at' => now()->subHour(),
                            ]
                        );

                        // Answer Q3
                        QuizAnswer::firstOrCreate(
                            [
                                'attempt_id' => $attempt->id,
                                'question_id' => $q3->id,
                            ],
                            [
                                'selected_option_ids' => null,
                                'essay_answer' => 'A idempotência garante que reexecutar php artisan db:seed não cause erros de duplicidade nem crie registros duplicados.',
                                'is_correct' => true,
                                'graded_by' => $gestor?->id,
                                'graded_at' => now()->subHour(),
                            ]
                        );
                    }
                });
            });
        });
    }
}
