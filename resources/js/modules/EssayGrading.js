/**
 * EssayGrading - SOLID JavaScript module for the Gestor-facing manual
 * grading form on `resources/views/quizzes/attempts/show.blade.php`
 * (`dusk="grade-attempt-form"`, `data-grading-form`).
 *
 * The Blade template renders the static structure (one `.ds-verdict`
 * radio pair per essay `QuizAnswer`, already carrying the native
 * `required` attribute) plus the `data-grading-*` hooks documented in
 * `_grading-progress.scss`/`_verdict-choice.scss`; this module is what
 * makes the screen live — Blade alone cannot recompute a running count or
 * block a submit with a custom alert/focus/outline.
 *
 * Blade contract this module reads (see `quizzes/attempts/show.blade.php`):
 *   - `[data-grading-progress]`        the "N de M vereditos" + progress
 *                                      bar + ready-chip container (a
 *                                      sibling of the `<form>`, not inside
 *                                      it — there is exactly one per page).
 *       `[data-grading-progress-label]`   textContent becomes
 *                                         "X de Y vereditos".
 *       `.progress [data-progress-bar]`   the `<x-ui.progress>` component's
 *                                         own hook; `style.width` is kept
 *                                         in sync with the live percentage.
 *       `[data-grading-ready-chip]`       the "Pronto para salvar" badge;
 *                                         toggled via the `d-none` utility
 *                                         class (Bootstrap), not the
 *                                         `hidden` attribute.
 *   - `[data-grading-alert]`           the `x-ui.alert` wrapper, rendered
 *                                      with `d-none` already set; reused
 *                                      as-is (no bespoke toast is built
 *                                      here). `[data-grading-alert-question]`
 *                                      inside it receives the pending
 *                                      question's label (its `<h6>` text).
 *   - `[data-verdict-question]`        one per essay question, wrapping
 *                                      its `.ds-verdict` radiogroup;
 *                                      `data-verdict-input` marks each of
 *                                      the two radios inside it. The
 *                                      number of these blocks IS the
 *                                      denominator — objective questions
 *                                      never carry this attribute, so
 *                                      they never pad the count.
 *   - `.ds-verdict-option.is-selected` server sets it from the persisted
 *                                      verdict on first render; this
 *                                      module keeps it in sync with the
 *                                      checked radio afterwards.
 *   - `.ds-verdict.has-error`          the outline class `_verdict-
 *                                      choice.scss` paints red-free
 *                                      (`--critical`) for a question left
 *                                      unset at submit time.
 *
 * The form does not carry `novalidate` server-side (its radios keep the
 * native `required` attribute as the base-line guarantee), so this module
 * sets `form.noValidate = true` on bind. Without that, the browser's own
 * constraint-validation UI would intercept the `submit` event before this
 * module ever saw it, and the spec's alert/focus/outline UX could never
 * fire. This module's own check is a strict superset of what `required`
 * already guarantees, not a relaxation of it.
 */
export class EssayGrading {
    static READY_CLASS = 'has-error';

    constructor() {
        this.form = null;
        this.progressContainer = null;
        this.alertContainer = null;
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
        this.form = document.querySelector('[dusk="grade-attempt-form"]');
        if (!this.form) return;

        this.form.noValidate = true;

        this.progressContainer = document.querySelector('[data-grading-progress]');
        this.alertContainer = document.querySelector('[data-grading-alert]');

        this.form.querySelectorAll('[data-verdict-input]').forEach((radio) => {
            radio.addEventListener('change', () => this.handleVerdictChange(radio));
        });

        this.form.addEventListener('submit', (event) => this.guardSubmit(event));

        this.updateProgress();
    }

    /** @returns {HTMLElement[]} one per essay question rendered in the form. */
    getQuestionBlocks() {
        return Array.from(this.form.querySelectorAll('[data-verdict-question]'));
    }

    /** @param {HTMLElement} block @returns {HTMLInputElement[]} its 2 verdict radios. */
    getRadios(block) {
        return Array.from(block.querySelectorAll('[data-verdict-input]'));
    }

