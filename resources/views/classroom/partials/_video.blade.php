@php
    $videoId = $lesson->youtube_video_id;
    $embedUrl = $lesson->youtube_embed_url;
    /**
     * Só quem tem matrícula ativa grava progresso. Quem apenas visualiza a aula
     * (Admin/Gestor) recebe o player sem o polling: o endpoint recusaria a
     * escrita com 403 e a tela viraria uma fila de toasts de erro a cada 5s.
     */
    $pollsProgress = $videoId !== null && ($tracksProgress ?? true);
@endphp

<div @class(['mb-4', 'ds-ratio ds-ratio-16x9' => $videoId !== null])>
    <div
        id="youtube-player-{{ $lesson->id }}"
        @if($pollsProgress)
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
            {{-- Tom neutro (`--attention-container`): o link quebrado não é culpa do aluno. --}}
            <div class="ds-media-unavailable" dusk="video-unavailable-{{ $lesson->id }}">
                <p class="ds-media-unavailable-title">Vídeo indisponível</p>
                <p class="ds-media-unavailable-text">
                    Não foi possível reconhecer o endereço do vídeo desta aula. Avise o responsável pelo curso para que o link do YouTube seja corrigido.
                </p>
            </div>
        @endif
    </div>
</div>

{{--
    Só o vídeo reconhecido conclui sozinho a 90%. Sem id de vídeo não existe
    player para medir progresso, então a lição volta a aceitar conclusão
    manual — caso contrário um link quebrado travaria o curso inteiro.
--}}
<x-classroom.completion-bar :lesson="$lesson" :is-completed="$isCompleted ?? false" :manual="$videoId === null" :tracks-progress="$tracksProgress ?? true">
    @if($pollsProgress)
        <span class="small text-body-secondary" data-progress-hint>
            O progresso é salvo automaticamente ao assistir o vídeo.
        </span>
    @endif
</x-classroom.completion-bar>
