{{--
    SPEC-08 RF08 — Gestor edits a Quiz's metadata and manages its
    Questions/Options on a single screen (no dedicated
    `quiz-questions/create|edit` full pages exist — Questions are
    authored via the modals below, each wrapping
    `quizzes.partials._question-form`).

    Expected `QuizController@edit`/`@update` + `QuizQuestionController`
    contract (Bucket 2):
      - `$quiz`      the bound Quiz, with `lesson.module.course` loaded.
      - `$questions` `$quiz->questions()->with('options')->orderBy('order_index')->get()`.
      - routes: `PUT route('quizzes.update', $quiz)`,
        `POST route('quiz-questions.store', $quiz)`,
        `PUT route('quiz-questions.update', $question)`,
        `DELETE route('quiz-questions.destroy', $question)`,
        `POST route('quiz-questions.reorder', $quiz)` (same
        `{ ordered_ids: [...] }` shape as `modules.reorder`/`lessons.reorder`,
        consumed by the existing `ModuleReorder.js` — no new reorder JS
        needed here, see `quizzes-conventions`).
--}}
@extends('layouts.app')

@php
    /** @var \App\Models\Quiz $quiz */
    /** @var \Illuminate\Support\Collection<int, \App\Models\QuizQuestion> $questions */
    $questions = $questions ?? collect();
@endphp

@section('content')
    <x-ui.card title="Editar Quiz" kicker="{{ $quiz->lesson->module->course->title }} / {{ $quiz->lesson->module->title }} / {{ $quiz->lesson->title }}">
        <form method="POST" action="{{ route('quizzes.update', $quiz) }}" dusk="quiz-form">
            @csrf
            @method('PUT')

            <div style="display: flex; flex-direction: column; gap: 20px; max-width: 640px;">
                <x-ui.input name="title" label="Título" required value="{{ $quiz->title }}" dusk="quiz-title-input" />

                <x-ui.input type="textarea" name="instructions" label="Instruções" value="{{ $quiz->instructions }}" />

                <div class="field" style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="allow_retries" name="allow_retries" value="1"
                           @checked(old('allow_retries', $quiz->allow_retries)) style="width: 16px; height: 16px;" dusk="quiz-allow-retries" />
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
                           @checked(old('show_correct_answers', $quiz->show_correct_answers)) style="width: 16px; height: 16px;" dusk="quiz-show-correct-answers" />
                    <label for="show_correct_answers" style="font-size: 13px; font-weight: 600; color: var(--color-text);">Exibir gabarito ao aluno após envio</label>
                </div>

                <x-ui.input
                    type="number"
                    name="min_score_percentage"
                    label="Nota mínima para aprovação (%)"
                    required
                    value="{{ $quiz->min_score_percentage }}"
                    dusk="quiz-min-score"
                />
            </div>

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <x-ui.button type="submit" dusk="quiz-submit">Salvar Alterações</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('modules.lessons.index', $quiz->lesson->module) }}">Cancelar</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <div style="margin-top: 24px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
            <h2 style="font-family: var(--font-heading); font-weight: 800; font-size: 18px; margin: 0;">Questões</h2>
            <x-ui.button data-modal-target="question-create-modal" dusk="new-question">Nova Questão</x-ui.button>
        </div>

        <p style="font-size: 12px; color: var(--color-neutral-600); margin-bottom: 12px;">
            Arraste as questões para reordená-las. A nova ordem é salva automaticamente.
        </p>

        @include('quizzes.partials._question-list', ['quiz' => $quiz, 'questions' => $questions])
    </div>

    {{-- "Nova Questão" modal — always a blank `QuizQuestion` instance. --}}
    <x-ui.modal id="question-create-modal" title="Nova Questão" size="lg">
        @include('quizzes.partials._question-form', [
            'quiz' => $quiz,
            'question' => new \App\Models\QuizQuestion(['type' => 'single_choice']),
            'formSuffix' => 'create',
            'action' => route('quiz-questions.store', $quiz),
            'method' => 'POST',
        ])
    </x-ui.modal>

    {{-- One "Editar Questão" modal per existing question, each pre-filled server-side. --}}
    @foreach($questions as $question)
        <x-ui.modal id="question-edit-modal-{{ $question->id }}" title="Editar Questão" size="lg">
            @include('quizzes.partials._question-form', [
                'quiz' => $quiz,
                'question' => $question,
                'formSuffix' => 'edit-'.$question->id,
                'action' => route('quiz-questions.update', $question),
                'method' => 'PUT',
            ])
        </x-ui.modal>
    @endforeach
@endsection
