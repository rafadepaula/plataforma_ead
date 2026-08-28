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
        this.syncAllCardSelections();
        this.bindSubmitConfirmation();
    }

    bindOptionCards() {
        const optionCards = this.getOptionCards();
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

    getOptionCards() {
        if (!this.form) return [];
        return Array.from(this.form.querySelectorAll('.quiz-option-card, [data-quiz-option]'));
    }

    /**
     * Reflete no cartão o estado atual do controle. O visual é responsabilidade
     * exclusiva de `.quiz-option-card.is-selected` no SCSS — nenhuma classe
     * utilitária do Bootstrap é dirigida por JS.
     */
    syncCardSelection(card, input) {
        if (input.type === 'radio') {
            const groupName = input.name;
            const sameGroupInputs = this.form.querySelectorAll(`input[name="${groupName}"]`);
            sameGroupInputs.forEach((otherInput) => {
                const otherCard = otherInput.closest('.quiz-option-card, [data-quiz-option], label.list-group-item');
                if (otherCard) {
                    otherCard.classList.toggle('is-selected', otherInput.checked);
                }
            });
        } else {
            card.classList.toggle('is-selected', input.checked);
        }
    }

    /**
     * Sincronização inicial: um formulário repopulado por `old()` já chega com
     * controles marcados, e o cartão precisa refletir isso sem clique do aluno.
     */
    syncAllCardSelections() {
        this.getOptionCards().forEach((card) => {
            const input = card.querySelector('input[type="radio"], input[type="checkbox"]');
            if (!input) return;

            card.classList.toggle('is-selected', input.checked);
        });
    }

    getQuestions() {
        if (!this.form) return [];
        return Array.from(this.form.querySelectorAll('[data-question-id], [dusk^="quiz-question-"]'));
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
        return this.getQuestions().filter((question) => !this.isQuestionAnswered(question));
    }

    countUnanswered() {
        return this.getUnansweredQuestions().length;
    }

    countTotal() {
        return this.getQuestions().length;
    }

    buildUnansweredMessage(unanswered) {
        if (unanswered === 0) {
            return 'Todas as questões foram respondidas.';
        }

        if (unanswered === 1) {
            return 'Uma questão ficou sem resposta.';
        }

        return `${unanswered} questões ficaram sem resposta.`;
    }

    /**
     * O modal de confirmação relê o DOM a cada abertura (`show.bs.modal`), e não
     * apenas no clique do gatilho, para que a contagem acompanhe qualquer
     * resposta dada entre uma abertura e outra.
     */
    bindSubmitConfirmation() {
        const modalEl = document.querySelector('[data-quiz-confirm-modal]');
        if (!modalEl) return;

        const refresh = () => this.refreshConfirmationSummary(modalEl);

        modalEl.addEventListener('show.bs.modal', refresh);

        const submitBtn = this.form.querySelector('[dusk="quiz-attempt-submit"]');
        if (submitBtn) {
            submitBtn.addEventListener('click', refresh);
        }

        refresh();
    }

    refreshConfirmationSummary(modalEl) {
        const unanswered = this.countUnanswered();
        const countEl = modalEl.querySelector('[data-unanswered-count]');
        const totalEl = modalEl.querySelector('[data-total-count]');
        const messageEl = modalEl.querySelector('[data-unanswered-message]');

        if (countEl) countEl.textContent = String(unanswered);
        if (totalEl) totalEl.textContent = String(this.countTotal());
        if (messageEl) messageEl.textContent = this.buildUnansweredMessage(unanswered);
    }
}

export default QuizTaking;
