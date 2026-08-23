{{--
    x-course.card-header — 168px media band (SPEC-26 §3.1). No `cover_path`
    column exists on `courses` yet (see the spec's open question — this
    bucket's scope is gradient-only), so `$enrollment.coverUrl`/
    `course.cover_url` is read defensively: whichever the controller ends up
    exposing, a null value degrades to the pastel-wash gradient rather than
    breaking. The status chip always renders — one of the 4 derived
    display statuses — fixed to the bottom-left corner of the media band.
--}}
@props(['enrollment'])

@php
    $course = data_get($enrollment, 'course');
    $courseId = optional($course)->id;
    $displayStatus = data_get($enrollment, 'displayStatus');
    $coverUrl = data_get($enrollment, 'coverUrl') ?? data_get($enrollment, 'course.cover_url');

    [$chipVariant, $chipLabel, $chipIcon] = match ($displayStatus) {
        'em_andamento' => ['info', 'Em andamento', null],
        'concluido' => ['success', 'Concluído', null],
        'expirado' => ['accent-2', 'Prazo encerrado', 'alert-circle'],
        default => ['neutral', 'Não iniciado', null],
    };
@endphp

<div class="ds-course-card-media">
    @if ($coverUrl)
        <img src="{{ $coverUrl }}" alt="" class="ds-course-card-cover">
        <div class="ds-course-card-veil" aria-hidden="true"></div>
    @else
        <div class="ds-pastel-wash ds-course-card-wash" aria-hidden="true"></div>
    @endif

    <x-ui.badge
        :variant="$chipVariant"
        :dot="false"
        class="ds-course-card-chip"
        dusk="course-status-{{ $courseId }}"
    >
        @if ($chipIcon)
            <x-ui.icon :name="$chipIcon" size="14" />
        @endif
        {{ $chipLabel }}
    </x-ui.badge>
</div>
