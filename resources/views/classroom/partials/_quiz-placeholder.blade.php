@php
    $quiz = $lesson->quiz;
    $questionsCount = $quiz ? ($quiz->questions_count ?? $quiz->questions()->count()) : 0;
@endphp

@if($quiz && $questionsCount > 0)
    <div class="p-5 text-center border border-dashed rounded-4" dusk="quiz-placeholder">
        <div class="mb-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-body-secondary p-3 text-primary">
                <x-ui.icon name="clipboard" size="30" />
            </span>
        </div>

        <h2 class="h5 fw-bold mb-2">Esta aula é uma prova</h2>

        <p class="text-body-secondary mb-4">
            @if(filled($quiz->instructions))
                {{ Str::limit($quiz->instructions, 140) }}
            @else
                São {{ $questionsCount }} {{ $questionsCount === 1 ? 'questão' : 'questões' }}{{ $quiz->time_limit_minutes ? ' e ' . $quiz->time_limit_minutes . ' minutos' : '' }}.
            @endif
        </p>

        <div>
            <x-ui.button variant="primary" href="{{ route('student.quizzes.show', $lesson) }}" dusk="start-quiz">
                Iniciar prova
            </x-ui.button>
        </div>
    </div>
@else
    <div class="p-5 text-center border border-dashed rounded-4 text-body-secondary" dusk="quiz-placeholder">
        <div class="mb-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-body-secondary p-3 text-body-secondary">
                <x-ui.icon name="info" size="30" />
            </span>
        </div>

        <h2 class="h5 fw-bold mb-2 text-body">Prova em preparação</h2>

        <p class="text-body-secondary mb-0">
            O responsável pelo curso ainda está montando esta avaliação. Ela aparece aqui quando estiver pronta.
        </p>
    </div>
@endif
