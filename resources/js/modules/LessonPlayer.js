/**
 * LessonPlayer - Unified classroom lesson player module.
 *
 * Responsibilities:
 * - Video lessons: loads the YouTube IFrame API against every `[data-youtube-player]`
 *   container, polls player.getCurrentTime() / player.getDuration() every 5s and
 *   POSTs { watched_seconds, duration_seconds } to `lessons.progress`.
 *   Provides public test hook `window.LessonPlayer.reportProgress(lessonId, watched, duration)`
 *   for deterministic E2E test execution.
 * - Manual completion (text, image, PDF): binds click handlers on completion buttons
 *   (`[data-mark-complete-url]`, `[data-action="complete-lesson"]`, `[dusk="mark-complete-button"]`),
 *   POSTs to `lessons.complete`, manages loading state ('Marcando...'), and updates UI.
 * - UI updates: Toggles visibility using CSS classes (`.d-none` / `.ds-hidden`).
 *   NEVER uses the HTML `hidden` attribute due to Bootstrap's `[hidden] { display: none !important }`.
 */
export class LessonPlayer {
    constructor(httpClient, notificationService) {
        this.httpClient = httpClient;
        this.notificationService = notificationService;
        this.videoPlayers = new Map();
        this.pollingIntervals = new Map();
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
        const selectors = [
            '[data-mark-complete-url]',
            '[data-action="complete-lesson"]',
            '[dusk="mark-complete-button"]',
        ];

        const buttons = document.querySelectorAll(selectors.join(','));
        buttons.forEach((button) => {
            if (button.dataset.lessonPlayerBound) return;
            button.dataset.lessonPlayerBound = 'true';

            button.addEventListener('click', (event) => {
                event.preventDefault();
                this.markComplete(button);
            });
        });
    }

    async markComplete(target) {
        let button = null;
        let url = null;

        if (target instanceof HTMLElement) {
            button = target;
            url = button.getAttribute('data-mark-complete-url')
                || button.getAttribute('data-url')
                || button.getAttribute('href')
                || (button.dataset.lessonId ? `/lessons/${button.dataset.lessonId}/complete` : null);
        } else if (typeof target === 'string' || typeof target === 'number') {
            const lessonId = String(target);
            button = document.querySelector(
                `[data-mark-complete-url*="/lessons/${lessonId}/complete"], [data-lesson-id="${lessonId}"][dusk="mark-complete-button"], [data-lesson-id="${lessonId}"][data-action="complete-lesson"]`
            );
            url = button
                ? (button.getAttribute('data-mark-complete-url') || button.getAttribute('data-url') || button.getAttribute('href'))
                : `/lessons/${lessonId}/complete`;
        }

        if (!url && button) {
            const form = button.closest('form');
            if (form) {
                url = form.getAttribute('action');
            }
        }

        if (!url) return;

        let originalContent = '';
        if (button) {
            button.disabled = true;
            originalContent = button.innerHTML;
            button.textContent = 'Marcando...';
        }

        try {
            const response = await this.httpClient.post(url);
            this.reflectCompletion(response.data);
            this.notify('success', 'Lição concluída com sucesso.');
            return response.data;
        } catch (error) {
            if (button) {
                button.disabled = false;
                button.innerHTML = originalContent;
            }
            this.notify('error', `Falha ao concluir a lição: ${error.message || 'Erro inesperado'}`);
            throw error;
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
        if (!lessonId || !videoId) return;

        const resolvedProgressUrl = progressUrl || `/lessons/${lessonId}/progress`;
        this.videoPlayers.set(String(lessonId), { progressUrl: resolvedProgressUrl, containerId: container.id });

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
        const key = String(lessonId);
        if (this.pollingIntervals.has(key)) {
            clearInterval(this.pollingIntervals.get(key));
        }

        const intervalId = setInterval(() => {
            if (typeof player.getCurrentTime !== 'function' || typeof player.getDuration !== 'function') {
                return;
            }

            const watchedSeconds = Math.floor(player.getCurrentTime());
            const durationSeconds = Math.floor(player.getDuration());

            if (durationSeconds > 0) {
                this.reportProgress(lessonId, watchedSeconds, durationSeconds);
            }
        }, 5000);

        this.pollingIntervals.set(key, intervalId);
    }

    stopPolling(lessonId) {
        const key = String(lessonId);
        if (this.pollingIntervals.has(key)) {
            clearInterval(this.pollingIntervals.get(key));
            this.pollingIntervals.delete(key);
        }
    }

    /**
     * POSTs watched/duration seconds to `lessons.progress` for the given lesson.
     * Public test hook and progress reporter seam for E2E tests.
     */
    async reportProgress(lessonId, watchedSeconds, durationSeconds) {
        const progressUrl = this.resolveProgressUrl(lessonId);
        if (!progressUrl) return;

        try {
            const response = await this.httpClient.post(progressUrl, {
                watched_seconds: Number(watchedSeconds),
                duration_seconds: Number(durationSeconds),
            });

            if (response.data && response.data.is_completed) {
                this.stopPolling(lessonId);
                this.reflectCompletion(response.data);
            }

            return response.data;
        } catch (error) {
            this.notify('error', `Falha ao registrar progresso: ${error.message || 'Erro inesperado'}`);
            throw error;
        }
    }

    resolveProgressUrl(lessonId) {
        const entry = this.videoPlayers.get(String(lessonId));
        if (entry && entry.progressUrl) {
            return entry.progressUrl;
        }

        const container = document.querySelector(
            `[data-youtube-player][data-lesson-id="${lessonId}"], [data-lesson-id="${lessonId}"][data-progress-url], #youtube-player-${lessonId}, [dusk="video-player-${lessonId}"]`
        );

        if (container && container.getAttribute('data-progress-url')) {
            return container.getAttribute('data-progress-url');
        }

        return `/lessons/${lessonId}/progress`;
    }

    reflectCompletion(data) {
        if (!data || !data.is_completed) return;

        // Visibility is expressed with Bootstrap's `.d-none` / `.ds-hidden` utility.
        // DO NOT use native HTML `hidden` attribute: Bootstrap Reboot has
        // `[hidden] { display: none !important }` which overrides author declarations.

        // Hide mark complete buttons
        const buttonSelectors = [
            '[data-mark-complete-url]',
            '[data-action="complete-lesson"]',
            '[dusk="mark-complete-button"]',
            '[data-element="mark-complete-button"]',
        ];
        document.querySelectorAll(buttonSelectors.join(',')).forEach((button) => {
            button.classList.add('d-none');
            button.classList.add('ds-hidden');
        });

        // Show completion badges
        const badgeSelectors = [
            '[data-completion-badge]',
            '[data-element="completed-badge"]',
            '[dusk="lesson-completed-badge"]',
            '[data-element="completion-badge"]',
        ];
        document.querySelectorAll(badgeSelectors.join(',')).forEach((badge) => {
            badge.classList.remove('d-none');
            badge.classList.remove('ds-hidden');
        });
    }

    notify(type, message) {
        if (!this.notificationService || typeof this.notificationService[type] !== 'function') return;
        this.notificationService[type](message);
    }
}

export default LessonPlayer;
