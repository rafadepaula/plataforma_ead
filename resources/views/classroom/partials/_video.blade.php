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

    The 16:9 box lives on the OUTER wrapper rather than on the
    `[data-youtube-player]` element itself: `YT.Player()` replaces that
    element with its own `<iframe>`, and `.ratio > *` keeps sizing whatever
    ends up in that slot.
--}}

@php
    $videoId = basename(parse_url($lesson->youtube_url, PHP_URL_PATH));
@endphp

<div class="ratio ratio-16x9 bg-black mb-4">
    <div
        id="youtube-player-{{ $lesson->id }}"
        data-youtube-player
        data-lesson-id="{{ $lesson->id }}"
        data-video-id="{{ $videoId }}"
        data-progress-url="{{ route('lessons.progress', $lesson) }}"
        dusk="video-player-{{ $lesson->id }}"
    >
        {{-- Fallback restricted embed (no-JS / before the IFrame API takes over) --}}
        <iframe
            src="{{ $lesson->youtube_url }}?rel=0&modestbranding=1&controls=1"
            class="w-100 h-100 border-0"
            allow="autoplay; encrypted-media"
            allowfullscreen
        ></iframe>
    </div>
</div>

{{--
    The badge's hidden state is the `.d-none` utility, toggled by
    `LessonPlayer.js.reflectCompletion()` via `classList`. Do NOT use the
    native `hidden` attribute here: Bootstrap's Reboot emits
    `[hidden] { display: none !important }`, an author rule that beats any
    inline `style.display` the JS could write to reveal the element.
--}}
<div class="d-flex align-items-center gap-2">
    <x-ui.badge variant="accent" data-completion-badge dusk="lesson-completed-badge" @class(['d-none' => ! ($isCompleted ?? false)])>
        Concluída
    </x-ui.badge>
    <span class="small text-body-secondary" data-progress-hint>
        O progresso é salvo automaticamente ao assistir o vídeo.
    </span>
</div>