    isAnswered(block) {
        return this.getRadios(block).some((radio) => radio.checked);
    }

    /** @returns {HTMLElement|null} the first essay question with no verdict set yet. */
    firstPendingBlock() {
        return this.getQuestionBlocks().find((block) => !this.isAnswered(block)) ?? null;
    }

    /** Recomputes "X de Y vereditos", the progress bar and the ready chip. */
    updateProgress() {
        const blocks = this.getQuestionBlocks();
        const total = blocks.length;
        if (total === 0) return;

        const answered = blocks.filter((block) => this.isAnswered(block)).length;
        const pct = Math.round((answered / total) * 100);

        if (this.progressContainer) {
            this.progressContainer.setAttribute('data-grading-answered', String(answered));
            this.progressContainer.setAttribute('data-grading-total', String(total));

            const label = this.progressContainer.querySelector('[data-grading-progress-label]');
            if (label) {
                label.textContent = `${answered} de ${total} vereditos`;
            }

            const progressRoot = this.progressContainer.querySelector('.progress');
            if (progressRoot) {
                progressRoot.setAttribute('aria-valuenow', String(pct));
            }

            const bar = this.progressContainer.querySelector('[data-progress-bar]');
            if (bar) {
                bar.style.width = `${pct}%`;
            }

            const chip = this.progressContainer.querySelector('[data-grading-ready-chip]');
            if (chip) {
                chip.classList.toggle('d-none', answered !== total);
            }
        }
    }

    /**
     * Keeps `.ds-verdict-option.is-selected` (the mint/neutral card fill)
     * in sync with the checked radio for one question block.
     *
     * @param {HTMLElement} block
     */
    syncSelectedOption(block) {
        this.getRadios(block).forEach((radio) => {
            const option = radio.closest('.ds-verdict-option');
            if (option) {
                option.classList.toggle('is-selected', radio.checked);
            }
        });
    }

    /**
     * Runs on every verdict radio `change`: syncs the selected-card fill,
     * clears that question's own error outline, recomputes the progress
     * readout, and — once no pending question remains — hides the
     * submit-guard alert too.
     *
     * @param {HTMLInputElement} radio
     */
    handleVerdictChange(radio) {
        const block = radio.closest('[data-verdict-question]');
        if (block) {
            this.syncSelectedOption(block);

            const group = block.querySelector('.ds-verdict');
            if (group) {
                group.classList.remove(EssayGrading.READY_CLASS);
            }
        }

        this.updateProgress();

        if (!this.firstPendingBlock()) {
            this.hideAlert();
        }
    }

    /**
     * Supplements (never replaces) the native `required` attribute
     * already on every verdict radio: blocks the submit, surfaces the
     * `x-ui.alert` node naming the pending question, and
     * scrolls/focuses/outlines it — behavior HTML5 validation alone
     * cannot produce.
     *
     * @param {SubmitEvent} event
     */
    guardSubmit(event) {
        const pending = this.firstPendingBlock();
        if (!pending) return;

        event.preventDefault();

        const group = pending.querySelector('.ds-verdict');
        if (group) {
            group.classList.add(EssayGrading.READY_CLASS);
        }

        this.showAlert(pending);

        const target = group ?? pending;
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });

        const firstRadio = this.getRadios(pending)[0];
        if (firstRadio) {
            firstRadio.focus();
        }
    }

    /** @param {HTMLElement} pendingBlock the question the alert should name. */
    showAlert(pendingBlock) {
        if (!this.alertContainer) return;

        const questionLabel = this.alertContainer.querySelector('[data-grading-alert-question]');
        if (questionLabel) {
            const heading = pendingBlock.querySelector('h6');
            questionLabel.textContent = heading ? heading.textContent.trim() : '';
        }

        this.alertContainer.classList.remove('d-none');
    }

    hideAlert() {
        if (this.alertContainer) {
            this.alertContainer.classList.add('d-none');
        }
    }
}

export default EssayGrading;
