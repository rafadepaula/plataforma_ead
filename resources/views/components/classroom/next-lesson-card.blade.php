@props([
    'nextLesson' => null,
    'course' => null,
    'lesson' => null,
])

@php
    $targetLesson = $nextLesson ?? $lesson;
@endphp

@if($targetLesson)
    <x-ui.card title="Próxima aula" {{ $attributes }}>
        <a href="{{ route('classroom.lesson', $targetLesson) }}" class="ds-next-lesson">
            <span class="ds-lesson-icon">
                <x-ui.icon :name="match(true) {
                    $targetLesson->type === 'quiz' => 'clipboard',
                    filled($targetLesson->youtube_url ?? null) => 'play',
                    filled($targetLesson->pdf_path ?? null) => 'file-text',
                    default => 'book-open',
                }" :size="20" aria-hidden="true" />
            </span>
            <span class="ds-next-lesson-info">
                <span class="ds-next-lesson-title">{{ $targetLesson->title }}</span>
                @if($targetLesson->module)
                    <span class="ds-caption text-secondary">
                        Módulo {{ $targetLesson->module->position ?? ($targetLesson->module->order_index ?? '') }} · {{ $targetLesson->module->title }}
                    </span>
                @endif
            </span>
            <x-ui.icon name="chevron-right" :size="18" class="ds-next-lesson-chevron" aria-hidden="true" />
        </a>

        <x-ui.button variant="primary"
                     :href="route('classroom.lesson', $targetLesson)">
            Continuar
        </x-ui.button>
    </x-ui.card>
@endif
