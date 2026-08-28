@php
    $images = $lesson->images()->orderBy('id')->get();
    if ($images->isEmpty() && ! empty($lesson->image_path)) {
        $images = collect([(object) ['path' => $lesson->image_path]]);
    }
@endphp

<div class="mx-auto w-100 ds-reading-column">
    @foreach($images as $index => $image)
        @php
            $imageUrl = Storage::url($image->path);
            $suffix = $index > 0 ? "-{$index}" : '';
        @endphp
        @if ($imageUrl)
            <div class="ratio ratio-16x9 mb-4 overflow-hidden rounded-3">
                <img
                    src="{{ $imageUrl }}"
                    alt="{{ $lesson->title }}"
                    class="w-100 h-100 object-fit-cover"
                    loading="lazy"
                    dusk="lesson-image-{{ $lesson->id }}{{ $index > 0 ? '-'.$index : '' }}"
                >
            </div>
        @endif
    @endforeach

    @if(! empty($lesson->content_text))
        <div class="lh-base mb-4 fs-6 ds-lesson-content" dusk="lesson-content-{{ $lesson->id }}">{{ $lesson->content_text }}</div>
    @endif

    <div class="d-flex align-items-center gap-3">
        <x-ui.badge variant="accent" data-completion-badge dusk="lesson-completed-badge" @class(['d-none' => ! ($isCompleted ?? false)])>
            Concluída
        </x-ui.badge>

        <x-ui.button
            data-mark-complete-url="{{ route('lessons.complete', $lesson) }}"
            @class(['d-none' => $isCompleted ?? false])
            dusk="mark-complete-button"
        >
            Marcar como concluída
        </x-ui.button>
    </div>
</div>
