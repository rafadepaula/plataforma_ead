/**
 * NotificationService — fachada pública de toasts, reimplementada sobre
 * `bootstrap.Toast` (bootstrap-conventions §9).
 *
 * A ASSINATURA PÚBLICA É CONTRATO e não muda: `show(message, type, options)`,
 * `success/error/warning/info(message, options)`, `dismiss(element)` e
 * `getOrCreateContainer()`. Seis módulos recebem este singleton por injeção
 * (`ForumReportModal`, `LessonPlayer`, `ModuleReorder`, `QuizBuilder`,
 * `SmartInvitationForm`, e o registry em `modules/index.js`), e a suíte Dusk
 * chama `window.NotificationService.success(...)`.
 *
 * Zero `style=` gerado por JS: o tom vem das classes tonais `.ds-tone-*` do
 * design system (mesmas de `x-ui.alert`/`x-ui.badge`) sobre o widget
 * `bootstrap.Toast`.
 */
export class NotificationService {
    constructor(containerId = 'notification-container') {
        this.containerId = containerId;
        this.container = null;
    }

    /**
     * Resolve o container único de toasts. Normalmente ele já vem renderizado
     * por `<x-layout.alerts>`; telas standalone (landing, verificação pública)
     * não têm o componente, então criamos o container sob demanda com as
     * mesmas classes.
     */
    getOrCreateContainer() {
        if (this.container && document.body.contains(this.container)) {
            return this.container;
        }

        let container = document.getElementById(this.containerId);

        if (!container) {
            container = document.createElement('div');
            container.id = this.containerId;
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            document.body.appendChild(container);
        }

        this.container = container;

        return container;
    }

    /**
     * Mapeia o tipo lógico para o par tonal container/on-container do design
     * system (`.ds-tone-*`, definido em `_chip.scss`). Nunca `text-bg-*`:
     * bloco sólido com texto branco foge do sistema pastel de superfícies.
     */
    resolveVariantClass(type) {
        switch (type) {
            case 'success':
                return 'ds-tone-success';
            case 'error':
            case 'danger':
                return 'ds-tone-critical';
            case 'warning':
                return 'ds-tone-attention';
            case 'info':
                return 'ds-tone-info';
            default:
                return 'ds-tone-primary';
        }
    }

    /**
     * @param {string} message
     * @param {string} type    info | success | error | danger | warning
     * @param {{duration?: number}} options `duration` 0 desliga o auto-dismiss.
     * @returns {HTMLElement} o elemento `.toast` criado.
     */
    show(message, type = 'info', options = {}) {
        const container = this.getOrCreateContainer();
        const duration = options.duration ?? 5000;

        const toast = document.createElement('div');
        toast.className = `toast align-items-center border-0 ${this.resolveVariantClass(type)}`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', type === 'error' || type === 'danger' ? 'assertive' : 'polite');
        toast.setAttribute('aria-atomic', 'true');

        const flex = document.createElement('div');
        flex.className = 'd-flex';

        const body = document.createElement('div');
        body.className = 'toast-body';
        body.textContent = message;

        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        // Superfície tonal é clara: o glifo escuro padrão do `btn-close` é o
        // correto (`btn-close-white` era par do bloco sólido `text-bg-*`).
        closeBtn.className = 'btn-close me-2 m-auto';
        closeBtn.setAttribute('data-bs-dismiss', 'toast');
        closeBtn.setAttribute('aria-label', 'Fechar');

        flex.appendChild(body);
        flex.appendChild(closeBtn);
        toast.appendChild(flex);
        container.appendChild(toast);

        // Remove o nó do DOM quando o Bootstrap terminar de escondê-lo, para o
        // container não acumular toasts mortos.
        toast.addEventListener('hidden.bs.toast', () => toast.remove());

        // `animation: false` é deliberado: o fade do Bootstrap mantém
        // `.toast.showing { opacity: 0 }` por 150ms, o que torna a asserção de
        // texto no Dusk dependente de timing. Sem animação o toast é visível no
        // mesmo tick em que `show()` retorna.
        const instance = window.bootstrap.Toast.getOrCreateInstance(toast, {
            animation: false,
            autohide: duration > 0,
            delay: duration > 0 ? duration : 5000,
        });

        instance.show();

        return toast;
    }

    success(message, options = {}) {
        return this.show(message, 'success', options);
    }

    error(message, options = {}) {
        return this.show(message, 'error', options);
    }

    warning(message, options = {}) {
        return this.show(message, 'warning', options);
    }

    info(message, options = {}) {
        return this.show(message, 'info', options);
    }

    /**
     * @param {HTMLElement} toastElement elemento devolvido por `show()`.
     */
    dismiss(toastElement) {
        if (!toastElement || !toastElement.parentElement) {
            return;
        }

        window.bootstrap.Toast.getOrCreateInstance(toastElement).hide();
    }
}

export default new NotificationService();
