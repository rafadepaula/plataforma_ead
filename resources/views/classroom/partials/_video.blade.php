{{--
    SPEC-07 RF20 — video lesson. BUG-002: this view no longer trusts the
    stored format. `youtube_url` *should* already be in sanitized embed form
    (`YoutubeSanitizerService`, SPEC-05), but nothing in the database enforces
    that, so the video id is resolved through `Lesson::$youtube_video_id` —
    which accepts `embed/`, `watch?v=` and `youtu.be/` alike and returns
    `null` for anything unrecognizable. When it is `null` we degrade to an
    explicit notice instead of emitting an `<iframe>` YouTube would refuse to
    frame (and a bogus `data-video-id` that would break progress tracking).

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
    $videoId = $lesson->youtube_video_id;
    $embedUrl = $lesson->youtube_embed_url;
@endphp

{{--
    The 16:9 black box only makes sense around a real player; the degraded
    branch is plain flow content. The video-player dusk selector stays on the
    same single element in both branches — it is a test contract and must never
    be duplicated across an `@if`.
--}}
<div @class(['mb-4', 'ratio ratio-16x9 bg-black' => $videoId !== null])>
    <div
        id="youtube-player-{{ $lesson->id }}"
        @if($videoId !== null)
            data-youtube-player
            data-lesson-id="{{ $lesson->id }}"
            data-video-id="{{ $videoId }}"
            data-progress-url="{{ route('lessons.progress', $lesson) }}"
        @endif
        dusk="video-player-{{ $lesson->id }}"
    >
        @if($videoId !== null)
            {{-- Fallback restricted embed (no-JS / before the IFrame API takes over) --}}
            <iframe
                src="{{ $embedUrl }}?rel=0&modestbranding=1&controls=1"
                class="w-100 h-100 border-0"
                allow="autoplay; encrypted-media"
                allowfullscreen
            ></iframe>
        @else
            <x-ui.alert variant="warning" dusk="video-unavailable-{{ $lesson->id }}" class="mb-0">
                <span class="fw-semibold d-block">Vídeo indisponível</span>
                Não foi possível reconhecer o endereço do vídeo desta aula. Avise o
                responsável pelo curso para que o link do YouTube seja corrigido.
            </x-ui.alert>
        @endif
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
    @if($videoId !== null)
        <span class="small text-body-secondary" data-progress-hint>
            O progresso é salvo automaticamente ao assistir o vídeo.
        </span>
    @endif
</div>
