import { createAdapter } from './createAdapter';

/** Cadência do POST de progresso para `lessons.progress` (contrato vivo). */
const PROGRESS_POLL_MS = 5000;

/**
 * Falhas consecutivas de poll toleradas antes de derrubar o intervalo — um
 * blip não para o tracking; falha persistente não vira fila infinita de
 * toasts idênticos (o toast único é emissão de `reportProgress`).
 */
const MAX_PROGRESS_FAILURES = 3;

/** Controles overlay auto-escondem depois deste tempo de mouse parado. */
const CONTROLS_IDLE_HIDE_MS = 2500;

const VOLUME_STORAGE_KEY = 'ds-player-volume';
const MUTED_STORAGE_KEY = 'ds-player-muted';

/**
 * Dirige UM container `[data-video-player]`: fachada click-to-load, controles
 * overlay (play/pause, seek, tempo, volume/mute, fullscreen), atalhos de
 * teclado, auto-hide e o polling de progresso de 5s. Toda interação com o
 * provedor passa pelo `VideoPlayerAdapter` — este arquivo não sabe que
 * YouTube/Vimeo existem.
 */
export class PlayerController {
    constructor(container, lessonPlayer) {
        this.container = container;
        this.lessonPlayer = lessonPlayer;
        this.lessonId = container.getAttribute('data-lesson-id');
        this.provider = container.getAttribute('data-provider');
        this.videoId = container.getAttribute('data-video-id');
        this.embedUrl = container.getAttribute('data-video-embed');
        this.videoHash = container.getAttribute('data-video-hash');
        this.progressUrl = container.getAttribute('data-progress-url');
        this.adapter = null;
        this.booted = false;
        this.booting = false;
        this.seeking = false;
        this.duration = 0;
        this.progressInterval = null;
        this.progressFailures = 0;
        this.hideTimer = null;

        this.facade = container.querySelector('[data-player-facade]');
        this.stage = container.querySelector('[data-player-stage]');
        this.controls = container.querySelector('[data-player-controls]');
        this.errorNotice = container.querySelector('[data-player-error]');
        this.toggleButton = container.querySelector('[data-player-toggle]');
        this.seekBar = container.querySelector('[data-player-seek]');
        this.currentTimeLabel = container.querySelector('[data-player-current]');
        this.durationLabel = container.querySelector('[data-player-duration]');
        this.muteButton = container.querySelector('[data-player-mute]');
        this.volumeBar = container.querySelector('[data-player-volume]');
        this.fullscreenButton = container.querySelector('[data-player-fullscreen]');
    }

    /**
     * Liga a fachada e os controles. Antes do primeiro clique a página não
     * baixa SDK de terceiro algum — os controles ficam ocultos e inertes.
     */
    mount() {
        if (!this.facade || !this.stage) return;

        this.facade.addEventListener('click', () => this.boot());
        this.bindControls();
        this.bindKeyboard();
        this.bindFullscreenState();
    }

    async boot() {
        if (this.booted || this.booting) return;
        this.booting = true;
        this.facade.setAttribute('aria-busy', 'true');

        try {
            this.adapter = createAdapter(this.provider, this.container, {
                videoId: this.videoId,
                embedUrl: this.embedUrl,
                videoHash: this.videoHash,
            });

            this.adapter.on('ready', () => this.onAdapterReady());
            this.adapter.on('timeupdate', ({ currentTime, duration }) => this.onTimeUpdate(currentTime, duration));
            this.adapter.on('statechange', ({ state }) => this.onStateChange(state));
            this.adapter.on('error', () => this.showUnavailable());

            await this.adapter.boot();
        } catch (error) {
            this.showUnavailable();
        } finally {
            this.booting = false;
        }
    }

    onAdapterReady() {
        this.booted = true;
        this.container.classList.add('ds-player-active');

        // Visibilidade segue a regra da casa: `.d-none`, nunca `hidden`
        // (Reboot aplica `[hidden] { display: none !important }`).
        this.facade.classList.add('d-none');
        if (this.controls) this.controls.classList.remove('d-none');

        this.applyStoredVolume();
        this.startProgressPolling();
    }

