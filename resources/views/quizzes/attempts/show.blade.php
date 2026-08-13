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
    <x-layout.page-header
        :kicker="$attempt->quiz->lesson->module->course->title"
        title="Corrigir Tentativa · {{ $attempt->user->name }}" />

    <div class="mb-4x">
        <x-ui.button variant="secondary" href="{{ route('quiz-attempts.pending') }}" dusk="back-to-pending">Voltar às Correções Pendentes</x-ui.button>
    </div>

    <x-ui.card title="{{ $attempt->quiz->title }}">
        <form method="POST" action="{{ route('quiz-attempts.grade', $attempt) }}" dusk="grade-attempt-form">
            @csrf

            @foreach($attempt->quiz->questions as $index => $question)
                @php $answer = $answersByQuestionId->get($question->id); @endphp

                <div class="mb-6x pb-6x border-bottom">
                    <h6 class="text-body-secondary mb-2x">Questão {{ $index + 1 }}</h6>
                    <p class="fw-bold mb-3x">{{ $question->question_text }}</p>

                    @if($question->type === 'essay')
                        <div class="bg-body-tertiary p-4x mb-3x text-prewrap" dusk="essay-answer-{{ $question->id }}">
                            {{ $answer?->essay_answer ?? '(sem resposta)' }}
                        </div>

                        {{--
                            `GradeEssayAnswerRequest`/`GradeEssayAnswerAction` expect `grades`
                            as a plain **list** of `{answer_id, is_correct}` entries (not keyed
                            by `answer_id`) — the hidden input carries the id, the radio pair
                            carries the verdict, both sharing the same `$index` slot.
                        --}}
                        <input type="hidden" name="grades[{{ $index }}][answer_id]" value="{{ $answer?->id }}" />

                        <div class="d-flex gap-6x">
                            <div class="form-check">
                                <input class="form-check-input cursor-pointer" type="radio"
                                       id="grade-{{ $index }}-correct"
                                       name="grades[{{ $index }}][is_correct]" value="1" required
                                       @checked($answer?->is_correct === true) dusk="grade-correct-{{ $answer?->id }}" />
                                <label class="form-check-label cursor-pointer" for="grade-{{ $index }}-correct">
                                    Correta
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input cursor-pointer" type="radio"
                                       id="grade-{{ $index }}-incorrect"
                                       name="grades[{{ $index }}][is_correct]" value="0" required
                                       @checked($answer?->is_correct === false) dusk="grade-incorrect-{{ $answer?->id }}" />
                                <label class="form-check-label cursor-pointer" for="grade-{{ $index }}-incorrect">
                                    Incorreta
                                </label>
                            </div>
                        </div>
                    @else
                        <p class="small text-body-secondary mb-0">
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
