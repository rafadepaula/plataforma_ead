@extends('layouts.app')

@php
    /** @var \App\Models\Lesson $lesson */
    /** @var \App\Models\Quiz $quiz */
    /** @var \Illuminate\Support\Collection<int, array{attempt: \App\Models\QuizAttempt, number: int}> $numberedAttempts */

    $course = $course ?? $lesson->module->course;

    /**
     * Histórico de tentativas do próprio Aluno (`student.quizzes.history`).
     * `$numberedAttempts` já vem mais-recente-primeiro, cada item com o
     * número cronológico ("Tentativa N": a mais antiga é 1 — numeração por
     * `started_at` asc, sem coluna em `quiz_attempts`). Status:
     * `graded` + `is_passed` → "Aprovada"/"Reprovada",
     * `awaiting_manual_grading` → "Aguardando correção",
     * `in_progress` → "Em andamento". Nota: "—" salvo `graded`
     * (mesmo formato bruto do `result.blade.php`). Attempt `in_progress`
     * não tem link de resultado — resultado é só de attempt finalizada.
     */
    $statusLabel = fn (\App\Models\QuizAttempt $attempt): string => match (true) {
        $attempt->status === 'in_progress' => 'Em andamento',
        $attempt->status === 'awaiting_manual_grading' => 'Aguardando correção',
        $attempt->is_passed => 'Aprovada',
        default => 'Reprovada',
    };

    $statusVariant = fn (\App\Models\QuizAttempt $attempt): string => match (true) {
        $attempt->status === 'in_progress' => 'info',
        $attempt->status === 'awaiting_manual_grading' => 'neutral',
        $attempt->is_passed => 'success',
        default => 'accent-2',
    };
@endphp

@section('content')
    <div class="mx-auto ds-reading-column">
        <x-layout.page-header
            :breadcrumb="[['label' => 'Meus cursos', 'url' => route('student.courses.index')], ['label' => $course->title, 'url' => route('classroom.show', $course)], ['label' => $lesson->title]]"
            :kicker="$course->title . ' / Prova'"
            :title="$quiz->title ?? $lesson->title"
            subtitle="Minhas tentativas" />

        <div dusk="attempt-history">
            @if($numberedAttempts->isEmpty())
                <x-ui.card class="mb-4" surface="white">
                    <p class="mb-0 text-body" dusk="attempt-history-empty">
                        Você ainda não tentou este quiz.
                    </p>
                </x-ui.card>
            @else
                <x-ui.card class="mb-4" surface="white">
                    <ul class="list-group list-group-flush">
                        @foreach($numberedAttempts as $item)
                            @php $attempt = $item['attempt']; @endphp
                            <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-3"
                                dusk="attempt-row-{{ $attempt->id }}">
                                <div>
                                    <span class="fw-semibold">Tentativa {{ $item['number'] }}</span>
                                    <span class="text-body-secondary ms-2">
                                        {{ $attempt->started_at->format('d/m/Y H:i') }}
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <x-ui.badge :variant="$statusVariant($attempt)" :dot="false"
                                                dusk="attempt-status-{{ $attempt->id }}">
                                        {{ $statusLabel($attempt) }}
                                    </x-ui.badge>
                                    <span class="fw-semibold" dusk="attempt-score-{{ $attempt->id }}">
                                        {{ $attempt->status === 'graded' ? $attempt->score_percentage.'%' : '—' }}
                                    </span>
                                    @if($attempt->status === 'graded')
                                        <a href="{{ route('student.quizzes.attempt-result', [$lesson, $attempt]) }}"
                                           class="btn btn-outline-secondary btn-sm"
                                           dusk="attempt-result-link-{{ $attempt->id }}">
                                            Ver resultado
                                        </a>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            <div class="mb-5">
                <x-ui.button variant="secondary" href="{{ route('student.quizzes.show', $lesson) }}" dusk="back-to-quiz">
                    Voltar para a prova
                </x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('classroom.show', $course) }}">
                    Voltar para a sala de aula
                </x-ui.button>
            </div>
        </div>
    </div>
@endsection
