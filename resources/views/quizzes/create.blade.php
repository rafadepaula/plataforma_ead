{{--
    Gestor creates the (1:1) Quiz for a Lesson of
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
    <x-layout.page-header
        :breadcrumb="[['label' => 'Cursos', 'url' => route('courses.index')], ['label' => $lesson->module->course->title, 'url' => route('courses.modules.index', $lesson->module->course)], ['label' => $lesson->module->title, 'url' => route('modules.lessons.index', $lesson->module)], ['label' => 'Novo Quiz']]"
        :kicker="$lesson->module->course->title.' / '.$lesson->module->title.' / '.$lesson->title"
        title="Novo Quiz"
        subtitle="Defina as regras da avaliação; as questões são cadastradas depois de salvar."
    />

    <x-ui.card>
        <form method="POST" action="{{ route('quizzes.store', $lesson) }}" dusk="quiz-form">
            @csrf

            {{-- `col-lg-8` substitui o antigo `max-width: 640px`: o Bootstrap não
                 emite utility de max-width, e a coluna do grid é a forma
                 idiomática de limitar a largura de leitura de um formulário. --}}
            <div class="row">
                <x-ui.field-stack class="col-lg-8">
                    <x-ui.input name="title" label="Título" required value="{{ $quiz->title }}" dusk="quiz-title-input" />

                    <x-ui.textarea name="instructions" label="Instruções" value="{{ $quiz->instructions }}" />

                    <x-ui.checkbox name="allow_retries"
                                   label="Permitir novas tentativas"
                                   :checked="$quiz->allow_retries ?? true"
                                   dusk="quiz-allow-retries" />

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

                    <x-ui.checkbox name="show_correct_answers"
                                   label="Exibir gabarito ao aluno após envio"
                                   :checked="$quiz->show_correct_answers ?? false"
                                   dusk="quiz-show-correct-answers" />

                    <x-ui.input
                        type="number"
                        name="min_score_percentage"
                        label="Nota mínima para aprovação (%)"
                        required
                        value="{{ $quiz->min_score_percentage ?? 70 }}"
                        dusk="quiz-min-score"
                    />
                </x-ui.field-stack>
            </div>

            <x-ui.form-actions>
                <x-ui.button type="submit" dusk="quiz-submit">Criar Quiz</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('modules.lessons.index', $lesson->module) }}">Cancelar</x-ui.button>
            </x-ui.form-actions>
        </form>
    </x-ui.card>
@endsection
