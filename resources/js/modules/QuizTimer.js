export class QuizTimer {
    static EXPIRED_CLASS = 'ds-tone-attention';

    constructor() {
        this.intervalId = null;
        this.handleUnload = this.destroy.bind(this);
    }

    init() {
        if (typeof document === 'undefined') return;

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.bind());
        } else {
            this.bind();
        }

        window.addEventListener('beforeunload', this.handleUnload);
        window.addEventListener('pagehide', this.handleUnload);
    }

    bind() {
        this.destroy();

        const container = document.querySelector('[data-quiz-timer]');
        if (!container) return;

        const startedAt = container.getAttribute('data-started-at');
        const timeLimitMinutes = Number(container.getAttribute('data-time-limit-minutes'));
        if (!startedAt || !timeLimitMinutes) return;

        const deadline = new Date(startedAt).getTime() + timeLimitMinutes * 60 * 1000;

        this.tick(container, deadline);
        this.intervalId = setInterval(() => this.tick(container, deadline), 1000);
    }

    tick(container, deadline) {
        const remainingMs = deadline - Date.now();

        if (remainingMs <= 0) {
            container.textContent = 'Tempo esgotado';
            container.classList.add(QuizTimer.EXPIRED_CLASS);
            this.destroy();
            return;
        }

        container.classList.remove(QuizTimer.EXPIRED_CLASS);
        container.textContent = this.formatRemaining(remainingMs);
    }

    formatRemaining(remainingMs) {
        const totalSeconds = Math.max(0, Math.floor(remainingMs / 1000));
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }

    destroy() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
    }
}

export default QuizTimer;
