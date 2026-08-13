/**
 * QuizTimer - SPEC-08 RF09 SOLID JavaScript module for the student-facing
 * quiz countdown shown on `resources/views/student/quizzes/show.blade.php`
 * (`[data-quiz-timer]`, only rendered when `quizzes.time_limit_minutes` is
 * set).
 *
 * Purely cosmetic. Real `time_limit_minutes` enforcement (accept-but-fail,
 * per SPEC-08 §1.3 — an over-limit submission is never blocked) happens
 * server-side in `SubmitQuizAttemptAction`, computed on read from
 * `started_at`/`completed_at`/`time_limit_minutes` — this module never
 * calls `submit()` on the form itself when the countdown reaches zero, it
 * only flips to a "Tempo esgotado" visual state, so a slow network or a
 * background tab never silently discards the student's answers.
 *
 * The expired state is painted with the Bootstrap `text-bg-danger` utility
 * (never an inline `style`). The timer element is a `<x-ui.badge
 * variant="outline">` precisely so it carries no `text-bg-*` class of its
 * own and this toggle cannot collide — see the contract comment at the top
 * of `resources/views/student/quizzes/show.blade.php`.
 */
export class QuizTimer {
    /** Bootstrap utility class applied while the countdown is expired. */
    static EXPIRED_CLASS = 'text-bg-danger';

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
