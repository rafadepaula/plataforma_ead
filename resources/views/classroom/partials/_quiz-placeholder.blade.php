@php
    $quiz = $lesson->quiz;
    /** Contagem vem pré-carregada pelo controller (`withCount`): nada de query na view. */
    $questionsCount = match (true) {
        $quiz === null => 0,
        $quiz->relationLoaded('questions') => $quiz->questions->count(),
        default => (int) ($quiz->questions_count ?? 0),
    };
@endphp

@if($quiz && $questionsCount > 0)
    @php
        /** O resumo de questões/tempo é sempre exibido; as instruções, quando houver, complementam. */
        $questionsLabel = $questionsCount.' '.($questionsCount === 1 ? 'questão' : 'questões');
        $timeLabel = $quiz->time_limit_minutes ? ' e '.$quiz->time_limit_minutes.' minutos' : '';
    @endphp
    <div class="ds-quiz-placeholder" dusk="quiz-placeholder">
        <span class="ds-quiz-placeholder-icon">
            <x-ui.icon name="clipboard" size="30" />
        </span>

        <h2 class="ds-quiz-placeholder-title">Esta aula é uma prova</h2>

        <p class="ds-quiz-placeholder-text">
            São {{ $questionsLabel }}{{ $timeLabel }}.
        </p>

        @if(filled($quiz->instructions))
            <p class="ds-quiz-placeholder-text">{{ Str::limit($quiz->instructions, 140) }}</p>
        @endif

        <div>
            <x-ui.button variant="primary" href="{{ route('student.quizzes.show', $lesson) }}" dusk="start-quiz">
                Iniciar prova
            </x-ui.button>
        </div>
    </div>
@else
    <div class="ds-quiz-placeholder" dusk="quiz-placeholder">
        <span class="ds-quiz-placeholder-icon">
            <x-ui.icon name="info" size="30" />
        </span>

        <h2 class="ds-quiz-placeholder-title">Prova em preparação</h2>

        <p class="ds-quiz-placeholder-text">
            O responsável pelo curso ainda está montando esta avaliação. Ela aparece aqui quando estiver pronta.
        </p>
    </div>
@endif
