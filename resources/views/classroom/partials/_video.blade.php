{{--
    SPEC-07 RF20 — video lesson. `youtube_url` is already persisted in
    sanitized embed form by `YoutubeSanitizerService` (SPEC-05), e.g.
    `https://www.youtube.com/embed/dQw4w9WgXcQ`, so the video id is simply
    the last path segment.

    `resources/js/modules/LessonPlayer.js` progressively enhances this
    static `<iframe>` into a YouTube IFrame API player (`data-youtube-player`
    marks the target `<div>`), polling `player.getCurrentTime()` every 5s
    and POSTing to `lessons.progress`; completion (>= 90% watched) is
    reflected here without a reload via `[data-completion-badge]`.
--}}

@php
    $videoId = basename(parse_url($lesson->youtube_url, PHP_URL_PATH));
@endphp

<div style="margin-bottom: 16px;">
    <div
        id="youtube-player-{{ $lesson->id }}"
        data-youtube-player
        data-lesson-id="{{ $lesson->id }}"
        data-video-id="{{ $videoId }}"
        data-progress-url="{{ route('lessons.progress', $lesson) }}"
        style="position: relative; padding-top: 56.25%; background: #000;"
        dusk="video-player-{{ $lesson->id }}"
    >
        {{-- Fallback restricted embed (no-JS / before the IFrame API takes over) --}}
        <iframe
            src="{{ $lesson->youtube_url }}?rel=0&modestbranding=1&controls=1"
            style="position: absolute; inset: 0; width: 100%; height: 100%; border: 0;"
            allow="autoplay; encrypted-media"
            allowfullscreen
        ></iframe>
    </div>
</div>

{{--
    NOTE: `x-ui.badge` bakes `display: inline-flex` into its own inline
    `style`; the native `hidden` attribute's UA-stylesheet `display: none`
    cannot win against an inline style already set on the same element,
    so the hidden state is expressed as an explicit `style="display:none;"`
    override instead (Blade's `ComponentAttributeBag::merge()` appends the
    caller's `style` after the component's own, so the later declaration
    wins). `LessonPlayer.js.reflectCompletion()` reveals it by setting
    `style.display = 'inline-flex'` directly.
--}}
<div style="display: flex; align-items: center; gap: 8px;">
    <x-ui.badge variant="accent" data-completion-badge dusk="lesson-completed-badge" style="{{ ($isCompleted ?? false) ? '' : 'display:none;' }}">
        Concluída
    </x-ui.badge>
    <span style="font-size: 12px; color: var(--color-neutral-600);" data-progress-hint>
        O progresso é salvo automaticamente ao assistir o vídeo.
    </span>
</div>
