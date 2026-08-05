{{--
    SPEC-08 §2.1 — Gestor grades every pending `essay` `QuizAnswer` of one
    `QuizAttempt` in a single screen/submission.
    `GradeEssayAnswerAction::finalizeGrading()` (Bucket 1) only recomputes
    `score_percentage`/`status` once **every** essay answer on the attempt
    has a non-null `is_correct` — this form requires all of them answered
    before submitting (`required` on every radio group) so a partial grade
    never silently leaves the attempt stuck.

    Expected `EssayGradingController@show`/`@grade` contract (Bucket 2):
      - `$attempt`  the bound `QuizAttempt`
                    (`status = awaiting_manual_grading`), with
                    `quiz.questions.options`, `answers.question`, `user`
                    eager-loaded.
      - route: `POST route('quiz-attempts.grade', $attempt)` posting
        `grades[{answer_id}]` = `'1'`|`'0'` for every essay `QuizAnswer`
        on the attempt — see `GradeEssayAnswerRequest`.
--}}
@extends('layouts.app')

@php
    /** @var \App\Models\QuizAttempt $attempt */
    $answersByQuestionId = $attempt->answers->keyBy('question_id');
@endphp

@section('content')
    <div style="margin-bottom: 20px;">
        <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">
            {{ $attempt->quiz->lesson->module->course->title }}
        </span>
        <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 24px; margin: 4px 0 0;">
            Corrigir Tentativa · {{ $attempt->user->name }}
        </h1>
    </div>

    <div style="margin-bottom: 16px;">
        <x-ui.button variant="secondary" href="{{ route('quiz-attempts.pending') }}" dusk="back-to-pending">Voltar às Correções Pendentes</x-ui.button>
    </div>

    <x-ui.card title="{{ $attempt->quiz->title }}">
        <form method="POST" action="{{ route('quiz-attempts.grade', $attempt) }}" dusk="grade-attempt-form">
            @csrf

            @foreach($attempt->quiz->questions as $index => $question)
                @php $answer = $answersByQuestionId->get($question->id); @endphp

                <div style="margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--color-divider);">
                    <h6 style="color: var(--color-neutral-600); margin: 0 0 8px; font-weight: 800;">Questão {{ $index + 1 }}</h6>
                    <p style="font-weight: 700; margin: 0 0 12px;">{{ $question->question_text }}</p>

                    @if($question->type === 'essay')
                        <div style="padding: 14px 16px; background: var(--color-neutral-200); margin-bottom: 12px; white-space: pre-wrap;" dusk="essay-answer-{{ $question->id }}">
                            {{ $answer?->essay_answer ?? '(sem resposta)' }}
                        </div>

                        {{--
                            `GradeEssayAnswerRequest`/`GradeEssayAnswerAction` expect `grades`
                            as a plain **list** of `{answer_id, is_correct}` entries (not keyed
                            by `answer_id`) — the hidden input carries the id, the radio pair
                            carries the verdict, both sharing the same `$index` slot.
                        --}}
                        <input type="hidden" name="grades[{{ $index }}][answer_id]" value="{{ $answer?->id }}" />

                        <div style="display: flex; gap: 24px;">
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                <input type="radio" name="grades[{{ $index }}][is_correct]" value="1" required
                                       @checked($answer?->is_correct === true) dusk="grade-correct-{{ $answer?->id }}" />
                                Correta
                            </label>
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                <input type="radio" name="grades[{{ $index }}][is_correct]" value="0" required
                                       @checked($answer?->is_correct === false) dusk="grade-incorrect-{{ $answer?->id }}" />
                                Incorreta
                            </label>
                        </div>
                    @else
                        <p style="font-size: 13px; color: var(--color-neutral-600); margin: 0;">
                            Corrigida automaticamente —
                            <x-ui.badge :variant="$answer?->is_correct ? 'accent' : 'accent-2'">
                                {{ $answer?->is_correct ? 'Correta' : 'Incorreta' }}
                            </x-ui.badge>
                        </p>
                    @endif
                </div>
            @endforeach

            <x-ui.button type="submit" dusk="grade-attempt-submit">Salvar Correção</x-ui.button>
        </form>
    </x-ui.card>
@endsection
