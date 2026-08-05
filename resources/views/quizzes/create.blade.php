{{--
    SPEC-08 RF08 — Gestor creates the (1:1) Quiz for a Lesson of
    `type = quiz`. `quizzes.lesson_id` is UNIQUE — a Lesson that already
    has a Quiz never reaches this screen (`QuizController::create()` is
    expected to redirect back with a 422-style error instead).

    Expected `QuizController@create`/`@store` contract (Bucket 2):
      - `$lesson`  the bound Lesson (with `module.course` loaded), used
                   purely for the breadcrumb/kicker and the cancel link.
      - route: `POST route('quizzes.store', $lesson)` (nested under the
        Lesson, mirroring `courses.modules.store`/`modules.lessons.store`).
--}}
@extends('layouts.app')

@php
    /** @var \App\Models\Lesson $lesson */
    $quiz = $quiz ?? new \App\Models\Quiz;
@endphp

@section('content')
    <x-ui.card title="Novo Quiz" kicker="{{ $lesson->module->course->title }} / {{ $lesson->module->title }} / {{ $lesson->title }}">
        <form method="POST" action="{{ route('quizzes.store', $lesson) }}" dusk="quiz-form">
            @csrf

            <div style="display: flex; flex-direction: column; gap: 20px; max-width: 640px;">
                <x-ui.input name="title" label="Título" required value="{{ $quiz->title }}" dusk="quiz-title-input" />

                <x-ui.input type="textarea" name="instructions" label="Instruções" value="{{ $quiz->instructions }}" />

                <div class="field" style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="allow_retries" name="allow_retries" value="1"
                           @checked(old('allow_retries', $quiz->allow_retries ?? true))
                           style="width: 16px; height: 16px;" dusk="quiz-allow-retries" />
                    <label for="allow_retries" style="font-size: 13px; font-weight: 600; color: var(--color-text);">Permitir novas tentativas</label>
                </div>

                <x-ui.input
                    type="number"
                    name="max_attempts"
                    label="Máximo de tentativas"
                    hint="Deixe em branco para tentativas ilimitadas (conta apenas tentativas enviadas, não em andamento)."
                    value="{{ $quiz->max_attempts }}"
                    dusk="quiz-max-attempts"
                />

                <x-ui.input
                    type="number"
                    name="time_limit_minutes"
                    label="Limite de tempo (minutos)"
                    hint="Deixe em branco para sem limite. Envios após o limite são aceitos, mas marcados como reprovados."
                    value="{{ $quiz->time_limit_minutes }}"
                    dusk="quiz-time-limit"
                />

                <div class="field" style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="show_correct_answers" name="show_correct_answers" value="1"
                           @checked(old('show_correct_answers', $quiz->show_correct_answers ?? false))
                           style="width: 16px; height: 16px;" dusk="quiz-show-correct-answers" />
                    <label for="show_correct_answers" style="font-size: 13px; font-weight: 600; color: var(--color-text);">Exibir gabarito ao aluno após envio</label>
                </div>

                <x-ui.input
                    type="number"
                    name="min_score_percentage"
                    label="Nota mínima para aprovação (%)"
                    required
                    value="{{ $quiz->min_score_percentage ?? 70 }}"
                    dusk="quiz-min-score"
                />
            </div>

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <x-ui.button type="submit" dusk="quiz-submit">Criar Quiz</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('modules.lessons.index', $lesson->module) }}">Cancelar</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
