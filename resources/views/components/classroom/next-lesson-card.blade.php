@props([
    'lesson',
])

<x-ui.card title="Próxima aula" {{ $attributes }}>
    <a href="{{ route('classroom.lesson', $lesson) }}" class="ds-next-lesson">
        <span class="ds-lesson-icon">
            <x-ui.icon :name="$lesson->glyph ?? 'book-open'" :size="20" aria-hidden="true" />
        </span>
        <span class="ds-next-lesson-info">
            <span class="ds-next-lesson-title">{{ $lesson->title }}</span>
            @if($lesson->module)
                <span class="ds-caption text-secondary">{{ $lesson->module->title }}</span>
            @endif
        </span>
        <x-ui.icon name="chevron-right" :size="18" class="ds-next-lesson-chevron" aria-hidden="true" />
    </a>

    <x-ui.button variant="primary" :href="route('classroom.lesson', $lesson)">
        Continuar
    </x-ui.button>
</x-ui.card>
