{{--
    SPEC-07 RF20 — text/image lesson: plain content render + an explicit
    "Marcar como concluída" button (hidden once `is_completed`), bound to
    POST `lessons.complete` by `resources/js/modules/LessonPlayer.js`.
--}}

<div style="margin-bottom: 16px;">
    @if(! empty($lesson->image_path))
        <img src="{{ Storage::url($lesson->image_path) }}" alt="{{ $lesson->title }}" style="max-width: 100%; height: auto; border: 1px solid var(--color-divider); margin-bottom: 16px;" dusk="lesson-image-{{ $lesson->id }}">
    @endif

    @if(! empty($lesson->content_text))
        <div style="font-size: 14px; line-height: 1.6; color: var(--color-text);" dusk="lesson-content-{{ $lesson->id }}">
            {!! nl2br(e($lesson->content_text)) !!}
        </div>
    @endif
</div>

<div style="display: flex; align-items: center; gap: 12px;">
    {{--
        NOTE: `x-ui.badge` bakes `display: inline-flex` into its own inline
        `style`; the native `hidden` attribute's UA-stylesheet
        `display: none` cannot win against an inline style already set on
        the same element, so the hidden state is expressed as an explicit
        `style="display:none;"` override instead (Blade's
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
