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
            responsive: false,
            width: '100%',
            height: '100%',
        });

        this.player.on('timeupdate', ({ seconds, duration }) => {
            this.cachedTime = seconds;
            this.cachedDuration = duration || this.cachedDuration;
            this.emit('timeupdate', { currentTime: seconds, duration: this.cachedDuration });
        });
        this.player.on('play', () => this.setState('playing'));
        this.player.on('pause', () => this.setState('paused'));
        this.player.on('ended', () => this.setState('ended'));
        this.player.on('bufferstart', () => this.setState('buffering'));
        this.player.on('bufferend', () => this.setState(this.cachedTime > 0 ? 'playing' : 'unstarted'));
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
