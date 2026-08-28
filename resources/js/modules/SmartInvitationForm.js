/**
 * SmartInvitationForm - Adaptive invitation registration form module.
 * Checks email existence asynchronously and collapses registration fields
 * for already registered users.
 */
export class SmartInvitationForm {
    constructor(httpClient, notificationService) {
        this.httpClient = httpClient;
        this.notificationService = notificationService;
        this.debounceTimer = null;
        this.debounceMs = 400;
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

    async checkEmail(form, emailField) {
        const url = form.getAttribute('data-check-email-url') || form.dataset.checkEmailUrl;
        const email = emailField ? emailField.value.trim() : '';
        const isValidEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

        if (!url || !email || !isValidEmail) {
            this.toggleFields(form, false);
            return;
        }

        try {
            const response = await this.httpClient.post(url, { email });
            const exists = Boolean(response.data && response.data.exists);
            this.toggleFields(form, exists);
        } catch (error) {
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
        hintElements.forEach((el) => {
            el.classList.toggle('d-none', !exists);
        });

        const newAccountFieldSelectors = [
            '[data-invitation-field="new-account"]',
            '[data-invitation-field="name"]',
            '[data-invitation-field="cpf"]',
            '[data-invitation-field="password_confirmation"]'
        ];
        const fieldWrappers = form.querySelectorAll(newAccountFieldSelectors.join(', '));
        fieldWrappers.forEach((field) => {
            field.classList.toggle('d-none', exists);
        });

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

    notify(type, message) {
        if (!this.notificationService || typeof this.notificationService[type] !== 'function') return;
        this.notificationService[type](message);
    }
}

export default SmartInvitationForm;
