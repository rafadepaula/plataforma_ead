{{--
    PDF lesson: one 16:9 viewer + download link per attached PDF (multiple
    since `lesson_media`), stored on the `public` disk by
    `FileUploadService`. The flat `pdf_path` column is only a legacy fallback
    for lessons that carry no media rows (e.g. rows written before the
    backfill migration).
--}}
@php
    $pdfs = $lesson->media->where('kind', \App\Models\LessonMedia::KIND_PDF)->values();
@endphp

@if ($pdfs->isNotEmpty())
    @foreach ($pdfs as $index => $media)
        <div class="ratio ratio-16x9 mb-4">
            <iframe
                src="{{ Storage::url($media->path) }}"
                class="border"
                dusk="pdf-viewer-{{ $lesson->id }}{{ $index > 0 ? '-'.$index : '' }}"
            ></iframe>
        </div>

        <div class="mb-4">
            <a
                href="{{ Storage::url($media->path) }}"
                download
                class="link-primary fw-bold small"
                dusk="pdf-download-{{ $lesson->id }}{{ $index > 0 ? '-'.$index : '' }}"
            >
                Baixar PDF
            </a>
        </div>
    @endforeach
@elseif (! empty($lesson->pdf_path))
    <div class="ratio ratio-16x9 mb-4">
        <iframe
            src="{{ Storage::url($lesson->pdf_path) }}"
            class="border"
            dusk="pdf-viewer-{{ $lesson->id }}"
        ></iframe>
    </div>
@endif

<div class="d-flex align-items-center justify-content-between gap-3">
    @if ($pdfs->isEmpty() && ! empty($lesson->pdf_path))
        <a href="{{ Storage::url($lesson->pdf_path) }}" download class="link-primary fw-bold small" dusk="pdf-download-{{ $lesson->id }}">
            Baixar PDF
        </a>
    @else
        <span></span>
    @endif

    <div class="d-flex align-items-center gap-3">
        {{--
            Badge and button both express their hidden state with the
            `.d-none` utility, toggled by
            `LessonPlayer.js.reflectCompletion()` via `classList`. Do NOT
            use the native `hidden` attribute here: Bootstrap's Reboot
            emits `[hidden] { display: none !important }`, an author rule
            that beats any inline `style.display` the JS could write to
            reveal the element.
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
</div>
