/**
 * PasswordToggle - botão eye/eye-off que alterna a visibilidade de um campo
 * de senha (`resources/views/auth/login.blade.php`, diretriz das telas públicas).
 *
 * Opt-in por design: só ganha o botão o campo cujo wrapper carrega
 * `data-password-toggle-field`. Isso mantém todo outro `<x-ui.input
 * type="password">` do projeto (ex.: `profile/edit.blade.php`, já verde nas
 * Fases 4/5) inalterado — nenhum comportamento novo nasce por padrão.
 *
 * O botão nunca troca o `<svg>` do ícone em runtime (evitaria duplicar o
 * markup de `components/ui/icon.blade.php` aqui); em vez disso a view
 * renderiza os dois ícones (`eye`/`eye-off`) e este módulo só alterna
 * `d-none` entre eles, junto do `type`/`aria-label` do campo.
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
        document
            .querySelectorAll('[data-password-toggle-field]')
            .forEach((field) => this.bindField(field));
    }

    bindField(field) {
        const input = field.querySelector('input');
        const button = field.querySelector('[data-password-toggle-btn]');
        const showIcon = field.querySelector('[data-password-toggle-icon="show"]');
        const hideIcon = field.querySelector('[data-password-toggle-icon="hide"]');
        if (!input || !button) return;

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
