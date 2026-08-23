{{--
    x-course.card-body — organization overline (1-line ellipsis), clamped
    title (3 lines) and summary (2 lines), plus the 10px progress bar
    (SPEC-26 §3.2). `.kicker` is this codebase's existing overline class
    (see `_page-header.scss`) — not a new `.ds-overline`. `progressPercentage`
    is trusted as already clamped to a 2% visual minimum by the controller
    (SPEC-26 bucket 2); this component only renders it.
--}}
@props(['enrollment'])

@php
    $course = data_get($enrollment, 'course');
    $organization = data_get($enrollment, 'organization');
    $courseId = optional($course)->id;
    $progress = (int) data_get($enrollment, 'progressPercentage', 0);
@endphp

<div class="card-body d-flex flex-column gap-2">
    <div class="kicker text-truncate">{{ optional($organization)->name ?? 'Organização' }}</div>

    <h3 class="ds-course-card-title mb-0">{{ optional($course)->title }}</h3>

    <p class="ds-course-card-summary text-body-secondary small mb-2">
        {{ optional($course)->description }}
    </p>

    <div class="ds-course-card-progress mt-auto">
        <div class="d-flex justify-content-between small text-body-secondary mb-1">
            <span>Progresso</span>
            <span class="fw-semibold">{{ $progress }}%</span>
        </div>

        <x-ui.progress
            :value="$progress"
            height="10"
            label="Progresso do curso"
            dusk="course-progress-{{ $courseId }}"
        />
    </div>
</div>
