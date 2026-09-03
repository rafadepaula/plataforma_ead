{{--
    x-course.card-footer — top-divider footer with one contextual primary
    action, an optional secondary action, and the
    "{N} aulas · {N}h · Prazo: DD/MM/AAAA" caption.
    `ctaHref`/`ctaLabel` are resolved by the controller
    (first published lesson / resume lesson / classroom); when `ctaHref`
    is null (no published lessons yet) the button degrades to disabled
    instead of linking to a 404. The secondary slot carries a `concluido`
    row's certificate: "Baixar certificado" as a link once issued, or the
    neutral "Certificado em emissão" placeholder while it hasn't.
--}}
@props(['enrollment'])

@php
    $course = data_get($enrollment, 'course');
    $courseId = optional($course)->id;
    $displayStatus = data_get($enrollment, 'displayStatus');
    $ctaHref = data_get($enrollment, 'ctaHref');
    $ctaLabel = data_get($enrollment, 'ctaLabel');
    $secondaryCtaHref = data_get($enrollment, 'secondaryCtaHref');
    $secondaryCtaLabel = data_get($enrollment, 'secondaryCtaLabel');
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

    @if ($secondaryCtaLabel && $secondaryCtaHref)
        <a
            href="{{ $secondaryCtaHref }}"
            dusk="course-certificate-{{ $courseId }}"
            class="small d-inline-flex align-items-center justify-content-center gap-2 text-body"
        >
            <x-ui.icon name="download" size="16" />
            {{ $secondaryCtaLabel }}
        </a>
    @elseif ($secondaryCtaLabel)
        <p class="ds-card-meta small text-body-secondary mb-0 text-center">{{ $secondaryCtaLabel }}</p>
    @endif

    <p class="ds-card-meta small text-body-secondary mb-0">{{ $metaLine }}</p>
</div>
