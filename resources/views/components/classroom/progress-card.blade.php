@props([
    'progressPercentage' => 0,
    'completedLessonsCount' => null,
    'completedCount' => null,
    'totalLessonsCount' => null,
    'totalLessons' => null,
    'totalCount' => null,
])

@php
    $pct = (int) $progressPercentage;
    $completed = $completedCount ?? ($completedLessonsCount ?? 0);
    $total = $totalCount ?? ($totalLessons ?? ($totalLessonsCount ?? 0));
@endphp

<x-ui.card title="Progresso do curso" {{ $attributes }}>
    <div class="ds-progress-labels">
        <span class="ds-caption text-secondary">
            @if($completed === 0)
                Nenhuma aula concluída
            @else
                {{ $completed }} de {{ $total }} {{ $total === 1 ? 'aula concluída' : 'aulas concluídas' }}
            @endif
        </span>
        <span class="ds-caption ds-progress-value" dusk="course-progress-label">
            {{ $pct }}%
        </span>
    </div>

    <x-ui.progress :value="$pct"
                   :height="8"
                   :variant="$pct >= 100 ? 'success' : 'primary'"
                   label="Progresso do curso"
                   dusk="course-progress-bar" />

    @if($pct >= 100)
        <p class="ds-caption text-secondary mb-0 mt-2">
            Curso concluído. O certificado fica disponível abaixo.
        </p>
    @endif
</x-ui.card>
