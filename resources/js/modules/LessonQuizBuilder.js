/**
 * LessonQuizBuilder - builder de questões do quiz EMBUTIDO no formulário
 * de lição (`modules/lessons/_form.blade.php`, seção
 * `[data-lesson-quiz-fields]`). O quiz é gerenciado 100% pela tela da
 * lição: as questões são blocos repetíveis no mesmo form, enviadas no
 * MESMO submit da lição (`questions[...]` + `questions[...][options][...]`,
 * persistidos por `SaveQuizForLessonAction`).
 *
 * Comportamento espelha o `QuizBuilder.js` dos modais de questão
 * (quizzes/edit), estendido para múltiplas questões:
 *  - `type = essay` esconde a UI de opções da questão (e desabilita os
 *    inputs, para nada de `options[]` vazar no POST);
 *  - no máximo 1 opção correta para `single_choice`/`true_false`
 *    (`multiple_choice` deixa os checkboxes independentes);
 *  - adicionar/remover opções (mínimo 2 por questão de escolha) e
 *    adicionar/remover/mover questões;
 *  - após qualquer adição/remoção/movimentação, TODAS as questões são
 *    reindexadas na ordem do DOM (`questions[i]` / `...[options][j]`), de
 *    modo que a ordem enviada é a ordem visual — o servidor usa o índice
 *    do payload como `order_index`.
 *
 * Remover uma questão/opção apenas a derruba do DOM: o servidor deleta
 * as linhas persistidas que não vierem no payload (upsert por `id`,
 * "delete what is no longer present").
 */
const QUESTION_NAME = /^questions\[[^\]]*\]/;
const OPTION_NAME = /\[options\]\[[^\]]*\]/;

const TYPE_SELECT = '[data-lesson-question-type]';
const ESSAY_HINT = '[data-lesson-essay-hint]';
const OPTIONS_CONTAINER = '[data-lesson-options]';
const ADD_OPTION_WRAPPER = '[data-add-option-wrapper]';

