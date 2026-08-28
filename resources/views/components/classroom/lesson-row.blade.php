@props([
    'lesson',
    'isCompleted' => false,
    'completed' => null,
    'routeUrl' => null,
])

@php
    $done = $completed ?? $isCompleted;
    $url = $routeUrl ?? route('classroom.lesson', $lesson);

    $icon = $done
        ? 'check'
        : match(true) {
            $lesson->type === 'quiz' => 'clipboard',
            filled($lesson->youtube_url ?? null) => 'play',
            filled($lesson->pdf_path ?? null) => 'file-text',
            default => 'book-open',
        };
@endphp

<li {{ $attributes->merge(['class' => 'ds-lesson-row']) }} dusk="lesson-{{ $lesson->id }}">
    <a href="{{ $url }}"
       class="ds-lesson-link {{ $done ? 'is-completed' : '' }}"
       dusk="open-lesson-{{ $lesson->id }}">

        <span class="ds-lesson-icon {{ $done ? 'is-completed' : '' }}"
              @if($done) dusk="lesson-completed-{{ $lesson->id }}" @endif>
            <x-ui.icon :name="$icon" :size="20" aria-hidden="true" />
        </span>

        <span class="ds-lesson-title">{{ $lesson->title }}</span>

        <span class="ds-chip ds-chip-{{ $lesson->type === 'quiz' ? 'primary' : 'outline' }} ds-chip-plain">
            {{ $lesson->type === 'quiz' ? 'Prova' : 'Conteúdo' }}
        </span>

        <x-ui.icon name="chevron-right" :size="18" class="ds-lesson-chevron" aria-hidden="true" />
    </a>
</li>
