{{--
    x-course.card-footer — top-divider footer with one contextual primary
    action plus the "{N} aulas · {N}h · Prazo: DD/MM/AAAA" caption
    (SPEC-26 §3.3). `ctaHref`/`ctaLabel` are resolved by the controller
    (SPEC-26 bucket 2: first published lesson / resume lesson / certificate
    download / read-only classroom); when `ctaHref` is null (no published
    lessons yet, or a completed course whose certificate hasn't issued yet)
    the button degrades to disabled instead of linking to a 404 — see the
    plan's edge cases.
--}}
@props(['enrollment'])

@php
    $course = data_get($enrollment, 'course');
    $courseId = optional($course)->id;
    $displayStatus = data_get($enrollment, 'displayStatus');
    $ctaHref = data_get($enrollment, 'ctaHref');
    $ctaLabel = data_get($enrollment, 'ctaLabel');
    $lessonsCount = (int) data_get($enrollment, 'lessonsCount', 0);
    $workloadHours = (int) data_get($enrollment, 'workloadHours', 0);
    $deadlineLabel = data_get($enrollment, 'deadlineLabel');

    $variant = in_array($displayStatus, ['concluido', 'expirado'], true) ? 'tonal' : 'primary';
    $showChevron = $displayStatus === 'em_andamento' && $ctaHref;

    $metaLine = $displayStatus === 'concluido'
        ? ($deadlineLabel ? "Concluído em {$deadlineLabel}" : 'Concluído')
        : trim(
            "{$lessonsCount} ".Str::plural('aula', $lessonsCount)." · {$workloadHours}h"
            .($deadlineLabel ? " · Prazo: {$deadlineLabel}" : '')
        );
@endphp

<div class="card-footer border-top d-flex flex-column gap-2">
    <x-ui.button
        :variant="$variant"
        :href="$ctaHref"
        :disabled="! $ctaHref"
        block
        class="d-inline-flex align-items-center justify-content-center gap-2"
        dusk="course-continue-{{ $courseId }}"
    >
        {{ $ctaLabel ?? 'Conteúdo indisponível' }}
        @if ($showChevron)
            <x-ui.icon name="chevron-right" size="16" />
        @endif
    </x-ui.button>

    <p class="ds-card-meta small text-body-secondary mb-0">{{ $metaLine }}</p>
</div>
