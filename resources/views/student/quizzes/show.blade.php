@extends('layouts.app')

@php
    /** @var \App\Models\Lesson $lesson */
    /** @var \App\Models\Quiz $quiz */
    /** @var bool $canAttempt */
    /** @var \Carbon\CarbonInterface|null $attemptStartedAt */
    /** @var int $completedAttempts */
    /** @var float|null $bestScore */
    /** @var \App\Models\QuizAttempt|null $pendingAttempt */
    /** @var \App\Models\QuizAttempt|null $expiredAttempt */
    /** @var bool $showAnswerKey */
    /** @var \App\Models\QuizAttempt|null $latestGradedAttempt */

    $course = $course ?? $lesson->module->course;
    $hasPendingGrading = isset($hasPendingGrading) ? $hasPendingGrading : ($pendingAttempt !== null);
    $oldAnswers = old('answers', []);
    $typeLabels = [
        'single_choice' => 'Escolha única',
        'multiple_choice' => 'Múltipla escolha',
        'true_false' => 'Verdadeiro ou Falso',
        'essay' => 'Dissertativa',
    ];
@endphp

@section('content')
    <div class="mx-auto ds-reading-column">
        <x-layout.page-header
            :breadcrumb="[['label' => 'Meus cursos', 'url' => route('student.courses.index')], ['label' => $course->title, 'url' => route('classroom.show', $course)], ['label' => $lesson->title]]"
            :kicker="$course->title . ' / Prova'"
            :title="$quiz->title ?? $lesson->title"
            :subtitle="'São ' . $quiz->questions->count() . ' ' . ($quiz->questions->count() === 1 ? 'questão' : 'questões') . '.'">
            @if($quiz->time_limit_minutes && $canAttempt && $attemptStartedAt !== null)
                <x-slot:actions>
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-2 bg-body-secondary rounded-pill fw-semibold">
                        <x-ui.icon name="clock" size="18" />
                        <span data-quiz-timer data-started-at="{{ $attemptStartedAt->toIso8601String() }}" data-time-limit-minutes="{{ $quiz->time_limit_minutes }}" dusk="quiz-timer"></span>
                    </div>
                </x-slot:actions>
            @endif
        </x-layout.page-header>

        {{-- STRICT BANNER HIERARCHY --}}

        {{-- 0. Tentativa anterior encerrada por estouro de tempo --}}
        @if(($expiredAttempt ?? null) !== null)
            <x-ui.alert variant="warning" dusk="quiz-expired-attempt" class="mb-4">
                O tempo da sua tentativa anterior se esgotou antes do envio. Ela foi encerrada e registrada como reprovada, e conta no seu total de tentativas.
            </x-ui.alert>
        @endif

        {{-- 1. Melhor nota obtida --}}
        @if($bestScore !== null)
            <x-ui.alert variant="info" dusk="quiz-best-score" class="mb-4">
                Sua melhor nota nesta prova: {{ $bestScore }}%
            </x-ui.alert>
        @endif

        {{-- 2. Aguardando correção manual --}}
        @if($hasPendingGrading)
            <x-ui.alert variant="warning" dusk="quiz-pending-grading" class="mb-4">
                Você possui uma tentativa aguardando correção manual.
            </x-ui.alert>
        @endif

        {{-- 3. Gabarito da tentativa anterior corrigida --}}
        @if($showAnswerKey && $latestGradedAttempt)
            @include('student.quizzes._answer_key', ['quiz' => $quiz, 'latestAttempt' => $latestGradedAttempt])
        @endif

        {{-- 4. Bloqueio / Tentativas esgotadas --}}
        @if(! $canAttempt)
            <x-ui.alert variant="info" dusk="quiz-cannot-attempt" class="mb-4">
                @if(! $quiz->allow_retries)
                    Esta prova não permite novas tentativas.
                @else
                    Você atingiu o número máximo de tentativas ({{ $quiz->max_attempts }}) para esta prova.
                @endif
            </x-ui.alert>

            <div class="mb-4">
                <x-ui.button variant="secondary" href="{{ route('classroom.show', $course) }}" dusk="back-to-lesson">
                    Voltar para a sala de aula
                </x-ui.button>
            </div>
        @else

        {{-- 5. Formulário de realização da prova --}}
        @if(filled($quiz->instructions))
            <x-ui.card class="mb-4" surface="body">
                <div class="d-flex align-items-center gap-2 mb-2 text-primary fw-semibold">
                    <x-ui.icon name="info" size="18" />
                    <span>Antes de começar</span>
                </div>
                <div class="text-body text-prewrap">{{ $quiz->instructions }}</div>
            </x-ui.card>
        @endif

        <form id="quiz-attempt-form" method="POST" action="{{ route('student.quizzes.submit', $lesson) }}" dusk="quiz-attempt-form">
            @csrf

            @foreach($quiz->questions as $index => $question)
                @php
                    $selected = $oldAnswers[$question->id]['selected_option_ids'] ?? [];
                    $essayValue = data_get(old('answers'), $question->id.'.essay_answer', '');
                @endphp

                <x-ui.card class="mb-4" data-question-id="{{ $question->id }}" dusk="quiz-question-{{ $question->id }}" surface="body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="ds-overline text-body-secondary">
                            Questão {{ $loop->iteration }} de {{ $quiz->questions->count() }}
                        </span>
                        <x-ui.badge variant="outline" :dot="false">
                            {{ $typeLabels[$question->type] ?? $question->type }}
                        </x-ui.badge>
                    </div>

                    <h3 class="h5 fw-bold mb-3">
                        {{ $question->question_text }}
                    </h3>

                    @if($question->type === 'multiple_choice')
                        <p class="form-text text-body-secondary mb-3">Selecione todas as respostas que se aplicam.</p>
                    @endif

                    @if($question->type === 'essay')
                        @include('student.quizzes._essay', ['question' => $question, 'essayValue' => $essayValue])
                    @else
                        <div class="d-flex flex-column gap-2">
                            @foreach($question->options as $option)
                                @include('student.quizzes._option', ['question' => $question, 'option' => $option, 'selected' => $selected])
                            @endforeach
                        </div>
                    @endif
                </x-ui.card>
            @endforeach

            <x-ui.form-actions align="end" class="mb-5">
                <x-ui.button type="button"
                             variant="primary"
                             icon="check"
                             data-bs-toggle="modal"
                             data-bs-target="#submit-attempt-modal"
                             dusk="quiz-attempt-submit">
                    Finalizar prova
                </x-ui.button>
            </x-ui.form-actions>
        </form>

        {{-- Confirmação de envio: fora do `<form>` (HTML não aninha formulários),
             submete a prova pelo atributo `form="quiz-attempt-form"`. --}}
        <x-ui.confirm-modal
            id="submit-attempt-modal"
            title="Finalizar prova"
            form="quiz-attempt-form"
            variant="primary"
            confirm-label="Finalizar prova"
            cancel-label="Voltar para a prova"
            confirm-dusk="quiz-attempt-confirm"
            data-quiz-confirm-modal
        >
            <p class="mb-2"><span data-unanswered-count>0</span> de <span data-total-count>{{ $quiz->questions->count() }}</span> questões estão sem resposta.</p>
            <p class="mb-2 text-body-secondary" data-unanswered-message>Todas as questões foram respondidas.</p>
            <p class="mb-0">Depois de finalizar, não será possível alterar suas respostas.</p>
            @if($quiz->max_attempts !== null)
                <p class="mb-0">Esta é a tentativa {{ $completedAttempts + 1 }} de {{ $quiz->max_attempts }}.</p>
            @endif
        </x-ui.confirm-modal>
        @endif
    </div>
@endsection
