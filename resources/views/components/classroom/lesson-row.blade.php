@props([
    'lesson',
    'completed' => false,
])

@php
    $done = (bool) $completed;
    $icon = $done ? 'check' : ($lesson->glyph ?? 'book-open');
@endphp

<li {{ $attributes->merge(['class' => 'ds-lesson-row']) }} dusk="lesson-{{ $lesson->id }}">
    <a href="{{ route('classroom.lesson', $lesson) }}"
       class="ds-lesson-link {{ $done ? 'is-completed' : '' }}"
       dusk="open-lesson-{{ $lesson->id }}">

        <span class="ds-lesson-icon {{ $done ? 'is-completed' : '' }}"
              @if($done) dusk="lesson-completed-{{ $lesson->id }}" @endif>
            <x-ui.icon :name="$icon" :size="20" aria-hidden="true" />
        </span>

        <span class="ds-lesson-title">{{ $lesson->title }}</span>

        {{--
            Deliberate exception to the "no raw markup, use <x-ui.*>" convention:
            <x-ui.chip> renders a <button>, and interactive content cannot be
            nested inside this row's <a>. Keep this plain <span> chip as-is.
        --}}
        <span class="ds-chip ds-chip-{{ $lesson->type === 'quiz' ? 'primary' : 'outline' }} ds-chip-plain">
            {{ $lesson->type === 'quiz' ? 'Prova' : 'Conteúdo' }}
        </span>

        <x-ui.icon name="chevron-right" :size="18" class="ds-lesson-chevron" aria-hidden="true" />
    </a>
</li>
