import { createAdapter } from './createAdapter';
import WatchTracker from './WatchTracker';

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

/** Threshold de conclusão anunciado no indicador de % assistido. */
const REQUIRED_WATCHED_PERCENT = 90;

/**
 * Atraso do flush debounced do bookmark de retomada: rápido o bastante para
 * sobreviver a um F5 logo após o seek, espaçado o bastante para um arrasto
 * contínuo na barra não virar rajada de POSTs.
 */
const POSITION_FLUSH_DELAY_MS = 800;

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
        // "Retomar de onde parou": PLAYHEAD exato da última sessão (o
        // servidor manda last_position_seconds; cai para o primeiro segundo
        // não assistido quando não há bookmark). Consumido no boot.
        this.resumeSeconds = Number(container.getAttribute('data-resume-seconds')) || 0;
        // Estado inicial dos intervalos assistidos (server-rendered) para
        // pintar o overlay verde do seek e a barra de % antes do 1º POST.
        this.watchedRanges = this.parseWatchedRanges(
            container.getAttribute('data-watched-ranges'),
        );
        this.reportedDuration = Number(container.getAttribute('data-duration-seconds')) || 0;
        this.lastReportedPosition = null;
        this.pendingPositionOverride = null;
        this.positionFlushTimer = null;
        this.pageLifecycleBound = false;
        this.adapter = null;
        this.booted = false;
        this.booting = false;
        this.seeking = false;
        this.duration = 0;
        this.tracker = new WatchTracker();
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
        this.bindStageClickToToggle();
        this.bindDoubleClickToFullscreen();
        this.bindControls();
        this.bindKeyboard();
        this.bindFullscreenState();
        this.bindPageLifecycle();
    }

    /**
     * O F5 não espera o poll de 5s: escondendo a aba ou saindo da página,
     * o bookmark de retomada e os segundos pendentes saem na hora
     * (best-effort — a requisição pode não completar, por isso o seek e a
     * pausa também disparam flush próprio).
     */
    bindPageLifecycle() {
        if (this.pageLifecycleBound || typeof document === 'undefined') return;
        this.pageLifecycleBound = true;

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) this.flushProgress();
        });
        window.addEventListener('pagehide', () => this.flushProgress());
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
        this.paintWatchedOverlay();
        this.startProgressPolling();

        // Retomada: seek ANTES do play para que a reprodução comece no
        // primeiro segundo não assistido. Duração ainda desconhecida no
        // ready, então o clamp contra ela acontece aqui — se o vídeo mudou
        // e a retomada caiu além do fim, assiste do zero.
        if (this.resumeSeconds > 0) {
            const duration = this.adapter?.getDuration() || 0;

            if (duration <= 0 || this.resumeSeconds < Math.floor(duration)) {
                this.adapter?.seek(this.resumeSeconds);
            }

            this.resumeSeconds = 0;
        }

        // O clique na fachada FOI a intenção de reproduzir — começar a tocar
        // imediatamente também tira o embed do estado "unstarted", onde o
        // YouTube sobrepuja o próprio botão vermelho gigante e o chrome de
        // título. Se a política de autoplay bloquear (SDK demorou demais
        // após o gesto), o clique na área do vídeo inicia normalmente.
        this.adapter?.play();
    }

    /**
     * Clique em qualquer ponto da área de vídeo alterna play/pause (padrão
     * de todo player). O iframe do provedor é `pointer-events: none`, então
     * o clique chega ao container em vez de acionar a UI nativa do YouTube.
     * Cliques na própria barra de controles são ignorados aqui — cada
     * controle trata o seu.
     */
    bindStageClickToToggle() {
        this.container.addEventListener('click', (event) => {
            if (!this.booted) return;
            if (event.target.closest('[data-player-controls]')) return;
            if (event.target.closest('[data-player-error]')) return;

            this.togglePlay();
            this.showControls(true);
        });
    }

    /**
     * Duplo clique na área de vídeo alterna a tela cheia (padrão
     * YouTube/Vimeo). Os dois cliques simples do gesto alternam play/pause
     * duas vezes e se anulam, então a reprodução sai ilesa — só o
     * fullscreen alterna. Na barra de controles e no aviso de erro o duplo
     * clique não faz nada (a precisão do seek fica preservada). Funciona
     * antes mesmo do boot: `toggleFullscreen()` só fala com a API de
     * fullscreen, sem depender do adapter.
     */
    bindDoubleClickToFullscreen() {
        this.container.addEventListener('dblclick', (event) => {
            if (event.target.closest('[data-player-controls]')) return;
            if (event.target.closest('[data-player-error]')) return;

            event.preventDefault();
            this.toggleFullscreen();
        });
    }

    onTimeUpdate(currentTime, duration) {
        this.duration = duration || this.duration;
        this.tracker.onTimeUpdate(currentTime, this.duration);
        this.paintWatchedOverlay();

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
        this.tracker.onStateChange(state);

        // Pausar numa posição inédita precisa gravar o bookmark logo — o
        // poll de 5s pode não chegar antes do F5. Buffering NÃO agenda:
        // todo seek passa por buffering, e um flush aqui sairia com a
        // posição AINDA ANTIGA (o provedor reporta o alvo só ao terminar de
        // carregar) — era o que fazia o reload voltar para o ponto pré-seek.
        if (state === 'paused') this.schedulePositionFlush();

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
        this.flushProgress();

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
                const target = Number(this.seekBar.value);
                this.adapter?.seek(target);
                this.schedulePositionFlush(POSITION_FLUSH_DELAY_MS, Math.round(target));
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
        this.schedulePositionFlush(POSITION_FLUSH_DELAY_MS, Math.round(target));
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

            // Intervalos de segundos EFETIVAMENTE reproduzidos desde o último
            // POST — nunca a posição do playhead, que um seek para frente
            // inflaria. O POST dispara com segundos novos OU com o playhead
            // movido (pausado em posição inédita também atualiza o bookmark
            // de retomada).
            const positionSeconds = this.resolveCurrentPosition();
            const ranges = this.tracker.takePendingRanges();
            const positionChanged = positionSeconds !== null
                && positionSeconds !== this.lastReportedPosition;

            if (ranges.length === 0 && !positionChanged) return;

            // O intervalo não é um `await`: a rejeição é engolida aqui para
            // nunca virar unhandled rejection — `reportProgress` já notificou,
            // os intervalos voltam ao pendente e, estourado o orçamento de
            // falhas, o polling para abaixo.
            this.lessonPlayer
                .reportProgress(this.lessonId, ranges, durationSeconds, positionSeconds)
                .then((data) => {
                    this.applyProgressResponse(data, positionSeconds);

                    if (data && data.is_completed) this.stopProgressPolling();
                })
                .catch(() => {
                    this.tracker.restore(ranges);
                    this.progressFailures += 1;
                    if (this.progressFailures >= MAX_PROGRESS_FAILURES) this.stopProgressPolling();
                });
        }, PROGRESS_POLL_MS);
    }

    /**
     * Consumo comum das respostas de progresso (poll e flush): a resposta do
     * servidor é a autoridade — repinta overlay e barra de % com a união
     * persistida e marca a posição reportada como confirmada.
     */
    applyProgressResponse(data, positionSeconds) {
        if (positionSeconds !== null && positionSeconds !== undefined) {
            this.lastReportedPosition = positionSeconds;
        }

        if (!data) return;

        if (Array.isArray(data.watched_ranges)) this.watchedRanges = data.watched_ranges;
        if (data.duration_seconds) this.reportedDuration = data.duration_seconds;
        this.paintWatchedOverlay();
        this.updateWatchedIndicator(data);
    }

    stopProgressPolling() {
        if (this.progressInterval) {
            clearInterval(this.progressInterval);
            this.progressInterval = null;
        }
    }

    /**
     * Pinta os intervalos já assistidos no próprio slider de seek: cada
     * trecho vira um stop verde num gradiente de background, com o trilho
     * translúcido nos buracos. Duração desconhecida (primeiro timeupdate
     * ainda não chegou) limpa o overlay — ele volta no próximo tick.
     */
    paintWatchedOverlay() {
        if (!this.seekBar) return;

        const duration = this.duration || this.reportedDuration || 0;
        const ranges = (Array.isArray(this.watchedRanges) ? [...this.watchedRanges] : [])
            .map(([start, end]) => [Number(start), Number(end)])
            .filter(([start, end]) => Number.isFinite(start) && Number.isFinite(end) && end > start)
            .sort((a, b) => a[0] - b[0]);

        if (duration <= 0 || ranges.length === 0) {
            this.seekBar.style.removeProperty('background-image');
            return;
        }

        const watched = 'var(--success)';
        const track = 'color-mix(in srgb, var(--grey-50) 30%, transparent)';
        const stops = [];
        let cursor = 0;

        ranges.forEach(([start, end]) => {
            if (end <= cursor) return;

            const gapStart = (cursor / duration) * 100;
            const watchedStart = (Math.max(start, cursor) / duration) * 100;
            const watchedEnd = (end / duration) * 100;

            if (watchedStart > gapStart) {
                stops.push(`${track} ${gapStart}% ${watchedStart}%`);
            }

            stops.push(`${watched} ${watchedStart}% ${watchedEnd}%`);
            cursor = end;
        });

        if (cursor < duration) {
            stops.push(`${track} ${(cursor / duration) * 100}% 100%`);
        }

        this.seekBar.style.backgroundImage = `linear-gradient(to right, ${stops.join(', ')})`;
    }

    /**
     * Atualiza o indicador "% assistido · % necessário" que vive ao lado
     * do vídeo (fora do container do player). A porcentagem Necessária é a
     * mesma do threshold do servidor (90%).
     */
    updateWatchedIndicator(data) {
        const scope = document.querySelector(
            `[data-watch-progress][data-lesson-id="${this.lessonId}"]`,
        );

        if (!scope) return;

        const duration = data.duration_seconds || this.duration || this.reportedDuration || 0;
        const percent = duration > 0
            ? Math.min(100, Math.round((Number(data.watched_unique_seconds) / duration) * 100))
            : 0;

        const bar = scope.querySelector('[data-progress-bar]');

        if (bar) {
            bar.style.width = `${percent}%`;
            bar.closest('[role="progressbar"]')?.setAttribute('aria-valuenow', String(percent));
        }

        const label = scope.querySelector('[data-watch-progress-text]');

        if (label) {
            label.textContent = `${percent}% assistido · ${REQUIRED_WATCHED_PERCENT}% necessário para concluir`;
        }
    }

    /**
     * Flush debounced do bookmark de retomada: seek na barra, skip de
     * teclado e pausa chegam aqui. Sem isso o bookmark só viajaria no poll
     * de 5s — seek + F5 imediato voltaria a página para o ponto antigo
     * (exatamente o bug que motivou este método).
     *
     * `positionOverride`: o alvo do seek conhecido NO ato. O Vimeo não emite
     * `timeupdate` pausado, então o tracker pode não saber do novo ponto
     * quando o flush disparar — o alvo explícito cobre esse buraco.
     */
    schedulePositionFlush(delay = POSITION_FLUSH_DELAY_MS, positionOverride = null) {
        if (!this.progressUrl || !this.lessonId || !this.booted) return;

        // O override só é SOBRESCRITO por outro override (novo seek mais
        // recente vence). Um agendamento sem override (pausa) NÃO o limpa —
        // senão o flush dispararia com a posição antiga do tracker e faria o
        // bookmark regredir logo após um seek.
        if (positionOverride !== null) this.pendingPositionOverride = positionOverride;

        if (this.positionFlushTimer) clearTimeout(this.positionFlushTimer);

        this.positionFlushTimer = setTimeout(() => {
            this.positionFlushTimer = null;
            this.flushProgress();
        }, delay);
    }

    /**
     * Posição mais fresca disponível: o relógio do adapter (síncrono, com
     * o seek já aplicado 800ms depois do flush agendado), caindo para o
     * último timeupdate do tracker quando o adapter não tem valor.
     */
    resolveCurrentPosition() {
        const live = Math.floor(this.adapter?.getCurrentTime() || 0);

        return live > 0 ? live : this.tracker.lastPosition;
    }

    /**
     * Último POST antes do adapter/container sumirem (falha do provedor,
     * teardown, aba escondida): o que foi assistido e ainda não confirmado
     * não pode morrer no `Set`, e o bookmark de retomada viaja MESMO sem
     * segundos novos — batch só-de-posição (`segments: []`). Fire-and-forget —
     * na falha, os intervalos voltam ao pendente do tracker, que morre junto
     * com o controller; não há o que recuperar.
     */
    flushProgress() {
        if (!this.progressUrl || !this.lessonId || !this.booted) return;

        if (this.positionFlushTimer) {
            clearTimeout(this.positionFlushTimer);
            this.positionFlushTimer = null;
        }

        const durationSeconds = Math.floor(this.adapter?.getDuration() || 0);

        if (durationSeconds <= 0) return;

        const positionSeconds = this.pendingPositionOverride ?? this.resolveCurrentPosition();
        this.pendingPositionOverride = null;

        const ranges = this.tracker.takePendingRanges();
        const positionUnreported = positionSeconds !== null
            && positionSeconds !== this.lastReportedPosition;

        if (ranges.length === 0 && !positionUnreported) return;

        this.lessonPlayer
            .reportProgress(this.lessonId, ranges, durationSeconds, positionSeconds)
            .then((data) => this.applyProgressResponse(data, positionSeconds))
            .catch(() => this.tracker.restore(ranges));
    }

    /**
     * @param {string|null} raw JSON `[[start, end], ...]` server-rendered
     * @returns {Array<[number, number]>}
     */
    parseWatchedRanges(raw) {
        if (!raw) return [];

        try {
            const parsed = JSON.parse(raw);

            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
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
        this.flushProgress();
        this.stopProgressPolling();
        if (this.hideTimer) clearTimeout(this.hideTimer);
        if (this.positionFlushTimer) clearTimeout(this.positionFlushTimer);
        this.adapter?.destroy();
    }
}

export default PlayerController;
