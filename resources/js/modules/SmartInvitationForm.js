/**
 * SmartInvitationForm - Adaptive invitation registration form module.
 *
 * Checks the typed e-mail against `/convite/check-email` and collapses the
 * registration-only fields (nome/CPF/confirmação de senha) when the address
 * already belongs to an account, leaving a password-only prompt.
 *
 * Contrato de disparo: `blur` (imediato) E `input` (debounce de 400ms). A
 * anatomia de design fala em "disparo no blur"; a spec §3.2 exige os dois, e
 * a spec vence — o `input` é o que faz o veredito acompanhar a digitação
 * incremental (aluno que corrige o e-mail sem tirar o foco do campo).
 *
 * Contrato de visibilidade: mostrar/esconder é SEMPRE a classe `.d-none`.
 * Nunca o atributo `hidden`, nunca `style.display` — o servidor renderiza a
 * mesma tela sem JavaScript e valida condicionalmente, então o estado
 * "escondido" precisa ser exclusivamente a decisão desta classe.
 */
export class SmartInvitationForm {
    constructor(httpClient, notificationService) {
        this.httpClient = httpClient;
        this.notificationService = notificationService;
        this.debounceTimer = null;
        this.debounceMs = 400;
        this.formStates = new WeakMap();
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
        const forms = document.querySelectorAll(
            '[data-smart-invitation], form[data-check-email-url], [data-check-email-url]'
        );
        const uniqueForms = new Set(forms);
        uniqueForms.forEach((form) => this.bindForm(form));
    }

    bindForm(form) {
        if (form.dataset.smartInvitationBound === 'true') return;
        form.dataset.smartInvitationBound = 'true';

        const emailField = form.querySelector('[data-invitation-email], input[name="email"]');
        if (!emailField) return;

        this.formStates.set(form, { checkedEmail: null, sequence: 0 });

        // Estado inicial explícito: dica escondida, campos de cadastro
        // visíveis com o `required` que o servidor renderizou.
        this.toggleFields(form, false);

        const handleDebouncedInput = () => {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => this.checkEmail(form, emailField), this.debounceMs);
        };

        const handleImmediateCheck = () => {
            clearTimeout(this.debounceTimer);
            this.checkEmail(form, emailField);
        };

        emailField.addEventListener('blur', handleImmediateCheck);
        emailField.addEventListener('input', handleDebouncedInput);

        if (emailField.value && emailField.value.trim() !== '') {
            this.checkEmail(form, emailField);
        }
    }

    /**
     * Estado por formulário: qual e-mail já foi consultado (ou está em voo) e
     * o número de sequência da última consulta disparada.
     */
    stateFor(form) {
        if (!this.formStates.has(form)) {
            this.formStates.set(form, { checkedEmail: null, sequence: 0 });
        }

        return this.formStates.get(form);
    }

    async checkEmail(form, emailField) {
        const url = form.getAttribute('data-check-email-url') || form.dataset.checkEmailUrl;
        const email = emailField ? emailField.value.trim() : '';
        const isValidEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        const state = this.stateFor(form);

        // E-mail vazio, parcial ou malformado nunca consulta o servidor e
        // nunca colapsa nada: o formulário fica no estado de conta nova.
        if (!url || !email || !isValidEmail) {
            state.sequence += 1;
            state.checkedEmail = null;
            this.toggleFields(form, false);
            return;
        }

        // O mesmo endereço já foi consultado (ou está em voo): reconsultar
        // apenas repetiria o veredito. Esse curto-circuito é o que impede o
        // `input` com debounce de reexecutar `toggleFields` DEPOIS de o
        // `blur` já ter colapsado o formulário — corrida que restaurava
        // `required` num campo escondido e travava o submit.
        if (state.checkedEmail === email) return;

        state.checkedEmail = email;
        const sequence = ++state.sequence;

        try {
            const response = await this.httpClient.post(url, { email });

            // Resposta obsoleta (o usuário já digitou outro e-mail): descarta.
            if (sequence !== state.sequence) return;

            const exists = Boolean(response.data && response.data.exists);
            this.toggleFields(form, exists);
        } catch (error) {
            if (sequence !== state.sequence) return;

            // Falha de rede degrada para o estado de conta nova — todos os
            // campos visíveis e obrigatórios, exatamente o que o submit sem
            // JavaScript envia. `checkedEmail` volta a nulo para que uma
            // nova tentativa (blur seguinte) possa reconsultar.
            state.checkedEmail = null;
            this.toggleFields(form, false);
            this.notify('error', `Não foi possível verificar o e-mail: ${error.message}`);
        }
    }

    /**
     * Hides the registration-only fields (name/CPF/password confirmation)
     * when the e-mail already belongs to an existing account, showing only
     * the password field for authentication — and toggles `required` in
     * lockstep so a hidden field never blocks client-side submission.
     */
    toggleFields(form, exists) {
        const hintSelectors = [
            '[data-invitation-existing-hint]',
            '[data-invitation-field="existing-account-hint"]',
            '[dusk="invitation-existing-account-hint"]'
        ];
        const hintElements = form.querySelectorAll(hintSelectors.join(', '));
        hintElements.forEach((el) => this.applyVisibility(el, !exists));

        const newAccountFieldSelectors = [
            '[data-invitation-field="new-account"]',
            '[data-invitation-field="name"]',
            '[data-invitation-field="cpf"]',
            '[data-invitation-field="password_confirmation"]'
        ];
        const fieldWrappers = form.querySelectorAll(newAccountFieldSelectors.join(', '));
        fieldWrappers.forEach((field) => this.applyVisibility(field, exists));

        const inputSelectors = [
            '[data-invitation-field="new-account"] input, [data-invitation-field="new-account"] select, [data-invitation-field="new-account"] textarea',
            '[data-invitation-field="name"] input',
            '[data-invitation-field="cpf"] input',
            '[data-invitation-field="password_confirmation"] input',
            '[data-invitation-name]',
            '[data-invitation-cpf]',
            '[data-invitation-password-confirmation]',
            'input[name="name"]',
            'input[name="cpf"]',
            'input[name="password_confirmation"]'
        ];
        const inputs = form.querySelectorAll(inputSelectors.join(', '));
        const seenInputs = new Set();
        inputs.forEach((input) => {
            if (seenInputs.has(input)) return;
            seenInputs.add(input);

            if (input.dataset.originallyRequired === undefined) {
                input.dataset.originallyRequired = String(input.required);
            }

            if (exists) {
                input.required = false;
            } else {
                input.required = input.dataset.originallyRequired === 'true';
            }
        });
    }

    /**
     * Única porta de entrada de visibilidade do módulo: `.d-none` e nada
     * mais. Ao mostrar, desfaz também um `hidden`/`display:none` que outro
     * código porventura tenha deixado no nó, para que a classe permaneça a
     * fonte de verdade do estado.
     */
    applyVisibility(element, shouldHide) {
        element.classList.toggle('d-none', shouldHide);

        if (!shouldHide) {
            element.hidden = false;

            if (element.style && element.style.display === 'none') {
                element.style.removeProperty('display');
            }
        }
    }

    notify(type, message) {
        if (!this.notificationService || typeof this.notificationService[type] !== 'function') return;
        this.notificationService[type](message);
    }
}

export default SmartInvitationForm;
