{{--
    text/image lesson: plain content render + every attached image
    (multiple since `lesson_media`), plus an explicit "Marcar como concluída"
    button (hidden once `is_completed`), bound to POST `lessons.complete` by
    `resources/js/modules/LessonPlayer.js`.

    Images are read through `Lesson::media()`; the flat `image_path` column
    is only a legacy fallback for lessons that carry no media rows (e.g. rows
    written before the backfill migration).
--}}
@php
    $images = $lesson->media->where('kind', \App\Models\LessonMedia::KIND_IMAGE)->values();
@endphp

<div class="mb-4">
    @if ($images->isNotEmpty())
        @foreach ($images as $index => $media)
            <img
                src="{{ Storage::url($media->path) }}"
                alt="{{ $lesson->title }}"
                class="img-fluid border mb-4"
                dusk="lesson-image-{{ $lesson->id }}{{ $index > 0 ? '-'.$index : '' }}"
            >
        @endforeach
    @elseif (! empty($lesson->image_path))
        <img src="{{ Storage::url($lesson->image_path) }}" alt="{{ $lesson->title }}" class="img-fluid border mb-4" dusk="lesson-image-{{ $lesson->id }}">
    @endif

    @if(! empty($lesson->content_text))
        <div class="lh-base" dusk="lesson-content-{{ $lesson->id }}">
            {!! nl2br(e($lesson->content_text)) !!}
        </div>
    @endif
</div>

<div class="d-flex align-items-center gap-3">
    {{--
        Badge and button both express their hidden state with the `.d-none`
        utility, toggled by `LessonPlayer.js.reflectCompletion()` via
        `classList`. Do NOT use the native `hidden` attribute here:
        Bootstrap's Reboot emits `[hidden] { display: none !important }`, an
        author rule that beats any inline `style.display` the JS could write
        to reveal the element.
    --}}
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
