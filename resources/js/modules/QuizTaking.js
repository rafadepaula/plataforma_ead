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
        this.bindRequiredCompletion();
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

    /**
     * Toda questão é obrigatória: "Finalizar prova" só é liberado quando a
     * última resposta é dada, e a dica de obrigatoriedade some junto. `input`
     * cobre a dissertativa (textarea), `change` cobre as objetivas — inclusive
     * a marcação disparada pelo clique no cartão. A checagem também roda no
     * bind, para um formulário repopulado por `old()` já nascer destravado.
     */
    bindRequiredCompletion() {
        const submitBtn = this.form.querySelector('[dusk="quiz-attempt-submit"]');
        if (!submitBtn) return;

        const hint = document.querySelector('[data-quiz-required-hint]');

        const refresh = () => {
            const complete = this.countUnanswered() === 0;
            submitBtn.disabled = !complete;
            if (hint) {
                hint.classList.toggle('d-none', complete);
            }
        };

        this.form.addEventListener('change', refresh);
        this.form.addEventListener('input', refresh);
        refresh();
    }
}

export default QuizTaking;
