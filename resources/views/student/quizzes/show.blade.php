{{--
    Single-page Aluno quiz-taking screen.
    `SubmitQuizAttemptAction` is a single correction pass over the whole
    attempt, so every question is answered and POSTed together in one
    request.

    Nested under `{lesson}` (not a bare `{quiz}`) so
    `EnsureStudentIsEnrolled`'s existing Course-resolution logic (which
    already knows how to resolve a `{lesson}` route parameter) keeps
    working unmodified.

    Expected `StudentQuizController@show` contract (Bucket 2):
      - `$lesson`             the bound Lesson (with `module.course` loaded).
      - `$quiz`               `$lesson->quiz`, with `questions.options`
                              eager-loaded, questions ordered by `order_index`.
      - `$canAttempt`         whether the student may submit a new attempt
                              right now (`allow_retries`/`max_attempts`,
                              counting only completed submissions — see
                              `quizzes-architecture`). The form below is
                              only rendered when this is `true`.
      - `$completedAttempts`  count of the student's own completed
                              (`awaiting_manual_grading`/`graded`) attempts.
      - `$bestScore`          `MAX(score_percentage)` across the student's
                              own `graded` attempts, or `null`.
      - `$pendingAttempt`     the student's own `awaiting_manual_grading`
                              attempt, if any (informational only — does not
                              by itself block a further attempt).

    There is no server-created `QuizAttempt` at this point — per
    `SubmitQuizAttemptAction`'s single-page design (see
    `quizzes-architecture`), the attempt row is only created once the whole
    form is POSTed. The cosmetic countdown below therefore starts counting
    from this page's own render time (`now()`), not from a persisted
    `started_at` — it is purely a client-side visual aid, never the source
    of truth for the server's accept-but-fail time-limit rule.

      - route: `POST route('student.quizzes.submit', $lesson)` posts
        `answers[{question_id}][selected_option_ids][]` (radio for
        single_choice/true_false, checkboxes for multiple_choice) or
        `answers[{question_id}][essay_answer]` (textarea, essay) for
        every question in one request — see `SubmitQuizAttemptRequest`.

    CONTRATO COM `resources/js/modules/QuizTimer.js` — NÃO ALTERAR SEM LER O
    MÓDULO. O cronômetro abaixo é UM ÚNICO elemento que carrega, ao mesmo
    tempo, `[data-quiz-timer]` (seletor de bind), `data-started-at`,
    `data-time-limit-minutes` e o seletor Dusk `@quiz-timer`. O módulo escreve em
    `container.textContent`, portanto o elemento NÃO pode ter filhos
    estruturais. Ele NÃO pode ser `<x-ui.badge>`: `.badge` carrega
    `text-transform: uppercase` (ver `resources/scss/components/_index.scss`),
    e o Selenium lê o texto RENDERIZADO — `StudentQuizAttemptTest` faz
    `waitForText('Tempo esgotado')` e passaria a receber `TEMPO ESGOTADO`.
    Por isso é um `<span>` com as mesmas utilities de borda do badge outline e
    sem nenhuma classe `text-bg-*`, para que o estado "Tempo esgotado" possa
    ser pintado por `classList.add('ds-tone-attention')` sem colidir.
--}}
@extends('layouts.app')

@php
    /** @var \App\Models\Lesson $lesson */
    /** @var \App\Models\Quiz $quiz */
    /** @var bool $canAttempt */
    /** @var int $completedAttempts */
    /** @var float|null $bestScore */
    /** @var \App\Models\QuizAttempt|null $pendingAttempt */
    /** @var bool $showAnswerKey */
    /** @var \App\Models\QuizAttempt|null $latestGradedAttempt */
    $oldAnswers = old('answers', []);
    $answersByQuestionId = $showAnswerKey ? $latestGradedAttempt->answers->keyBy('question_id') : collect();
@endphp

