{{--
    x-course.card — shell of the "Meus Cursos" rich card. Four
    zones: media header, body (overline/title/summary/progress), footer
    (contextual CTA + metadata caption). Each zone is its own sub-component
    so the header/body/footer can be unit-tested and reasoned about in
    isolation; this shell only lays them out and forwards `$attributes`
    (the `dusk="course-card-{id}"` selector) to the outer `.card`.

    Accepts the per-card view-model the controller is
    contracted to pass as `enrollment`, reading every field through
    `data_get()` so this component works whether the controller hands it a
    plain array or an object — array/object fields:
    course, organization, displayStatus, progressPercentage, ctaLabel,
    ctaHref, lessonsCount, workloadHours, deadlineLabel.
--}}
@props(['enrollment'])

<div {{ $attributes->merge(['class' => 'card ds-course-card h-100']) }}>
    <x-course.card-header :enrollment="$enrollment" />
    <x-course.card-body :enrollment="$enrollment" />
    <x-course.card-footer :enrollment="$enrollment" />
</div>
