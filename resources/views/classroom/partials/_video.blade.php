@php
    $videoId = $lesson->video_id;
    $embedUrl = $lesson->video_embed_url;
    /**
     * Só quem tem matrícula ativa grava progresso. Quem apenas visualiza a aula
     * (Admin/Gestor) recebe o player sem o polling: o endpoint recusaria a
     * escrita com 403 e a tela viraria uma fila de toasts de erro a cada 5s.
     */
    $pollsProgress = $videoId !== null && ($tracksProgress ?? true);

    // Estado assistido server-rendered: pinta o overlay do seek e o
    // indicador de % antes do primeiro POST do player.
    $watchedRanges = $watchedRanges ?? [];
    $durationForPercent = (int) ($durationSeconds ?? 0);
    $watchedUnique = (int) ($watchedSeconds ?? 0);
    $watchedPercent = $durationForPercent > 0
        ? (int) round(min(100, ($watchedUnique / $durationForPercent) * 100))
        : 0;
@endphp

<div class="ds-player ds-ratio ds-ratio-16x9 mb-4"
     id="video-player-{{ $lesson->id }}"
     dusk="video-player-{{ $lesson->id }}"
     @if($videoId !== null)
         data-video-player
         tabindex="0"
         role="region"
         aria-label="Player de vídeo da aula"
         data-lesson-id="{{ $lesson->id }}"
         data-provider="{{ $lesson->video_provider }}"
         data-video-id="{{ $videoId }}"
         data-video-embed="{{ $embedUrl }}"
         {{-- "Retomar de onde parou": o PLAYHEAD da última sessão. --}}
         data-resume-seconds="{{ $resumeSeconds ?? 0 }}"
         {{-- Intervalos já assistidos (união do servidor) para o overlay verde do seek. --}}
         data-watched-ranges="{{ json_encode($watchedRanges) }}"
         data-duration-seconds="{{ $durationForPercent > 0 ? $durationForPercent : '' }}"
         @if($pollsProgress) data-progress-url="{{ route('lessons.progress', $lesson) }}" @endif
     @endif
>
    @if($videoId !== null)
        {{-- O adapter do provedor substitui este stage pelo player real no clique. --}}
        <div class="ds-player-stage" data-player-stage aria-hidden="true"></div>

        {{--
            Fachada click-to-load: nenhum SDK de terceiro carrega antes do
            primeiro clique. A capa é o wash pastel do design system (a
            thumbnail remota só entraria com uma chamada externa no render,
            custo que o render síncrono da aula não paga).
        --}}
        <button type="button"
                class="ds-player-facade"
                data-player-facade
                dusk="video-facade-{{ $lesson->id }}"
                aria-label="Reproduzir vídeo">
            <span class="ds-player-facade-icon" aria-hidden="true">
                <x-ui.icon name="play" size="28" />
            </span>
        </button>

        {{--
            Controles 100% da plataforma, server-renderizados (os seletores
            dusk entram no snapshot por viverem aqui) e inertes até o boot —
            `LessonPlayer.js` só os revela quando o adapter fica pronto.
        --}}
        <div class="ds-player-controls d-none" data-player-controls>
            <button type="button"
                    class="ds-player-button"
                    data-player-toggle
                    dusk="video-play-{{ $lesson->id }}"
                    aria-label="Reproduzir ou pausar">
                <x-ui.icon name="play" size="18" class="ds-player-icon-play" aria-hidden="true" />
                <x-ui.icon name="pause" size="18" class="ds-player-icon-pause" aria-hidden="true" />
            </button>

            <span class="ds-player-time" data-player-current dusk="video-time-{{ $lesson->id }}">0:00</span>

            <input type="range"
                   class="ds-player-seek"
                   data-player-seek
                   dusk="video-seek-{{ $lesson->id }}"
                   min="0"
                   max="100"
                   step="0.1"
                   value="0"
                   aria-label="Posição do vídeo" />

            <span class="ds-player-time" data-player-duration>0:00</span>

            <button type="button"
                    class="ds-player-button"
                    data-player-mute
                    dusk="video-mute-{{ $lesson->id }}"
                    aria-label="Silenciar ou reativar o som">
                <x-ui.icon name="volume-2" size="18" class="ds-player-icon-sound" aria-hidden="true" />
                <x-ui.icon name="volume-x" size="18" class="ds-player-icon-muted" aria-hidden="true" />
            </button>

            <input type="range"
                   class="ds-player-volume"
                   data-player-volume
                   dusk="video-volume-{{ $lesson->id }}"
                   min="0"
                   max="1"
                   step="0.05"
                   value="1"
                   aria-label="Volume" />

            <button type="button"
                    class="ds-player-button"
                    data-player-fullscreen
                    dusk="video-fullscreen-{{ $lesson->id }}"
                    aria-label="Alternar tela cheia">
                <x-ui.icon name="maximize" size="18" class="ds-player-icon-maximize" aria-hidden="true" />
                <x-ui.icon name="minimize" size="18" class="ds-player-icon-minimize" aria-hidden="true" />
            </button>
        </div>

        {{--
            Estado degradado em RUNTIME (vídeo removido/privado no provedor):
            o adapter emite 'error' e este aviso substitui player e fachada.
        --}}
        <div class="ds-media-unavailable d-none" data-player-error>
            <p class="ds-media-unavailable-title">Vídeo indisponível</p>
            <p class="ds-media-unavailable-text">
                Não foi possível carregar o vídeo desta aula. Avise o responsável pelo curso para que o link seja corrigido.
            </p>
        </div>
    @else
        {{-- Tom neutro (`--attention-container`): o link quebrado não é culpa do aluno. --}}
        <div class="ds-media-unavailable" dusk="video-unavailable-{{ $lesson->id }}">
            <p class="ds-media-unavailable-title">Vídeo indisponível</p>
            <p class="ds-media-unavailable-text">
                Não foi possível reconhecer o endereço do vídeo desta aula. Avise o responsável pelo curso para que o link do vídeo seja corrigido.
            </p>
        </div>
    @endif
</div>

{{--
    Indicador de consumo à direita do vídeo: % único assistido (a união dos
    intervalos, não o playhead) e o threshold de 90% que declara o vídeo
    assistido. `data-watch-progress` é o hook do `PlayerController`, que
    reescreve barra e rótulo a cada resposta do endpoint de progresso.
--}}
@if($pollsProgress)
    <div class="d-flex align-items-center justify-content-end gap-2 mt-2"
         data-watch-progress
         data-lesson-id="{{ $lesson->id }}">
        <span class="small text-body-secondary" data-watch-progress-text>
            {{ $watchedPercent }}% assistido · 90% necessário para concluir
        </span>
        <div class="w-25">
            <x-ui.progress
                :value="$watchedUnique"
                :max="$durationForPercent > 0 ? $durationForPercent : 100"
                variant="success"
                :height="4"
                label="Percentual do vídeo assistido"
            />
        </div>
    </div>
@endif

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
