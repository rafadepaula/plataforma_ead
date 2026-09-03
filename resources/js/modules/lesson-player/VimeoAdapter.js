import { VideoPlayerAdapter } from './VideoPlayerAdapter';
import { loadVimeoPlayerSdk } from './sdk-loader';

/**
 * Adapter do Vimeo Player SDK (`player.js`, carregado via CDN no clique da
 * fachada). Controles nativos desligados (`controls: false`), cromagem toda
 * oculta (title/byline/portrait), `dnt: true` na mesma linha da escolha do
 * youtube-nocookie. Vídeo UNLISTED entra pela `url` completa (`vimeo.com/
 * {id}/{hash}`) — a forma documentada de embedar com hash.
 */
export class VimeoAdapter extends VideoPlayerAdapter {
    async boot() {
        await loadVimeoPlayerSdk();

        const stage = this.container.querySelector('[data-player-stage]');
        const embedUrl = this.options.embedUrl || '';

        let source;
        if (this.options.videoHash) {
            source = { url: `https://vimeo.com/${this.options.videoId}/${this.options.videoHash}` };
        } else {
            const embedHash = embedUrl ? new URL(embedUrl).searchParams.get('h') : null;
            source = embedHash
                ? { url: `https://vimeo.com/${this.options.videoId}/${embedHash}` }
                : { id: Number(this.options.videoId) };
        }

        this.player = new window.Vimeo.Player(stage, {
            ...source,
            controls: false,
            title: false,
            byline: false,
            portrait: false,
            dnt: true,
            // `responsive: true` faz o SDK preencher o stage 16:9 sozinho;
            // `width`/`height` do SDK são pixels numéricos — as strings
            // '100%' eram ignoradas e o iframe caía no tamanho padrão,
            // minúsculo no canto do player. O preenchimento é reforçado
            // pelo `.ds-player-stage iframe` em _video-player.scss.
            responsive: true,
        });

        this.player.on('timeupdate', ({ seconds, duration }) => {
            this.cachedTime = seconds;
            this.cachedDuration = duration || this.cachedDuration;

            // Auto-correção: frames avançando provam reprodução — se o estado
            // ficou preso em 'unstarted'/'buffering' (o `bufferend` correu
            // antes do primeiro tick e o `cachedTime` ainda era 0), o botão
            // de pausa chamaria `play()` para sempre e nunca pausaria.
            // 'paused'/'ended' nunca são tocados aqui: um seek com o vídeo
            // pausado também emite ticks e não pode "ressuscitar" o estado.
            if (seconds > 0 && (this.getState() === 'unstarted' || this.getState() === 'buffering')) {
                this.setState('playing');
            }

            this.emit('timeupdate', { currentTime: seconds, duration: this.cachedDuration });
        });
        this.player.on('play', () => this.setState('playing'));
        this.player.on('pause', () => this.setState('paused'));
        this.player.on('ended', () => this.setState('ended'));
        // Seek com o vídeo pausado/encerrado também gera buffering — o estado
        // pertence ao usuário (pausado/encerrado), não ao rebuffer.
        this.player.on('bufferstart', () => {
            if (this.getState() === 'paused' || this.getState() === 'ended') return;
            this.setState('buffering');
        });
        this.player.on('bufferend', () => {
            if (this.getState() === 'paused' || this.getState() === 'ended') return;
            this.setState(this.cachedTime > 0 ? 'playing' : 'unstarted');
        });
        this.player.on('error', () => this.emit('error'));

        await this.player.ready();

        this.cachedDuration = (await this.player.getDuration().catch(() => 0)) || 0;
        this.emit('ready');
    }

    readCurrentTime() {
        return this.cachedTime;
    }

    readDuration() {
        return this.cachedDuration;
    }

    play() {
        this.player?.play?.().catch(() => {});
    }

    pause() {
        this.player?.pause?.().catch(() => {});
    }

    seek(seconds) {
        this.player?.setCurrentTime?.(seconds).catch(() => {});
    }

    setVolume(volume) {
        this.player?.setVolume?.(Math.min(1, Math.max(0, volume))).catch(() => {});
    }

    setMuted(muted) {
        this.player?.setMuted?.(muted).catch(() => {});
    }

    destroy() {
        this.player?.destroy?.().catch(() => {});
    }
}

export default VimeoAdapter;
