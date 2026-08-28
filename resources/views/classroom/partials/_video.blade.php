@php
    $videoId = $lesson->youtube_video_id;
    $embedUrl = $lesson->youtube_embed_url;
@endphp

<div @class(['mb-4', 'ratio ratio-16x9 bg-black rounded-4 overflow-hidden' => $videoId !== null])>
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
            <iframe
                src="{{ $embedUrl }}?rel=0&modestbranding=1&controls=1"
                class="w-100 h-100 border-0"
                allow="autoplay; encrypted-media"
                allowfullscreen
            ></iframe>
        @else
            <x-ui.alert variant="warning" dusk="video-unavailable-{{ $lesson->id }}" class="mb-0">
                <span class="fw-semibold d-block">Vídeo indisponível</span>
                Não foi possível reconhecer o endereço do vídeo desta aula. Avise o responsável pelo curso para que o link do YouTube seja corrigido.
            </x-ui.alert>
        @endif
    </div>
</div>

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
