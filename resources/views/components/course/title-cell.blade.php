@props([
    'course',
])

<div class="fw-semibold">{{ $course->title }}</div>
<div class="ds-caption text-body-secondary">
    @if ((int) $course->modules_count === 0)
        Sem módulos cadastrados
    @else
        {{ $course->modules_count }} {{ Str::plural('módulo', $course->modules_count) }} · {{ $course->lessons_count }} {{ Str::plural('aula', $course->lessons_count) }}
    @endif
</div>
