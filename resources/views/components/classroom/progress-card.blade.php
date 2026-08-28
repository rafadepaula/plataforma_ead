@props([
    'progressPercentage' => 0,
    'completedCount' => 0,
    'totalCount' => 0,
    'certificateAvailable' => false,
])

@php
    $pct = (int) $progressPercentage;
    $completed = (int) $completedCount;
    $total = (int) $totalCount;
    /** Only point the student to the certificate below when there is one to download. */
    $hasDownloadableCertificate = (bool) $certificateAvailable;
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
            @if($hasDownloadableCertificate)
                Curso concluído. O certificado fica disponível abaixo.
            @else
                Curso concluído. Acompanhe a situação do certificado abaixo.
            @endif
        </p>
    @endif
</x-ui.card>
