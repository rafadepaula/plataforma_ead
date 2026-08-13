/**
 * SmartInvitationForm - SOLID JavaScript module for RF03's adaptive
 * `/convite/{token}` public registration form. On blur (debounced) of the
 * e-mail field it POSTs to the `check-email` endpoint via the shared
 * `HttpClient` module and toggles the visibility (and `required`-ness) of
 * the name/CPF/password-confirmation fields based on the `{ exists }`
 * response — mirrors `ModuleReorder.js`'s constructor-injection style so
 * both modules can be unit-tested with fake `httpClient`/`notificationService`
 * doubles instead of touching the network or the DOM notification tree.
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
        const forms = document.querySelectorAll('[data-check-email-url]');
        forms.forEach((form) => this.bindForm(form));
    }

    bindForm(form) {
        const emailField = form.querySelector('[data-invitation-email]');
        if (!emailField) return;

        const handler = () => {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => this.checkEmail(form, emailField), this.debounceMs);
        };

        emailField.addEventListener('blur', () => this.checkEmail(form, emailField));
        emailField.addEventListener('input', handler);
    }

    async checkEmail(form, emailField) {
        const url = form.getAttribute('data-check-email-url');
        const email = emailField.value.trim();

        if (!url || !email) {
            this.toggleFields(form, false);
            return;
        }

        try {
            const response = await this.httpClient.post(url, { email });
            const exists = Boolean(response.data && response.data.exists);
            this.toggleFields(form, exists);
        } catch (error) {
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
        const newAccountFields = form.querySelectorAll('[data-invitation-field="new-account"]');

        newAccountFields.forEach((field) => {
            field.classList.toggle('d-none', exists);

            const input = field.matches('input, select, textarea') ? field : field.querySelector('input, select, textarea');
            if (input && input.dataset.originallyRequired !== 'false') {
                if (exists) {
                    input.dataset.originallyRequired = String(input.required);
                    input.required = false;
                } else if (input.dataset.originallyRequired !== undefined) {
                    input.required = input.dataset.originallyRequired === 'true';
                }
            }
        });

        const existingAccountHint = form.querySelector('[data-invitation-field="existing-account-hint"]');
        if (existingAccountHint) {
            existingAccountHint.classList.toggle('d-none', !exists);
        }
    }

    notify(type, message) {
        if (!this.notificationService || typeof this.notificationService[type] !== 'function') return;
        this.notificationService[type](message);
    }
}

export default SmartInvitationForm;
