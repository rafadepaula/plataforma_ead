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
 * - UI updates: Toggles visibility using Bootstrap's `.d-none` utility class.
 *   NEVER uses the HTML `hidden` attribute due to Bootstrap's `[hidden] { display: none !important }`.
 */

/** Selectors that identify a manual completion button. */
const MARK_COMPLETE_SELECTORS = [
    '[data-mark-complete-url]',
    '[data-action="complete-lesson"]',
    '[dusk="mark-complete-button"]',
    '[data-element="mark-complete-button"]',
];

/** Selectors that identify the "lição concluída" badge. */
const COMPLETION_BADGE_SELECTORS = [
    '[data-completion-badge]',
    '[data-element="completed-badge"]',
    '[dusk="lesson-completed-badge"]',
    '[data-element="completion-badge"]',
];

/** Message shown when the student completes a lesson through the manual button. */
const COMPLETION_SUCCESS_MESSAGE = 'Lição concluída com sucesso.';

/**
 * Message shown when the video threshold completes the lesson on its own.
 * It is deliberately distinct from the manual one: the student clicked
 * nothing, so telling them "concluída com sucesso" would credit an action
 * they never took and hide that completion was automatic.
 */
const AUTO_COMPLETION_MESSAGE = 'Lição concluída automaticamente!';

/**
 * Consecutive progress-poll failures tolerated before the 5s interval is torn
 * down. A single blip must not stop tracking, but a persistent failure (session
 * expired, 5xx, enrollment cancelled mid-session) must never turn the screen
 * into an endless queue of identical error toasts.
 */
const MAX_PROGRESS_FAILURES = 3;

export class LessonPlayer {
    constructor(httpClient, notificationService) {
        this.httpClient = httpClient;
        this.notificationService = notificationService;
        this.videoPlayers = new Map();
        this.pollingIntervals = new Map();
        this.completedLessons = new Set();
        this.progressFailures = new Map();
        this.notifiedProgressErrors = new Set();
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
        const buttons = document.querySelectorAll(MARK_COMPLETE_SELECTORS.join(','));
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
            this.reflectCompletion(response.data, button ? button.dataset.lessonId : null);
            this.notify('success', COMPLETION_SUCCESS_MESSAGE);
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
                // The interval is not an `await` site: swallow the rejection
                // here so a failing poll never becomes an unhandled rejection.
                // `reportProgress()` already notified and, past the failure
                // budget, already cleared this interval.
                this.reportProgress(lessonId, watchedSeconds, durationSeconds).catch(() => {});
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

            this.progressFailures.delete(String(lessonId));
            this.notifiedProgressErrors.delete(String(lessonId));

            if (response.data && response.data.is_completed) {
                this.stopPolling(lessonId);
                this.reflectCompletion(response.data, lessonId);

                // The 90% auto-completion path announces itself instead of
                // silently swapping the DOM, but only once per lesson.
                if (!this.completedLessons.has(String(lessonId))) {
                    this.completedLessons.add(String(lessonId));
                    this.notify('success', AUTO_COMPLETION_MESSAGE);
                }
            }

            return response.data;
        } catch (error) {
            const key = String(lessonId);
            const failures = (this.progressFailures.get(key) || 0) + 1;
            this.progressFailures.set(key, failures);

            // One toast per failing lesson: the poll repeats every 5s and the
            // student does not need the same warning again on every beat.
            if (!this.notifiedProgressErrors.has(key)) {
                this.notifiedProgressErrors.add(key);
                this.notify('error', `Falha ao registrar progresso: ${error.message || 'Erro inesperado'}`);
            }

            if (failures >= MAX_PROGRESS_FAILURES) {
                this.stopPolling(lessonId);
            }

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

    /**
     * Narrows the DOM scope of a completion update to the container that holds
     * the given lesson, falling back to the whole document when the lesson has
     * no anchor element on the page.
     */
    resolveLessonScope(lessonId) {
        if (lessonId === undefined || lessonId === null || lessonId === '') {
            return document;
        }

        const anchor = document.querySelector(`[data-lesson-id="${lessonId}"]`);
        if (!anchor) return document;

        return anchor.closest('[data-lesson-container], .ds-lesson-card, .card, main') || document;
    }

    reflectCompletion(data, lessonId = null) {
        if (!data || !data.is_completed) return;

        // Visibility is expressed with Bootstrap's `.d-none` utility class.
        // DO NOT use native HTML `hidden` attribute: Bootstrap Reboot has
        // `[hidden] { display: none !important }` which overrides author declarations.
        const scope = this.resolveLessonScope(lessonId);

        // Hide mark complete buttons
        scope.querySelectorAll(MARK_COMPLETE_SELECTORS.join(',')).forEach((button) => {
            button.classList.add('d-none');
        });

        // Show completion badges
        scope.querySelectorAll(COMPLETION_BADGE_SELECTORS.join(',')).forEach((badge) => {
            badge.classList.remove('d-none');
        });
    }

    notify(type, message) {
        if (!this.notificationService || typeof this.notificationService[type] !== 'function') return;
        this.notificationService[type](message);
    }
}

export default LessonPlayer;
