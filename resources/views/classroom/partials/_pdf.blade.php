{{--
    SPEC-07 RF20 — PDF lesson. `pdf_path` is stored on the `public` disk by
    `FileUploadService` (SPEC-05), which is technically reachable by a
    guessed direct URL — the spec calls for a "download validado" (flagged
    as an open question in the SPEC-07 technical plan: no dedicated
    authenticated download route exists yet in `routes/web.php`, which is
    Bucket 2's file). Until that route lands, this uses `Storage::url()`
    as the pragmatic fallback rather than a hardcoded public path, so the
    swap to a controller-streamed route is a one-line change once
    available.
--}}

<div style="margin-bottom: 16px;">
    <iframe
        src="{{ Storage::url($lesson->pdf_path) }}"
        style="width: 100%; height: 600px; border: 1px solid var(--color-divider);"
        dusk="pdf-viewer-{{ $lesson->id }}"
    ></iframe>
</div>

<div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
    <a href="{{ Storage::url($lesson->pdf_path) }}" download style="font-size: 13px; color: var(--color-accent); font-weight: 700;" dusk="pdf-download-{{ $lesson->id }}">
        Baixar PDF
    </a>

    <div style="display: flex; align-items: center; gap: 12px;">
        {{--
            NOTE: `x-ui.badge` bakes `display: inline-flex` into its own
            inline `style`; the native `hidden` attribute's UA-stylesheet
            `display: none` cannot win against an inline style already
            set on the same element, so the hidden state is expressed as
            an explicit `style="display:none;"` override instead (Blade's
            `ComponentAttributeBag::merge()` appends the caller's `style`
            after the component's own, so the later declaration wins).
            `LessonPlayer.js.reflectCompletion()` reveals it by setting
            `style.display = 'inline-flex'` directly.
        --}}
        <x-ui.badge variant="accent" data-completion-badge dusk="lesson-completed-badge" style="{{ ($isCompleted ?? false) ? '' : 'display:none;' }}">
            Concluída
        </x-ui.badge>

        <x-ui.button
            data-mark-complete-url="{{ route('lessons.complete', $lesson) }}"
            :hidden="$isCompleted ?? false"
            dusk="mark-complete-button"
        >
            Marcar como concluída
        </x-ui.button>
    </div>
</div>
