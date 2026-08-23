{{--
    Gestor edits a Quiz's metadata and manages its
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
    <x-layout.page-header
        :breadcrumb="[['label' => 'Cursos', 'url' => route('courses.index')], ['label' => $quiz->lesson->module->course->title, 'url' => route('courses.modules.index', $quiz->lesson->module->course)], ['label' => $quiz->lesson->module->title, 'url' => route('modules.lessons.index', $quiz->lesson->module)], ['label' => 'Editar Quiz']]"
        :kicker="$quiz->lesson->module->course->title.' / '.$quiz->lesson->module->title.' / '.$quiz->lesson->title"
        title="Editar Quiz"
        subtitle="Atualize as regras da avaliação e gerencie as questões abaixo."
    />

    {{--
        DESIGN.md §4.5: coluna esquerda com as regras da prova, coluna
        direita com a lista arrastável de questões. `col-lg-*` já degrada
        para uma única coluna abaixo do breakpoint `lg` (§4.14 — mobile
        em coluna única, sem grid fixo).
    --}}
    <div class="row g-4 quiz-builder-layout">
        <div class="col-lg-5">
            <x-ui.card>
                <form method="POST" action="{{ route('quizzes.update', $quiz) }}" dusk="quiz-form">
                    @csrf
                    @method('PUT')

                    <x-ui.field-stack class="max-w-640">
                        <x-ui.input name="title" label="Título" required value="{{ $quiz->title }}" dusk="quiz-title-input" />

                        <x-ui.input type="textarea" name="instructions" label="Instruções" value="{{ $quiz->instructions }}" />

                        <x-ui.checkbox
                            name="allow_retries"
                            label="Permitir novas tentativas"
                            :checked="$quiz->allow_retries"
                            dusk="quiz-allow-retries"
                        />

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

                        <x-ui.checkbox
                            name="show_correct_answers"
                            label="Exibir gabarito ao aluno após envio"
                            :checked="$quiz->show_correct_answers"
                            dusk="quiz-show-correct-answers"
                        />

                        <x-ui.input
                            type="number"
                            name="min_score_percentage"
                            label="Nota mínima para aprovação (%)"
                            required
                            value="{{ $quiz->min_score_percentage }}"
                            dusk="quiz-min-score"
                        />
                    </x-ui.field-stack>

                    <x-ui.form-actions>
                        <x-ui.button type="submit" dusk="quiz-submit">Salvar Alterações</x-ui.button>
                        <x-ui.button variant="secondary" href="{{ route('modules.lessons.index', $quiz->lesson->module) }}">Cancelar</x-ui.button>
                    </x-ui.form-actions>
                </form>
            </x-ui.card>
        </div>

        <div class="col-lg-7">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3x mb-3x">
                <h2 class="h5 mb-0">Questões</h2>
                <x-ui.button data-bs-toggle="modal" data-bs-target="#question-create-modal" dusk="new-question">Nova Questão</x-ui.button>
            </div>

            <p class="small text-body-secondary mb-3x">
                Arraste as questões para reordená-las. A nova ordem é salva automaticamente.
            </p>

            @include('quizzes.partials._question-list', ['quiz' => $quiz, 'questions' => $questions])
        </div>
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
