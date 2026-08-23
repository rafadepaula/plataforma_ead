{{--
    Gestor grades every pending `essay` `QuizAnswer` of one
    `QuizAttempt` in a single screen/submission.
    `GradeEssayAnswerAction::finalizeGrading()` only recomputes
    `score_percentage`/`status` once **every** essay answer on the attempt
    has a non-null `is_correct` — this form requires all of them answered
    before submitting (`required` on every radio group) so a partial grade
    never silently leaves the attempt stuck.

    `EssayGradingController@show`/`@grade` contract:
      - `$attempt`  the bound `QuizAttempt`
                    (`status = awaiting_manual_grading`), with
                    `quiz.questions.options`, `answers.question`, `user`
                    eager-loaded.
      - route: `POST route('quiz-attempts.grade', $attempt)` posting
        `grades[{index}][answer_id]`/`[is_correct]` as a **list** (not
        keyed by `answer_id`) for every essay `QuizAnswer` on the attempt —
        see `GradeEssayAnswerRequest` and `quizzes-conventions`.

    Material Bootstrap refactor (spec/new_ds/DESIGN.md §4.6): single 760px
    reading column, essay answers in a `--surface-sunken` AnswerSurface,
    two-card VerdictChoice, and a live "X de Y vereditos" progress bar +
    "Pronto para salvar" chip. The `data-grading-*` attributes below are
    read-only hooks for `EssayGrading.js` (Bucket 3) — this view only
    renders the static structure and the value already known on the
    server; the JS keeps it live as the gestor answers each question and
    guards the submit against an incomplete set of verdicts.
--}}
@extends('layouts.app')

@php
    /** @var \App\Models\QuizAttempt $attempt */
    $answersByQuestionId = $attempt->answers->keyBy('question_id');
    $essayQuestions = $attempt->quiz->questions->where('type', 'essay');
    $essayTotal = $essayQuestions->count();
    $answeredCount = $essayQuestions
        ->filter(fn ($question) => $answersByQuestionId->get($question->id)?->is_correct !== null)
        ->count();
    $allGraded = $essayTotal > 0 && $answeredCount === $essayTotal;
@endphp

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Avaliações', 'url' => route('quiz-attempts.pending')], ['label' => 'Correções Pendentes', 'url' => route('quiz-attempts.pending')], ['label' => $attempt->user->name]]"
        :kicker="'Corrigir Tentativa · '.$attempt->quiz->lesson->module->course->title"
        title="{{ $attempt->user->name }}"
        subtitle="Avalie cada questão dissertativa como correta ou incorreta antes de salvar a correção.">
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('quiz-attempts.pending') }}" dusk="back-to-pending">Voltar às Correções Pendentes</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <div class="ds-grading-column mx-auto">
        <div class="ds-grading-progress mb-4x" data-grading-progress data-grading-total="{{ $essayTotal }}" data-grading-answered="{{ $answeredCount }}">
            <div class="flex-grow-1">
                <div class="d-flex justify-content-between mb-2">
                    <span class="ds-caption text-body-secondary">Vereditos dados</span>
                    <span class="ds-caption fw-bold" data-grading-progress-label>{{ $answeredCount }} de {{ $essayTotal }} vereditos</span>
                </div>
                <x-ui.progress
                    :value="$answeredCount"
                    :max="max($essayTotal, 1)"
                    :variant="$allGraded ? 'success' : 'primary'"
                    height="8"
                    label="Vereditos dados"
                />
            </div>
            <x-ui.badge variant="success" :dot="false" data-grading-ready-chip @class(['d-none' => ! $allGraded])>
                Pronto para salvar
            </x-ui.badge>
        </div>

        <x-ui.card title="{{ $attempt->quiz->title }}">
            <div class="d-none" data-grading-alert>
                <x-ui.alert variant="danger" class="mb-4x">
                    <strong class="d-block mb-1">Faltou um veredito</strong>
                    Avalie a questão <span data-grading-alert-question></span> para salvar a correção.
                </x-ui.alert>
            </div>

            <form method="POST" action="{{ route('quiz-attempts.grade', $attempt) }}" dusk="grade-attempt-form" data-grading-form>
                @csrf

                @foreach($attempt->quiz->questions as $index => $question)
                    @php $answer = $answersByQuestionId->get($question->id); @endphp

                    <div class="mb-6x pb-6x border-bottom" @if($question->type === 'essay') data-verdict-question="{{ $question->id }}" @endif>
                        <h6 class="text-body-secondary mb-2x">Questão {{ $index + 1 }}</h6>
                        <p class="fw-bold mb-3x" id="grading-question-prompt-{{ $question->id }}">{{ $question->question_text }}</p>

                        @if($question->type === 'essay')
                            <div class="ds-answer-surface mb-3x" dusk="essay-answer-{{ $question->id }}">
                                @if(filled($answer?->essay_answer))
                                    {{ $answer->essay_answer }}
                                @else
                                    <em>O aluno não respondeu esta questão.</em>
                                @endif
                            </div>

                            {{--
                                `GradeEssayAnswerRequest`/`GradeEssayAnswerAction` expect `grades`
                                as a plain **list** of `{answer_id, is_correct}` entries (not keyed
                                by `answer_id`) — the hidden input carries the id, the radio pair
                                carries the verdict, both sharing the same `$index` slot.
                            --}}
                            <input type="hidden" name="grades[{{ $index }}][answer_id]" value="{{ $answer?->id }}" />

                            <div class="ds-verdict" role="radiogroup" aria-labelledby="grading-question-prompt-{{ $question->id }}">
                                <label class="ds-verdict-option ds-verdict-option--correct @if($answer?->is_correct === true) is-selected @endif" for="grade-{{ $index }}-correct">
                                    <input class="ds-verdict-option-input" type="radio"
                                           id="grade-{{ $index }}-correct"
                                           name="grades[{{ $index }}][is_correct]" value="1" required
                                           data-verdict-input
                                           @checked($answer?->is_correct === true) dusk="grade-correct-{{ $answer?->id }}" />
                                    <span>Correta</span>
                                </label>
                                <label class="ds-verdict-option ds-verdict-option--incorrect @if($answer?->is_correct === false) is-selected @endif" for="grade-{{ $index }}-incorrect">
                                    <input class="ds-verdict-option-input" type="radio"
                                           id="grade-{{ $index }}-incorrect"
                                           name="grades[{{ $index }}][is_correct]" value="0" required
                                           data-verdict-input
                                           @checked($answer?->is_correct === false) dusk="grade-incorrect-{{ $answer?->id }}" />
                                    <span>Incorreta</span>
                                </label>
                            </div>
                        @else
                            <p class="small text-body-secondary mb-0">
                                Corrigida automaticamente
                                <x-ui.badge :variant="$answer?->is_correct ? 'success' : 'neutral'" :dot="false">
                                    {{ $answer?->is_correct ? 'Correta' : 'Incorreta' }}
                                </x-ui.badge>
                            </p>
                        @endif
                    </div>
                @endforeach

                <x-ui.form-actions>
                    <x-ui.button type="submit" dusk="grade-attempt-submit">Salvar Correção</x-ui.button>
                    <x-ui.button variant="ghost" href="{{ route('quiz-attempts.pending') }}">Voltar às Correções Pendentes</x-ui.button>
                </x-ui.form-actions>
            </form>
        </x-ui.card>
    </div>
@endsection
