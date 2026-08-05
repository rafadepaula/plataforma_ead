/**
 * LessonPlayer - SPEC-07 RF20 SOLID JavaScript module for the classroom
 * lesson player.
 *
 * Two independent responsibilities, mirroring the two non-quiz completion
 * paths `MarkLessonCompleteAction` supports:
 *
 * - Video lessons: loads the YouTube IFrame API against every
 *   `[data-youtube-player]` container, polls `player.getCurrentTime()` /
 *   `player.getDuration()` every 5s and POSTs `{ watched_seconds,
 *   duration_seconds }` to `lessons.progress` (via the shared `HttpClient`
 *   module). `reportProgress()` is intentionally public: it is the seam the
 *   Dusk `VideoThresholdCompletionTest` drives directly
 *   (`window.LessonPlayer.reportProgress(lessonId, watched, duration)`) to
 *   simulate reaching the 90% auto-completion threshold without depending
 *   on YouTube's real, network-bound IFrame API inside a headless browser.
 * - Manual lessons (text/image/PDF): binds every `[data-mark-complete-url]`
 *   button to POST `lessons.complete`.
 *
 * Both paths reflect `is_completed` in the UI without a reload by toggling
 * `[data-mark-complete-url]` (hide) and `[data-completion-badge]` (show).
 */
export class LessonPlayer {
    constructor(httpClient, notificationService) {
        this.httpClient = httpClient;
        this.notificationService = notificationService;
        this.videoPlayers = new Map();
    }

    init() {
        if (typeof document === 'undefined') return;

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.bind());
        } else {
            this.bind();
        }
    }

    bind() {
        this.bindManualCompletion();
        this.bindVideoPlayers();
    }

    bindManualCompletion() {
        document.querySelectorAll('[data-mark-complete-url]').forEach((button) => {
            button.addEventListener('click', () => this.markComplete(button));
        });
    }

    async markComplete(button) {
        const url = button.getAttribute('data-mark-complete-url');
        if (!url) return;

        button.disabled = true;

        try {
            const response = await this.httpClient.post(url);
            this.reflectCompletion(response.data);
            this.notify('success', 'Lição concluída com sucesso.');
        } catch (error) {
            button.disabled = false;
            this.notify('error', `Falha ao concluir a lição: ${error.message}`);
        }
    }

    bindVideoPlayers() {
        const containers = document.querySelectorAll('[data-youtube-player]');
        if (containers.length === 0) return;

        this.loadYouTubeApi(() => {
            containers.forEach((container) => this.createPlayer(container));
        });
    }

    loadYouTubeApi(onReady) {
        if (window.YT && window.YT.Player) {
            onReady();
            return;
        }

        const previousCallback = window.onYouTubeIframeAPIReady;
        window.onYouTubeIframeAPIReady = () => {
            if (typeof previousCallback === 'function') previousCallback();
            onReady();
        };

        if (!document.querySelector('script[src="https://www.youtube.com/iframe_api"]')) {
            const tag = document.createElement('script');
            tag.src = 'https://www.youtube.com/iframe_api';
            document.head.appendChild(tag);
        }
    }

    createPlayer(container) {
        const lessonId = container.getAttribute('data-lesson-id');
        const videoId = container.getAttribute('data-video-id');
        const progressUrl = container.getAttribute('data-progress-url');
        if (!lessonId || !videoId || !progressUrl) return;

        this.videoPlayers.set(String(lessonId), { progressUrl, intervalId: null });

        const player = new window.YT.Player(container.id, {
            videoId,
            playerVars: {
                rel: 0,
                modestbranding: 1,
                controls: 1,
            },
            events: {
                onReady: () => this.startPolling(lessonId, player),
            },
        });
    }

    startPolling(lessonId, player) {
        const intervalId = setInterval(() => {
            if (typeof player.getCurrentTime !== 'function' || typeof player.getDuration !== 'function') {
                return;
            }

            const watchedSeconds = Math.floor(player.getCurrentTime());
            const durationSeconds = Math.floor(player.getDuration());
            this.reportProgress(lessonId, watchedSeconds, durationSeconds);
        }, 5000);

        const entry = this.videoPlayers.get(String(lessonId)) || {};
        entry.intervalId = intervalId;
        this.videoPlayers.set(String(lessonId), entry);
    }

    /**
     * POSTs watched/duration seconds to `lessons.progress` for the given
     * lesson. Public on purpose — see the class docblock for why this is
     * also the Dusk test hook for `VideoThresholdCompletionTest`.
     */
    async reportProgress(lessonId, watchedSeconds, durationSeconds) {
        const entry = this.videoPlayers.get(String(lessonId));
        const progressUrl = entry ? entry.progressUrl : this.resolveProgressUrl(lessonId);
        if (!progressUrl) return;

        try {
            const response = await this.httpClient.post(progressUrl, {
                watched_seconds: watchedSeconds,
                duration_seconds: durationSeconds,
            });
            this.reflectCompletion(response.data);
        } catch (error) {
            this.notify('error', `Falha ao registrar progresso: ${error.message}`);
        }
    }

    resolveProgressUrl(lessonId) {
        const container = document.querySelector(`[data-youtube-player][data-lesson-id="${lessonId}"]`);
        return container ? container.getAttribute('data-progress-url') : null;
    }

    reflectCompletion(data) {
        if (!data || !data.is_completed) return;

        document.querySelectorAll('[data-mark-complete-url]').forEach((button) => {
            button.hidden = true;
        });

        // `x-ui.badge` bakes `display: inline-flex` into its own inline
        // `style`, and the Blade partials express the initial hidden
        // state as an explicit `style="display:none;"` override (a plain
        // `hidden` attribute can't win against an inline style already on
        // the element) — reveal it the same way, by writing `display`
        // directly, rather than toggling the (here, ineffective) `hidden`
        // DOM property.
        document.querySelectorAll('[data-completion-badge]').forEach((badge) => {
            badge.style.display = 'inline-flex';
        });
    }

    notify(type, message) {
        if (!this.notificationService || typeof this.notificationService[type] !== 'function') return;
        this.notificationService[type](message);
    }
}

export default LessonPlayer;