@section('content')
    <div class="d-flex justify-content-center p-6x">
        <div class="w-100 max-w-640">
            <x-layout.page-header
                :breadcrumb="[['label' => 'Meus Cursos', 'url' => route('student.courses.index')], ['label' => $lesson->module->course->title, 'url' => route('classroom.show', $lesson->module->course)], ['label' => $lesson->title, 'url' => route('classroom.lesson', $lesson)], ['label' => $quiz->title]]"
                :kicker="$lesson->module->course->title"
                :title="$quiz->title"
                subtitle="Responda todas as questões e envie o quiz em uma única submissão.">
                @if($canAttempt && $quiz->time_limit_minutes)
                    <x-slot:actions>
                        <span
                            class="border border-secondary text-body fs-5 fw-bold px-2 py-1"
                            data-quiz-timer
                            data-started-at="{{ now()->toIso8601String() }}"
                            data-time-limit-minutes="{{ $quiz->time_limit_minutes }}"
                            dusk="quiz-timer"
                        >--:--</span>
                    </x-slot:actions>
                @endif
            </x-layout.page-header>

            @if($bestScore !== null)
                <x-ui.alert variant="accent" dusk="quiz-best-score">
                    Sua melhor nota até agora: <strong>{{ $bestScore }}%</strong>
                    ({{ $completedAttempts }} {{ $completedAttempts === 1 ? 'tentativa enviada' : 'tentativas enviadas' }}).
                </x-ui.alert>
            @endif

            @if($pendingAttempt)
                <x-ui.alert variant="warning" dusk="quiz-pending-grading">
                    Você tem uma tentativa aguardando correção manual de questões dissertativas.
                </x-ui.alert>
            @endif

            {{--  gabarito só é exibido quando `show_correct_answers`
                 está marcado no Quiz E o aluno já possui uma tentativa
                 corrigida (`status = graded`) para exibir. --}}
            @if($showAnswerKey)
                <x-ui.card title="Gabarito" dusk="quiz-answer-key">
                    @foreach($quiz->questions as $index => $question)
                        @php $answer = $answersByQuestionId->get($question->id); @endphp
                        <div class="mb-4x" dusk="answer-key-question-{{ $question->id }}">
                            <p class="fw-bold mb-2x">{{ $index + 1 }}. {{ $question->question_text }}</p>

                            @if($question->type === 'essay')
                                <x-ui.badge :variant="$answer?->is_correct ? 'accent' : 'accent-2'">
                                    {{ $answer?->is_correct ? 'Correta' : 'Incorreta' }}
                                </x-ui.badge>
                            @else
                                <ul class="mb-0 ps-6x">
                                    @foreach($question->options as $option)
                                        <li @class(['fw-bold text-primary' => $option->is_correct]) dusk="answer-key-option-{{ $option->id }}">
                                            {{ $option->option_text }}
                                            @if($option->is_correct)
                                                (resposta correta)
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </x-ui.card>
            @endif

            @if(!$canAttempt)
                <x-ui.alert variant="accent-2" dusk="quiz-cannot-attempt">
                    @if(!$quiz->allow_retries)
                        Este questionário não permite novas tentativas.
                    @else
                        Você atingiu o número máximo de tentativas ({{ $quiz->max_attempts }}) para este questionário.
                    @endif
                </x-ui.alert>

                <x-ui.button variant="secondary" href="{{ route('classroom.lesson', $lesson) }}" dusk="back-to-lesson">
                    Voltar para a lição
                </x-ui.button>
            @else

            @if($quiz->instructions)
                <x-ui.alert variant="accent">{{ $quiz->instructions }}</x-ui.alert>
            @endif

            <form method="POST" action="{{ route('student.quizzes.submit', $lesson) }}" dusk="quiz-attempt-form">
                @csrf

                @foreach($quiz->questions as $index => $question)
                    @php
                        $questionNumber = $index + 1;
                        $selected = $oldAnswers[$question->id]['selected_option_ids'] ?? [];
                        $essayValue = $oldAnswers[$question->id]['essay_answer'] ?? '';
                        $typeLabels = [
                            'single_choice' => 'Única escolha',
                            'multiple_choice' => 'Múltipla escolha',
                            'true_false' => 'Verdadeiro ou Falso',
                            'essay' => 'Dissertativa',
                        ];
                    @endphp

                    <div class="mb-8x" dusk="quiz-question-{{ $question->id }}">
                        <div class="d-flex justify-content-between align-items-center gap-3x mb-2x">
                            <h6 class="text-body-secondary fw-bold mb-0">
                                Questão {{ $questionNumber }} de {{ $quiz->questions->count() }}
                            </h6>
                            <x-ui.badge variant="outline">{{ $typeLabels[$question->type] ?? $question->type }}</x-ui.badge>
                        </div>

                        <h3 class="h5 mb-4x">
                            {{ $question->question_text }}
                        </h3>

                        @if($question->type === 'essay')
                            <x-ui.input
                                type="textarea"
                                name="answers[{{ $question->id }}][essay_answer]"
                                label="Sua resposta"
                                value="{{ $essayValue }}"
                                dusk="quiz-essay-{{ $question->id }}"
                            />
                        @else
                            <div class="list-group">
                                @foreach($question->options as $option)
                                    @php $isSelected = in_array($option->id, $selected); @endphp
                                    <label @class([
                                        'list-group-item',
                                        'list-group-item-action',
                                        'd-flex align-items-center gap-3x p-4x bg-body-tertiary cursor-pointer',
                                        'border-primary border-2' => $isSelected,
                                    ])>
                                        <input
                                            type="{{ $question->type === 'multiple_choice' ? 'checkbox' : 'radio' }}"
                                            name="answers[{{ $question->id }}][selected_option_ids][]"
                                            value="{{ $option->id }}"
                                            @checked($isSelected)
                                            class="form-check-input flex-shrink-0 mt-0"
                                            dusk="quiz-option-{{ $question->id }}-{{ $option->id }}"
                                        />
                                        <span>{{ $option->option_text }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach

                <div class="d-flex justify-content-end mt-3x">
                    <x-ui.button type="submit" dusk="quiz-attempt-submit">Finalizar Quiz</x-ui.button>
                </div>
            </form>
            @endif
        </div>
    </div>
@endsection
