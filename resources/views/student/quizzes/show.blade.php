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

    $course = $course ?? $lesson->module->course;
    $hasPendingGrading = isset($hasPendingGrading) ? $hasPendingGrading : ($pendingAttempt !== null);
    $latestAttempt = $latestAttempt ?? $latestGradedAttempt;
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
            @if($quiz->time_limit_minutes && $canAttempt)
                <x-slot:actions>
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-2 bg-body-secondary rounded-pill fw-semibold">
                        <x-ui.icon name="clock" size="18" />
                        <span data-quiz-timer data-started-at="{{ now()->toIso8601String() }}" data-time-limit-minutes="{{ $quiz->time_limit_minutes }}" dusk="quiz-timer"></span>
                    </div>
                </x-slot:actions>
            @endif
        </x-layout.page-header>

        {{-- STRICT BANNER HIERARCHY --}}

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

        <form method="POST" action="{{ route('student.quizzes.submit', $lesson) }}" dusk="quiz-attempt-form">
            @csrf
            <input type="hidden" name="started_at" value="{{ now()->toIso8601String() }}">

            @foreach($quiz->questions as $index => $question)
                @php
                    $selected = $oldAnswers[$question->id]['selected_option_ids'] ?? [];
                    $essayValue = $oldAnswers[$question->id]['essay_answer'] ?? '';
                @endphp

                <x-ui.card class="mb-4" dusk="quiz-question-{{ $question->id }}" surface="body">
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

            <div class="d-flex justify-content-end mt-4 mb-5">
                <x-ui.button type="submit" variant="primary" icon="check" dusk="quiz-attempt-submit">
                    Finalizar prova
                </x-ui.button>
            </div>
        </form>
        @endif
    </div>
@endsection