export class LessonQuizBuilder {
    init() {
        if (typeof document === 'undefined') return;

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.bind());
        } else {
            this.bind();
        }
    }

    bind() {
        const scope = document.querySelector('[data-lesson-quiz-fields]');
        if (!scope) return;

        this.applyAllTypeBehavior();

        scope.addEventListener('change', (event) => {
            const typeSelect = event.target.closest(TYPE_SELECT);
            if (typeSelect) {
                this.applyTypeBehavior(typeSelect.closest('[data-lesson-question]'));
                return;
            }

            const checkbox = event.target.closest('[data-correct-checkbox]');
            if (checkbox) {
                this.enforceSingleCorrect(checkbox.closest('[data-lesson-question]'), checkbox);
            }
        });

        scope.addEventListener('click', (event) => {
            const addQuestion = event.target.closest('[data-add-question]');
            if (addQuestion) {
                event.preventDefault();
                this.addQuestion();
                return;
            }

            const removeQuestion = event.target.closest('[data-remove-question]');
            if (removeQuestion) {
                event.preventDefault();
                this.removeQuestion(removeQuestion);
                return;
            }

            const moveUp = event.target.closest('[data-move-question-up]');
            if (moveUp) {
                event.preventDefault();
                this.moveQuestion(moveUp.closest('[data-lesson-question]'), 'up');
                return;
            }

            const moveDown = event.target.closest('[data-move-question-down]');
            if (moveDown) {
                event.preventDefault();
                this.moveQuestion(moveDown.closest('[data-lesson-question]'), 'down');
                return;
            }

            const addOption = event.target.closest('[data-add-option]');
            if (addOption) {
                event.preventDefault();
                this.addOption(addOption.closest('[data-lesson-question]'));
                return;
            }

            const removeOption = event.target.closest('[data-remove-option]');
            if (removeOption) {
                event.preventDefault();
                this.removeOption(removeOption);
            }
        });
    }

    // ------------------------------------------------------------------
    // Questões
    // ------------------------------------------------------------------

    questionsList() {
        return document.querySelector('[data-lesson-questions]');
    }

    questionBlocks() {
        const list = this.questionsList();

        return list ? Array.from(list.querySelectorAll('[data-lesson-question]')) : [];
    }

    addQuestion() {
        const list = this.questionsList();
        const template = document.querySelector('template[data-lesson-question-template]');
        if (!list || !template) return;

        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.trim();
        const block = wrapper.firstElementChild;
        if (!block) return;

        list.appendChild(block);
        this.applyTypeBehavior(block);
        this.reindex();
        block.querySelector('textarea, input[type="text"]')?.focus();
    }

    removeQuestion(removeButton) {
        const block = removeButton.closest('[data-lesson-question]');
        if (!block) return;

        block.remove();
        this.reindex();
    }

    moveQuestion(block, direction) {
        if (!block) return block;

        if (direction === 'up' && block.previousElementSibling) {
            block.previousElementSibling.before(block);
        }

        if (direction === 'down' && block.nextElementSibling) {
            block.nextElementSibling.after(block);
        }

        this.reindex();

        return block;
    }

    // ------------------------------------------------------------------
    // Opções
    // ------------------------------------------------------------------

    addOption(block) {
        const template = block.querySelector('template[data-option-template]');
        const list = block.querySelector('[data-options-list]');
        if (!template || !list) return;

        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.trim();
        const row = wrapper.firstElementChild;
        if (!row) return;

        list.appendChild(row);
        this.applyRowState(row, block);
        this.reindex();
    }

    removeOption(removeButton) {
        const row = removeButton.closest('[data-option-row]');
        const block = removeButton.closest('[data-lesson-question]');
        if (!row || !block) return;

        const type = this.currentType(block);
        if (type !== 'multiple_choice' && block.querySelectorAll('[data-option-row]').length <= 2) {
            this.notify('warning', 'Uma questão precisa de ao menos 2 opções.');
            return;
        }

        row.remove();
        this.reindex();
    }

    // ------------------------------------------------------------------
    // Comportamento por tipo
    // ------------------------------------------------------------------

    applyAllTypeBehavior() {
        this.questionBlocks().forEach((block) => this.applyTypeBehavior(block));
    }

    applyTypeBehavior(block) {
        if (!block) return;

        const type = this.currentType(block);
        const isEssay = type === 'essay';
        const isTrueFalse = type === 'true_false';

        const container = block.querySelector(OPTIONS_CONTAINER);
        const hint = block.querySelector(ESSAY_HINT);
        const addWrapper = block.querySelector(ADD_OPTION_WRAPPER);

        if (container) container.classList.toggle('d-none', isEssay);
        if (hint) hint.classList.toggle('d-none', !isEssay);
        if (addWrapper) addWrapper.classList.toggle('d-none', isTrueFalse);

        block.querySelectorAll('[data-option-row]').forEach((row) => this.applyRowState(row, block));
    }

    applyRowState(row, block) {
        const type = this.currentType(block);
        const isEssay = type === 'essay';

        const checkbox = row.querySelector('[data-correct-checkbox]');
        const textInput = row.querySelector('input[type="text"]');
        const hiddenIdInput = row.querySelector('input[type="hidden"]');
        const removeButton = row.querySelector('[data-remove-option]');

        if (checkbox) {
            checkbox.disabled = isEssay;
            // Visual apenas: single_choice/true_false lêem como radio,
            // multiple_choice como checkbox — a exclusividade real fica em
            // `enforceSingleCorrect()`.
            checkbox.type = type === 'multiple_choice' ? 'checkbox' : 'radio';
            this.syncRowHighlight(row);
        }
        if (textInput) textInput.disabled = isEssay;
        // O hidden `id` de uma opção persistida é desabilitado no essay,
        // para o POST não carregar `options[]` junto de `type=essay`.
        if (hiddenIdInput) hiddenIdInput.disabled = isEssay;
        if (removeButton) removeButton.classList.toggle('d-none', type === 'true_false');
    }

    currentType(block) {
        const select = block ? block.querySelector(TYPE_SELECT) : null;

        return select ? select.value : 'single_choice';
    }

    enforceSingleCorrect(block, changedCheckbox) {
        if (!block || this.currentType(block) === 'multiple_choice') return;
        if (!changedCheckbox.checked) return;

        block.querySelectorAll('[data-correct-checkbox]').forEach((checkbox) => {
            if (checkbox !== changedCheckbox) checkbox.checked = false;
        });
    }

    syncRowHighlight(row) {
        const checkbox = row.querySelector('[data-correct-checkbox]');
        row.classList.toggle('is-correct', Boolean(checkbox && checkbox.checked));
    }

    // ------------------------------------------------------------------
    // Reindexação
    // ------------------------------------------------------------------

    /**
     * Reescreve os `name` de todas as questões/opções na ordem do DOM:
     * `questions[i]...` e `...[options][j]...` — a ordem visual é a ordem
     * do payload e o índice vira `order_index` no servidor.
     */
    reindex() {
        this.questionBlocks().forEach((block, questionIndex) => {
            block.querySelectorAll('[name]').forEach((input) => {
                input.name = input.name.replace(QUESTION_NAME, `questions[${questionIndex}]`);
            });

            const badge = block.querySelector('[data-lesson-question-position]');
            if (badge) badge.textContent = String(questionIndex + 1);

            const isEssay = this.currentType(block) === 'essay';
            if (isEssay) return;

            block.querySelectorAll('[data-option-row]').forEach((row, optionIndex) => {
                row.querySelectorAll('[name]').forEach((input) => {
                    input.name = input.name.replace(OPTION_NAME, `[options][${optionIndex}]`);
                });
            });
        });
    }

    notify(type, message) {
        if (window.NotificationService && typeof window.NotificationService[type] === 'function') {
            window.NotificationService[type](message);
        }
    }
}

export default LessonQuizBuilder;
