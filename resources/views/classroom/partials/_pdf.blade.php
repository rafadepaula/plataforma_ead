{{--
    PDF lesson. `pdf_path` is stored on the `public` disk by
    `FileUploadService`. Uses `Storage::url()` as the fallback to render
    the PDF in an iframe.
--}}

<div class="ratio ratio-16x9 mb-4">
    <iframe
        src="{{ Storage::url($lesson->pdf_path) }}"
        class="border"
        dusk="pdf-viewer-{{ $lesson->id }}"
    ></iframe>
</div>

<div class="d-flex align-items-center justify-content-between gap-3">
    <a href="{{ Storage::url($lesson->pdf_path) }}" download class="link-primary fw-bold small" dusk="pdf-download-{{ $lesson->id }}">
        Baixar PDF
    </a>

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