    onTimeUpdate(currentTime, duration) {
        this.duration = duration || this.duration;

        if (this.durationLabel && this.duration > 0) {
            this.durationLabel.textContent = this.formatTime(this.duration);
            this.seekBar?.setAttribute('max', String(this.duration));
        }

        if (!this.seeking && this.seekBar) {
            this.seekBar.value = String(currentTime);
        }

        if (this.currentTimeLabel) {
            this.currentTimeLabel.textContent = this.formatTime(currentTime);
        }
    }

    onStateChange(state) {
        const playing = state === 'playing';
        this.container.classList.toggle('ds-player-playing', playing);
        this.container.classList.toggle('ds-player-buffering', state === 'buffering');

        if (playing) {
            this.scheduleControlsHide();
        } else {
            this.showControls();
        }
    }

    showUnavailable() {
        if (this.adapter) {
            this.adapter.destroy();
            this.adapter = null;
        }
        this.stopProgressPolling();

        this.facade?.classList.add('d-none');
        this.controls?.classList.add('d-none');
        this.container.classList.add('ds-player-failed');

        if (this.errorNotice) {
            this.errorNotice.classList.remove('d-none');
        }
    }

    // ------------------------------------------------------------------
    // Controles overlay
    // ------------------------------------------------------------------

    bindControls() {
        this.toggleButton?.addEventListener('click', () => this.togglePlay());

        if (this.seekBar) {
            this.seekBar.addEventListener('input', () => {
                this.seeking = true;
                if (this.currentTimeLabel) {
                    this.currentTimeLabel.textContent = this.formatTime(Number(this.seekBar.value));
                }
            });
            this.seekBar.addEventListener('change', () => {
                this.seeking = false;
                this.adapter?.seek(Number(this.seekBar.value));
            });
        }

        this.muteButton?.addEventListener('click', () => this.toggleMute());

        if (this.volumeBar) {
            this.volumeBar.addEventListener('input', () => {
                const volume = Number(this.volumeBar.value);
                this.applyVolume(volume, volume > 0 ? this.isMuted() : true);
            });
        }

        this.fullscreenButton?.addEventListener('click', () => this.toggleFullscreen());
        this.bindAutoHide();
    }

    togglePlay() {
        if (!this.adapter) return;

        if (this.adapter.getState() === 'playing') {
            this.adapter.pause();
        } else {
            this.adapter.play();
        }
    }

    toggleMute() {
        this.applyVolume(this.currentVolume(), !this.isMuted());
    }

    isMuted() {
        return this.container.classList.contains('ds-player-muted');
    }

    currentVolume() {
        return this.volumeBar ? Number(this.volumeBar.value) : 1;
    }

    applyVolume(volume, muted) {
        const bounded = Math.min(1, Math.max(0, volume));

        if (this.volumeBar) this.volumeBar.value = String(bounded);
        this.container.classList.toggle('ds-player-muted', muted);
        this.adapter?.setVolume(bounded);
        this.adapter?.setMuted(muted);

        try {
            window.localStorage.setItem(VOLUME_STORAGE_KEY, String(bounded));
            window.localStorage.setItem(MUTED_STORAGE_KEY, muted ? 'true' : 'false');
        } catch (error) {
            // localStorage indisponível (modo privado etc.): o volume vive
            // só nesta sessão — nada além disso para tratar.
        }
    }

    applyStoredVolume() {
        let volume = 1;
        let muted = false;

        try {
            volume = Number(window.localStorage.getItem(VOLUME_STORAGE_KEY));
            muted = window.localStorage.getItem(MUTED_STORAGE_KEY) === 'true';
        } catch (error) {
            // Sem localStorage: defaults 100% audível.
        }

        this.applyVolume(Number.isFinite(volume) ? volume : 1, muted);
    }

    toggleFullscreen() {
        if (document.fullscreenElement === this.container) {
            document.exitFullscreen().catch(() => {});
            return;
        }

        this.container.requestFullscreen?.().catch(() => {});
    }

    bindFullscreenState() {
        document.addEventListener('fullscreenchange', () => {
            this.container.classList.toggle(
                'ds-player-fullscreen',
                document.fullscreenElement === this.container
            );
        });
    }

