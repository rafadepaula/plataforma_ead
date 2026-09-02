/**
 * Contrato comum dos adapters de provedor. Todo adapter expõe a MESMA
 * superfície imperativa (play/pause/seek/volume/mute) e reporta estado por
 * eventos normalizados — é o que mantém `PlayerController`, os controles
 * overlay e o tracking de progresso (task irmã) agnósticos ao provedor.
 *
 * Eventos emitidos (via `on`):
 *  - 'ready'       → player montado e metadados disponíveis
 *  - 'timeupdate'  → { currentTime, duration } (batida interna de ~250ms)
 *  - 'statechange' → { state: 'unstarted'|'buffering'|'playing'|'paused'|'ended' }
 *  - 'error'       → vídeo não pôde ser carregado (removido, privado, etc.)
 *
 * getCurrentTime()/getDuration() são síncronos e servem ao polling de 5s;
 * o Vimeo (API Promise-based) mantém o valor em cache via 'timeupdate'.
 */
export class VideoPlayerAdapter {
    constructor(container, options = {}) {
        this.container = container;
        this.options = options;
        this.listeners = { ready: [], timeupdate: [], statechange: [], error: [] };
        this.cachedTime = 0;
        this.cachedDuration = 0;
        this.state = 'unstarted';
        this.monitorInterval = null;
    }

    on(event, handler) {
        if (this.listeners[event]) this.listeners[event].push(handler);
    }

    emit(event, payload) {
        (this.listeners[event] || []).forEach((handler) => handler(payload));
    }

    /** Monta o player do provedor dentro de `[data-player-stage]`. */
    async boot() {
        throw new Error('VideoPlayerAdapter é abstrato: implemente boot().');
    }

    play() {
        throw new Error('VideoPlayerAdapter é abstrato: implemente play().');
    }

    pause() {
        throw new Error('VideoPlayerAdapter é abstrato: implemente pause().');
    }

    seek() {
        throw new Error('VideoPlayerAdapter é abstrato: implemente seek().');
    }

    setVolume() {
        throw new Error('VideoPlayerAdapter é abstrato: implemente setVolume().');
    }

    setMuted() {
        throw new Error('VideoPlayerAdapter é abstrato: implemente setMuted().');
    }

    getCurrentTime() {
        return this.cachedTime;
    }

    getDuration() {
        return this.cachedDuration;
    }

    getState() {
        return this.state;
    }

    setState(state) {
        this.state = state;
        this.emit('statechange', { state });
    }

    /**
     * Batida de timeupdate para provedores sem evento nativo (YouTube).
     * Deve ser cancelada em destroy().
     */
    startMonitor(intervalMs = 250) {
        this.stopMonitor();
        this.monitorInterval = setInterval(() => {
            this.cachedTime = this.readCurrentTime();
            this.cachedDuration = this.readDuration() || this.cachedDuration;
            this.emit('timeupdate', { currentTime: this.cachedTime, duration: this.cachedDuration });
        }, intervalMs);
    }

    stopMonitor() {
        if (this.monitorInterval) {
            clearInterval(this.monitorInterval);
            this.monitorInterval = null;
        }
    }

    destroy() {
        this.stopMonitor();
    }
}

export default VideoPlayerAdapter;
