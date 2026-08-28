export class QuizTaking {
    constructor() {
        this.form = null;
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
        this.form = document.querySelector('[data-quiz-attempt-form], [dusk="quiz-attempt-form"]');
        if (!this.form) return;

        this.bindOptionCards();
        this.bindSubmitConfirmation();
    }

    bindOptionCards() {
        const optionCards = this.form.querySelectorAll('.quiz-option-card, [data-quiz-option]');
        optionCards.forEach((card) => {
            const input = card.querySelector('input[type="radio"], input[type="checkbox"]');
            if (!input) return;

            if (card.tagName !== 'LABEL' && !card.closest('label')) {
                card.addEventListener('click', (event) => {
                    if (event.target !== input) {
                        input.checked = input.type === 'checkbox' ? !input.checked : true;
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            }

            input.addEventListener('change', () => {
                this.syncCardSelection(card, input);
            });
        });
    }

    syncCardSelection(card, input) {
        if (input.type === 'radio') {
            const groupName = input.name;
            const sameGroupInputs = this.form.querySelectorAll(`input[name="${groupName}"]`);
            sameGroupInputs.forEach((otherInput) => {
                const otherCard = otherInput.closest('.quiz-option-card, [data-quiz-option], label.list-group-item');
                if (otherCard) {
                    otherCard.classList.toggle('is-selected', otherInput.checked);
                    otherCard.classList.toggle('border-primary', otherInput.checked);
                }
            });
        } else {
            card.classList.toggle('is-selected', input.checked);
            card.classList.toggle('border-primary', input.checked);
        }
    }

    getQuestions() {
        if (!this.form) return [];
        return Array.from(this.form.querySelectorAll('[dusk^="quiz-question-"], [data-question-id]'));
    }

    isQuestionAnswered(questionEl) {
        const checkables = Array.from(questionEl.querySelectorAll('input[type="radio"], input[type="checkbox"]'));
        if (checkables.length > 0) {
            return checkables.some((input) => input.checked);
        }

        const essayTextarea = questionEl.querySelector('textarea, [dusk^="quiz-essay-"]');
        if (essayTextarea) {
            return essayTextarea.value.trim().length > 0;
        }

        return false;
    }

    getUnansweredQuestions() {
        return this.getQuestions().filter((q) => !this.isQuestionAnswered(q));
    }

    countUnanswered() {
        return this.getUnansweredQuestions().length;
    }

    countTotal() {
        return this.getQuestions().length;
    }

    bindSubmitConfirmation() {
        const submitBtn = this.form.querySelector('[dusk="quiz-attempt-submit"]');
        const modalEl = document.querySelector('[data-quiz-confirm-modal]');

        if (modalEl && submitBtn) {
            const countEl = modalEl.querySelector('[data-unanswered-count]');
            const totalEl = modalEl.querySelector('[data-total-count]');

            submitBtn.addEventListener('click', () => {
                const unanswered = this.countUnanswered();
                if (countEl) countEl.textContent = String(unanswered);
                if (totalEl) totalEl.textContent = String(this.countTotal());
            });
        }
    }
}

export default QuizTaking;