    /**
     * Atalhos só quando o FOCO está no próprio container: se o alvo é um
     * botão/slider, o comportamento nativo dele prevalece (espaço num
     * `<button>` é clique, setas num `<input range>` são ajuste fino).
     */
    bindKeyboard() {
        this.container.addEventListener('keydown', (event) => {
            const target = event.target;
            if (target instanceof HTMLElement
                && target !== this.container
                && target.matches('button, input, select, textarea')) {
                return;
            }

            const handled = () => {
                event.preventDefault();
                event.stopPropagation();
            };

            switch (event.key) {
                case ' ':
                case 'k':
                    handled();
                    this.togglePlay();
                    break;
                case 'ArrowLeft':
                    handled();
                    this.skipBy(-5);
                    break;
                case 'ArrowRight':
                    handled();
                    this.skipBy(5);
                    break;
                case 'ArrowUp':
                    handled();
                    this.applyVolume(Math.min(1, this.currentVolume() + 0.05), false);
                    break;
                case 'ArrowDown':
                    handled();
                    this.applyVolume(Math.max(0, this.currentVolume() - 0.05), this.isMuted());
                    break;
                case 'm':
                    handled();
                    this.toggleMute();
                    break;
                case 'f':
                    handled();
                    this.toggleFullscreen();
                    break;
                default:
                    break;
            }
        });
    }

    skipBy(seconds) {
        if (!this.adapter) return;

        const target = Math.min(
            Math.max(0, this.adapter.getCurrentTime() + seconds),
            this.duration || Number.MAX_SAFE_INTEGER
        );
        this.adapter.seek(target);
    }

    // ------------------------------------------------------------------
    // Auto-hide dos controles overlay
    // ------------------------------------------------------------------

    bindAutoHide() {
        this.container.addEventListener('pointermove', () => this.showControls(true));
        this.container.addEventListener('pointerleave', () => {
            if (this.isControlsHideable()) this.hideControls();
        });
    }

    isControlsHideable() {
        return this.booted && this.container.classList.contains('ds-player-playing');
    }

    showControls(reschedule = false) {
        this.container.classList.remove('ds-player-controls-hidden');

        if (this.hideTimer) {
            clearTimeout(this.hideTimer);
            this.hideTimer = null;
        }

        if (reschedule && this.isControlsHideable()) {
            this.scheduleControlsHide();
        }
    }

    scheduleControlsHide() {
        if (this.hideTimer) clearTimeout(this.hideTimer);

        this.hideTimer = setTimeout(() => {
            this.hideTimer = null;
            if (this.isControlsHideable()) this.hideControls();
        }, CONTROLS_IDLE_HIDE_MS);
    }

    hideControls() {
        this.container.classList.add('ds-player-controls-hidden');
    }

    // ------------------------------------------------------------------
    // Polling de progresso (5s → lessons.progress)
    // ------------------------------------------------------------------

    startProgressPolling() {
        if (!this.progressUrl || !this.lessonId || this.progressInterval) return;

        this.progressInterval = setInterval(() => {
            const durationSeconds = Math.floor(this.adapter?.getDuration() || 0);

            if (durationSeconds <= 0) return;

            const watchedSeconds = Math.floor(this.adapter?.getCurrentTime() || 0);

            // O intervalo não é um `await`: a rejeição é engolida aqui para
            // nunca virar unhandled rejection — `reportProgress` já notificou
            // e, estourado o orçamento de falhas, o polling para abaixo.
            this.lessonPlayer
                .reportProgress(this.lessonId, watchedSeconds, durationSeconds)
                .then((data) => {
                    if (data && data.is_completed) this.stopProgressPolling();
                })
                .catch(() => {
                    this.progressFailures += 1;
                    if (this.progressFailures >= MAX_PROGRESS_FAILURES) this.stopProgressPolling();
                });
        }, PROGRESS_POLL_MS);
    }

    stopProgressPolling() {
        if (this.progressInterval) {
            clearInterval(this.progressInterval);
            this.progressInterval = null;
        }
    }

    formatTime(totalSeconds) {
        const seconds = Math.max(0, Math.floor(totalSeconds || 0));
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = String(seconds % 60).padStart(2, '0');
        const hours = Math.floor(minutes / 60);

        return hours > 0
            ? `${hours}:${String(minutes % 60).padStart(2, '0')}:${remainingSeconds}`
            : `${minutes}:${remainingSeconds}`;
    }

    destroy() {
        this.stopProgressPolling();
        if (this.hideTimer) clearTimeout(this.hideTimer);
        this.adapter?.destroy();
    }
}

export default PlayerController;
