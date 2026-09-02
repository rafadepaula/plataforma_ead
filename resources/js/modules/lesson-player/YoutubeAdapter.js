import { VideoPlayerAdapter } from './VideoPlayerAdapter';
import { loadYouTubeIframeApi } from './sdk-loader';

/**
 * Adapter do YouTube IFrame API. A UI nativa fica 100% oculta
 * (`controls: 0`, `disablekb: 1` — o teclado é nosso) e o embed roda no
 * domínio privacy-enhanced `youtube-nocookie.com`; o botão de tela cheia do
 * próprio YouTube é desligado (`fs: 0`) porque o fullscreen é nosso, sobre o
 * container do player. O `YT.Player` substitui o elemento do stage pelo
 * `<iframe>` — o stage é descartável de propósito.
 */
export class YoutubeAdapter extends VideoPlayerAdapter {
    async boot() {
        await loadYouTubeIframeApi();

        const stage = this.container.querySelector('[data-player-stage]');
        this.player = new window.YT.Player(stage, {
            videoId: this.options.videoId,
            host: 'https://www.youtube-nocookie.com',
            playerVars: {
                rel: 0,
                modestbranding: 1,
                controls: 0,
                disablekb: 1,
                playsinline: 1,
                fs: 0,
            },
            events: {
                onReady: () => {
                    this.cachedDuration = this.player.getDuration() || 0;
                    this.startMonitor();
                    this.emit('ready');
                },
                onStateChange: (event) => {
                    this.setState(this.mapState(event.data));
                },
                onError: () => {
                    this.emit('error');
                },
            },
        });
    }

    /**
     * 250ms: cadência do monitor do tempo (a IFrame API não tem evento de
     * timeupdate; getDuration() volta 0 até os metadados chegarem, por isso
     * o cache só cresce, nunca regride).
     */
    readCurrentTime() {
        return typeof this.player?.getCurrentTime === 'function' ? this.player.getCurrentTime() : 0;
    }

    readDuration() {
        return typeof this.player?.getDuration === 'function' ? this.player.getDuration() : 0;
    }

    mapState(ytState) {
        const YT = window.YT?.PlayerState || {};

        if (ytState === YT.PLAYING) return 'playing';
        if (ytState === YT.PAUSED) return 'paused';
        if (ytState === YT.BUFFERING) return 'buffering';
        if (ytState === YT.ENDED) return 'ended';

        return 'unstarted';
    }

    play() {
        this.player?.playVideo?.();
    }

    pause() {
        this.player?.pauseVideo?.();
    }

    seek(seconds) {
        this.player?.seekTo?.(seconds, true);
    }

    /** Volume normalizado 0..1; a IFrame API trabalha em 0..100. */
    setVolume(volume) {
        this.player?.setVolume?.(Math.round(Math.min(1, Math.max(0, volume)) * 100));
    }

    setMuted(muted) {
        if (muted) {
            this.player?.mute?.();
        } else {
            this.player?.unMute?.();
        }
    }

    destroy() {
        this.stopMonitor();
        this.player?.destroy?.();
    }
}

export default YoutubeAdapter;
