{{--
    SPEC-08 RF09 — single-page Aluno quiz-taking screen. Visual reference
    only from `spec/docs/mockups/05-quiz-avaliacao.md` (its per-question,
    multi-page/`page=` navigation is NOT followed here — SPEC-08 §2's
    `SubmitQuizAttemptAction` is a single correction pass over the whole
    attempt, so every question is answered and POSTed together in one
    request; see this bucket's plan "edge case" note).

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
    <div style="display: flex; justify-content: center; padding: 24px;">
        <div style="width: 680px; max-width: 100%;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">
                        {{ $lesson->module->course->title }}
                    </span>
                    <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 22px; margin: 4px 0 0;">{{ $quiz->title }}</h1>
                </div>

                @if($canAttempt && $quiz->time_limit_minutes)
                    <div
                        data-quiz-timer
                        data-started-at="{{ now()->toIso8601String() }}"
                        data-time-limit-minutes="{{ $quiz->time_limit_minutes }}"
                        dusk="quiz-timer"
                        style="font-family: var(--font-heading); font-weight: 800; font-size: 18px; color: var(--color-text);"
                    >--:--</div>
                @endif
            </div>

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

            {{-- RN04 — gabarito só é exibido quando `show_correct_answers`
                 está marcado no Quiz E o aluno já possui uma tentativa
                 corrigida (`status = graded`) para exibir. --}}
            @if($showAnswerKey)
                <x-ui.card title="Gabarito" dusk="quiz-answer-key">
                    @foreach($quiz->questions as $index => $question)
                        @php $answer = $answersByQuestionId->get($question->id); @endphp
                        <div style="margin-bottom: 16px;" dusk="answer-key-question-{{ $question->id }}">
                            <p style="font-weight: 700; margin: 0 0 8px;">{{ $index + 1 }}. {{ $question->question_text }}</p>

                            @if($question->type === 'essay')
                                <x-ui.badge :variant="$answer?->is_correct ? 'accent' : 'accent-2'">
                                    {{ $answer?->is_correct ? 'Correta' : 'Incorreta' }}
                                </x-ui.badge>
                            @else
                                <ul style="margin: 0; padding-left: 20px;">
                                    @foreach($question->options as $option)
                                        <li style="{{ $option->is_correct ? 'font-weight: 700; color: var(--color-accent);' : '' }}" dusk="answer-key-option-{{ $option->id }}">
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

                    <div style="margin-bottom: 30px;" dusk="quiz-question-{{ $question->id }}">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <h6 style="color: var(--color-neutral-600); margin: 0; font-weight: 800;">
                                Questão {{ $questionNumber }} de {{ $quiz->questions->count() }}
                            </h6>
                            <x-ui.badge variant="outline">{{ $typeLabels[$question->type] ?? $question->type }}</x-ui.badge>
                        </div>

                        <h3 style="font-family: var(--font-heading); font-weight: 800; font-size: 18px; margin-bottom: 16px; color: var(--color-text);">
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
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                @foreach($question->options as $option)
                                    @php $isSelected = in_array($option->id, $selected); @endphp
                                    <label
                                        style="display: flex; align-items: center; gap: 12px; padding: 14px 16px; border: 1px solid {{ $isSelected ? 'var(--color-accent)' : 'var(--color-divider)' }}; background: {{ $isSelected ? 'var(--color-accent-100)' : 'var(--color-surface)' }}; font-size: 14px; cursor: pointer;"
                                    >
                                        <input
                                            type="{{ $question->type === 'multiple_choice' ? 'checkbox' : 'radio' }}"
                                            name="answers[{{ $question->id }}][selected_option_ids][]"
                                            value="{{ $option->id }}"
                                            @checked($isSelected)
                                            style="accent-color: var(--color-accent);"
                                            dusk="quiz-option-{{ $question->id }}-{{ $option->id }}"
                                        />
                                        <span>{{ $option->option_text }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach

                <div style="display: flex; justify-content: flex-end; margin-top: 12px;">
                    <x-ui.button type="submit" dusk="quiz-attempt-submit">Finalizar Quiz</x-ui.button>
                </div>
            </form>
            @endif
        </div>
    </div>
@endsection
