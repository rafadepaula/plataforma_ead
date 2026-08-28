/**
 * PasswordToggle - eye/eye-off toggle button for password visibility.
 * Supports binding via container ([data-password-toggle-field]) or directly
 * on the toggle button ([data-password-toggle], [data-password-toggle-btn]).
 */
export class PasswordToggle {
    init() {
        if (typeof document === 'undefined') return;

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.bind());
        } else {
            this.bind();
        }
    }

    bind() {
        const elements = document.querySelectorAll(
            '[data-password-toggle-field], [data-password-toggle], [data-password-toggle-btn]'
        );

        elements.forEach((el) => {
            if (el.tagName === 'BUTTON' || el.hasAttribute('data-password-toggle-btn') || (!el.hasAttribute('data-password-toggle-field') && el.hasAttribute('data-password-toggle'))) {
                this.bindButton(el);
            } else {
                this.bindField(el);
            }
        });
    }

    bindField(field) {
        const button = field.querySelector('[data-password-toggle], [data-password-toggle-btn], button');
        if (!button) return;
        this.bindButton(button, field);
    }

    bindButton(button, container = null) {
        if (button.dataset.passwordToggleBound === 'true') return;
        button.dataset.passwordToggleBound = 'true';

        const parent = container || button.closest('[data-password-toggle-field]') || button.parentElement;
        const input = parent ? parent.querySelector('input') : null;
        if (!input) return;

        const showIcon = parent.querySelector('[data-icon-show], [data-password-toggle-icon="show"]');
        const hideIcon = parent.querySelector('[data-icon-hide], [data-password-toggle-icon="hide"]');

        button.addEventListener('click', () => {
            const willReveal = input.type === 'password';
            input.type = willReveal ? 'text' : 'password';
            button.setAttribute('aria-label', willReveal ? 'Ocultar senha' : 'Mostrar senha');
            if (showIcon) showIcon.classList.toggle('d-none', willReveal);
            if (hideIcon) hideIcon.classList.toggle('d-none', !willReveal);
        });
    }
}

export default PasswordToggle;
