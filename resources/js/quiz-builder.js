/**
 * QuizBuilder - SPEC-08 RF08 SOLID JavaScript module for the Gestor-facing
 * dynamic question+options builder used by
 * `resources/views/quizzes/partials/_question-form.blade.php` (rendered
 * once per modal on `quizzes/edit.blade.php` — one "create" instance, one
 * "edit-{id}" instance per existing question, so every DOM query here is
 * scoped by the `[data-question-form]`/`data-*="{formSuffix}"` contract,
 * never a bare `document.querySelector` that would only ever hit the
 * first form on the page).
 *
 * Three independent responsibilities:
 * - Hide the entire options UI when `type=essay` is selected (RN11 —
 *   `quiz_options` does not apply to essay questions). The disabled
 *   options inputs are also given `disabled` so a browser never submits
 *   a stray `options[]` payload alongside `type=essay` — the server-side
 *   `StoreQuizQuestionRequest`/`UpdateQuizQuestionRequest` must still
 *   independently ignore/reject it, this is a UX nicety, not the
 *   validation boundary.
 * - Enforce "at most one correct option" for `single_choice`/`true_false`
 *   by unchecking siblings on click — `multiple_choice` leaves every
 *   `is_correct` checkbox independent (SPEC-08 §1.2's N >= 1 correct
 *   options rule).
 * - Add/remove option rows, cloning the form's own inert `<template>` and
 *   reindexing `options[__INDEX__]` names. Removing a row (persisted or
 *   not) simply drops it from the DOM — `QuizQuestionController::update()`
 *   deletes exactly the persisted options that are no longer present in
 *   the submitted `options[]` array (a diff against `options[*][id]`), so
 *   no separate "removed ids" payload is needed for this to work.
 */
export class QuizBuilder {
    constructor(notificationService) {
        this.notificationService = notificationService;
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
        document.querySelectorAll('[data-question-type-select]').forEach((select) => {
            const suffix = select.getAttribute('data-question-type-select');
            select.addEventListener('change', () => this.applyTypeBehavior(suffix));
            this.applyTypeBehavior(suffix);
        });

        document.querySelectorAll('[data-add-option-btn]').forEach((button) => {
            const suffix = button.getAttribute('data-add-option-btn');
            button.addEventListener('click', () => this.addOption(suffix));
        });

        document.querySelectorAll('[data-question-form]').forEach((form) => {
            form.addEventListener('click', (event) => {
                const removeButton = event.target.closest('[data-remove-option-btn]');
                if (removeButton) {
                    event.preventDefault();
                    this.removeOption(form, removeButton);
                }
            });

            form.addEventListener('change', (event) => {
                const checkbox = event.target.closest('[data-correct-checkbox]');
                if (checkbox) {
                    this.enforceSingleCorrect(form, checkbox);
                }
            });
        });
    }

    /**
     * @returns {'single_choice'|'multiple_choice'|'true_false'|'essay'}
     */
    currentType(suffix) {
        const select = document.querySelector(`[data-question-type-select="${suffix}"]`);
        return select ? select.value : 'single_choice';
    }

    applyTypeBehavior(suffix) {
        const type = this.currentType(suffix);
        const isEssay = type === 'essay';
        const isTrueFalse = type === 'true_false';

        const container = document.querySelector(`[data-options-container="${suffix}"]`);
        const hint = document.querySelector(`[data-essay-hint="${suffix}"]`);
        const addButton = document.querySelector(`[data-add-option-btn="${suffix}"]`);

        if (container) container.style.display = isEssay ? 'none' : 'flex';
        if (hint) hint.style.display = isEssay ? 'block' : 'none';
        if (addButton) addButton.style.display = isTrueFalse ? 'none' : 'inline-flex';

        const list = document.querySelector(`[data-options-list="${suffix}"]`);
        if (!list) return;

        list.querySelectorAll('[data-option-row]').forEach((row) => {
            const checkbox = row.querySelector('[data-correct-checkbox]');
            const textInput = row.querySelector('input[type="text"]');
            const hiddenIdInput = row.querySelector('input[type="hidden"]');
            const removeButton = row.querySelector('[data-remove-option-btn]');

            if (checkbox) checkbox.disabled = isEssay;
            if (textInput) textInput.disabled = isEssay;
            // A pre-existing option row carries a hidden `options[i][id]`
            // input (see `_question-form.blade.php`) that is never hidden
            // from the DOM by the essay branch above — left enabled, the
            // browser would still POST a non-empty `options` array
            // alongside `type=essay`, which `UpdateQuizQuestionRequest`'s
            // `'prohibited'` rule on `options` rejects.
            if (hiddenIdInput) hiddenIdInput.disabled = isEssay;
            if (removeButton) removeButton.style.display = isTrueFalse || isEssay ? 'none' : 'inline-flex';
        });
    }

    enforceSingleCorrect(form, changedCheckbox) {
        const suffix = form.getAttribute('data-question-form');
        const type = this.currentType(suffix);
        if (type === 'multiple_choice') return;
        if (!changedCheckbox.checked) return;

        form.querySelectorAll('[data-correct-checkbox]').forEach((checkbox) => {
            if (checkbox !== changedCheckbox) checkbox.checked = false;
        });
    }

    addOption(suffix) {
        const template = document.querySelector(`template[data-option-template="${suffix}"]`);
        const list = document.querySelector(`[data-options-list="${suffix}"]`);
        if (!template || !list) return;

        const nextIndex = list.querySelectorAll('[data-option-row]').length;
        const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));

        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const row = wrapper.firstElementChild;
        if (!row) return;

        this.applyRowDisabledState(row, suffix);
        list.appendChild(row);
    }

    applyRowDisabledState(row, suffix) {
        const isEssay = this.currentType(suffix) === 'essay';
        const checkbox = row.querySelector('[data-correct-checkbox]');
        const textInput = row.querySelector('input[type="text"]');
        const hiddenIdInput = row.querySelector('input[type="hidden"]');
        if (checkbox) checkbox.disabled = isEssay;
        if (textInput) textInput.disabled = isEssay;
        if (hiddenIdInput) hiddenIdInput.disabled = isEssay;
    }

    removeOption(form, removeButton) {
        const row = removeButton.closest('[data-option-row]');
        if (!row) return;

        const suffix = form.getAttribute('data-question-form');
        const list = document.querySelector(`[data-options-list="${suffix}"]`);
        if (list && list.querySelectorAll('[data-option-row]').length <= 2) {
            this.notify('warning', 'Uma questão precisa de ao menos 2 opções.');
            return;
        }

        // Simply dropping the row from the DOM is sufficient:
        // `QuizQuestionController::update()` deletes whatever persisted
        // option ids are no longer present in the submitted `options[]`
        // array, so a removed *persisted* option (`data-option-id`) is
        // deleted server-side without needing a dedicated payload field.
        row.remove();
    }

    notify(type, message) {
        if (!this.notificationService || typeof this.notificationService[type] !== 'function') return;
        this.notificationService[type](message);
    }
}

export default QuizBuilder;
