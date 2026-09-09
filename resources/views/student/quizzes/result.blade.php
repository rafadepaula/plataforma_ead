@extends('layouts.app')

@php
    /** @var \App\Models\Lesson $lesson */
    /** @var \App\Models\Quiz $quiz */
    /** @var \App\Models\QuizAttempt|null $resultAttempt */
    /** @var bool|null $showAnswerKey */
    /** @var bool|null $timeExceeded */

    $course = $course ?? $lesson->module->course;
    /**
     * Frente B (ClickUp 86e361vp7) — tela de resultado pós-envio.
     * A Frente A (controller) carrega a última attempt (`graded` ou
     * `awaiting_manual_grading`) com `answers`; os fallbacks abaixo aceitam
     * qualquer um dos nomes que ela passar. `show_correct_answers` e o
     * "tempo excedido" (computado na leitura, nunca persistido) seguem a
     * mesma tolerância.
     */
    $resultAttempt = $attempt ?? ($latestAttempt ?? ($latestGradedAttempt ?? null));
    $isAwaiting = $resultAttempt !== null && $resultAttempt->status === 'awaiting_manual_grading';
    $score = $resultAttempt?->score_percentage;
    $passed = $resultAttempt?->is_passed;

    if (! isset($timeExceeded)) {
        $timeExceeded = (bool) ($quiz->time_limit_minutes
            && ($resultAttempt?->started_at ?? null)
            && ($resultAttempt?->completed_at ?? null)
            && $resultAttempt->started_at->diffInMinutes($resultAttempt->completed_at) > $quiz->time_limit_minutes);
    }

    $showKey = $showAnswerKey ?? (bool) (($quiz->show_correct_answers ?? false) && $resultAttempt !== null);
@endphp

@section('content')
    <div class="mx-auto ds-reading-column">
        <x-layout.page-header
            :breadcrumb="[['label' => 'Meus cursos', 'url' => route('student.courses.index')], ['label' => $course->title, 'url' => route('classroom.show', $course)], ['label' => $lesson->title]]"
            :kicker="$course->title . ' / Prova'"
            :title="$quiz->title ?? $lesson->title"
            subtitle="Resultado da prova" />

        <div dusk="quiz-result">
            @if($resultAttempt === null)
                <x-ui.alert variant="info" class="mb-4">
                    Nenhuma tentativa encontrada para esta prova.
                </x-ui.alert>
            @else
                <x-ui.card class="mb-4" surface="white" title="Seu resultado">
                    @if($isAwaiting)
                        <x-ui.badge variant="outline" :dot="false" class="mb-2">Aguardando correção</x-ui.badge>
                        <p class="mb-0 text-body">
                            Prova enviada. As questões dissertativas aguardam correção manual — sua nota aparece aqui quando a correção terminar.
                        </p>
                    @else
                        <p class="fs-4 fw-bold mb-2" dusk="quiz-result-score">Você acertou {{ $score }}%.</p>

                        @if($passed)
                            <x-ui.alert variant="success" class="mb-0">
                                Aprovado! Você atingiu a nota mínima de {{ $quiz->min_score_percentage }}%.
                            </x-ui.alert>
                        @else
                            <x-ui.alert variant="info" class="mb-0">
                                Você não atingiu a nota mínima de {{ $quiz->min_score_percentage }}%.
                            </x-ui.alert>
                        @endif
                    @endif
                </x-ui.card>

                @if($timeExceeded)
                    <x-ui.alert variant="warning" class="mb-4">
                        Tempo excedido: esta tentativa foi enviada após o limite de {{ $quiz->time_limit_minutes }} minutos e conta como reprovada.
                    </x-ui.alert>
                @endif

                @if($showKey)
                    @include('student.quizzes._answer_key', ['quiz' => $quiz, 'latestAttempt' => $resultAttempt])
                @endif
            @endif

            <div class="mb-5">
                <x-ui.button variant="primary" href="{{ route('classroom.show', $course) }}" dusk="back-to-course">
                    Voltar para o curso
                </x-ui.button>
            </div>
        </div>
    </div>
@endsection
