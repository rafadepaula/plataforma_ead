/**
 * QuizTimer - SOLID JavaScript module for the student-facing
 * quiz countdown shown on `resources/views/student/quizzes/show.blade.php`
 * (`[data-quiz-timer]`, only rendered when `quizzes.time_limit_minutes` is
 * set).
 *
 * Purely cosmetic. Real `time_limit_minutes` enforcement (accept-but-fail,
 * an over-limit submission is never blocked) happens
 * server-side in `SubmitQuizAttemptAction`, computed on read from
 * `started_at`/`completed_at`/`time_limit_minutes` — this module never
 * calls `submit()` on the form itself when the countdown reaches zero, it
 * only flips to a "Tempo esgotado" visual state, so a slow network or a
 * background tab never silently discards the student's answers.
 *
 * The expired state is painted with the design-system `ds-tone-attention`
 * class (never an inline `style`), which resolves to the
 * `--attention-container`/`--on-attention-container` token pair (see
 * `resources/scss/components/_chip.scss`) — never red/orange/yellow, per
 * the design system's ban on those hues. The timer element is a plain
 * `<span>` styled with the same border utilities as an `<x-ui.badge
 * variant="outline">`, but it is NOT that component: `.badge` forces
 * `text-transform: uppercase`, and Selenium asserts on the rendered text
 * ("Tempo esgotado"), so the element carries no `text-bg-*`/badge class of
 * its own and this toggle cannot collide — see the contract comment at the
 * top of `resources/views/student/quizzes/show.blade.php`.
 */
export class QuizTimer {
    /** Design-system tone class applied while the countdown is expired. */
    static EXPIRED_CLASS = 'ds-tone-attention';

    constructor() {
        this.intervalId = null;
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
            if (this.intervalId) clearInterval(this.intervalId);
            return;
        }

        container.classList.remove(QuizTimer.EXPIRED_CLASS);
        container.textContent = this.formatRemaining(remainingMs);
    }

    formatRemaining(remainingMs) {
        const totalSeconds = Math.floor(remainingMs / 1000);
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }
}

export default QuizTimer;
