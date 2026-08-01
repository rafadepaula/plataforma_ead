/**
 * NotificationService - SOLID JavaScript module for toast & alert notification management
 */
export class NotificationService {
    constructor(containerId = 'notification-container') {
        this.containerId = containerId;
        this.container = null;
    }

    getOrCreateContainer() {
        if (this.container && document.body.contains(this.container)) {
            return this.container;
        }

        let container = document.getElementById(this.containerId);
        if (!container) {
            container = document.createElement('div');
            container.id = this.containerId;
            container.className = 'notification-container position-fixed top-0 end-0 p-3';
            container.style.cssText = 'position: fixed; top: 1rem; right: 1rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem; max-width: 400px; width: 100%; pointer-events: none;';
            document.body.appendChild(container);
        }
        this.container = container;
        return container;
    }

    show(message, type = 'info', options = {}) {
        const container = this.getOrCreateContainer();
        const duration = options.duration ?? 5000;

        const toast = document.createElement('div');
        toast.className = `toast-notification alert-item variant-${type}`;
        toast.style.cssText = 'pointer-events: auto; padding: 12px 16px; background: var(--color-surface, #eae9e9); border: 1px solid var(--color-divider, #201e1d); border-left: 4px solid var(--color-accent, #ec3013); color: var(--color-text, #201e1d); font-family: var(--font-body, sans-serif); box-shadow: var(--shadow-md, 0 3px 10px rgba(0,0,0,0.16)); display: flex; align-items: center; justify-content: space-between; gap: 12px; transition: opacity 0.3s ease, transform 0.3s ease; opacity: 0; transform: translateY(-10px); border-radius: 0px;';

        if (type === 'success') {
            toast.style.borderLeftColor = 'var(--color-accent, #ec3013)';
        } else if (type === 'error' || type === 'danger' || type === 'warning') {
            toast.style.borderLeftColor = 'var(--color-accent-2, #e15b47)';
        }

        const textSpan = document.createElement('span');
        textSpan.className = 'notification-message';
        textSpan.textContent = message;
        toast.appendChild(textSpan);

        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'btn-close';
        closeBtn.style.cssText = 'background: none; border: none; cursor: pointer; font-size: 16px; font-weight: bold; color: inherit; padding: 0 4px; line-height: 1; border-radius: 0px;';
        closeBtn.innerHTML = '&times;';
        closeBtn.setAttribute('aria-label', 'Fechar');
        closeBtn.addEventListener('click', () => this.dismiss(toast));
        toast.appendChild(closeBtn);

        container.appendChild(toast);

        requestAnimationFrame(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0)';
        });

        if (duration > 0) {
            setTimeout(() => {
                this.dismiss(toast);
            }, duration);
        }

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

    dismiss(toastElement) {
        if (!toastElement || !toastElement.parentElement) return;
        toastElement.style.opacity = '0';
        toastElement.style.transform = 'translateY(-10px)';
        setTimeout(() => {
            if (toastElement.parentElement) {
                toastElement.parentElement.removeChild(toastElement);
            }
        }, 300);
    }
}

export default new NotificationService();
