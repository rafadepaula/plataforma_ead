/**
 * ModalManager - SOLID JavaScript module for modal open/close/backdrop handling
 */
export class ModalManager {
    constructor() {
        this.activeModals = [];
        this.init();
    }

    init() {
        if (typeof window === 'undefined' || typeof document === 'undefined') return;

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                this.hideBackdropsOnLoad();
                this.bindGlobalEvents();
            });
        } else {
            this.hideBackdropsOnLoad();
            this.bindGlobalEvents();
        }
    }

    /**
     * `x-ui.modal`'s backdrop ships with a static inline `display: flex`
     * and relies on Alpine.js's `x-show="show"` to hide itself until
     * opened — but Alpine.js is not installed in this project, so every
     * modal would otherwise render open by default. This hides every
     * backdrop once on load so pages don't need to duplicate this fix
     * in their own inline `@push('scripts')` blocks.
     */
    hideBackdropsOnLoad() {
        document.querySelectorAll('.dialog-backdrop').forEach((backdrop) => {
            backdrop.style.display = 'none';
        });
    }

    bindGlobalEvents() {
        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-modal-target]');
            if (trigger) {
                event.preventDefault();
                const targetId = trigger.getAttribute('data-modal-target');
                if (targetId) this.open(targetId);
            }

            const dismiss = event.target.closest('[data-modal-dismiss]');
            if (dismiss) {
                event.preventDefault();
                const modal = dismiss.closest('.dialog, [role="dialog"], .modal');
                if (modal && modal.id) {
                    this.close(modal.id);
                } else if (modal) {
                    this.closeElement(modal);
                } else {
                    this.closeTopModal();
                }
            }

            if (event.target.classList.contains('dialog-backdrop') || event.target.classList.contains('modal-backdrop')) {
                const topModal = this.activeModals[this.activeModals.length - 1];
                if (topModal) {
                    this.closeElement(topModal);
                }
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && this.activeModals.length > 0) {
                this.closeTopModal();
            }
        });
    }

    open(modalId) {
        const modal = typeof modalId === 'string' ? document.getElementById(modalId) : modalId;
        if (!modal) return;

        if (!this.activeModals.includes(modal)) {
            this.activeModals.push(modal);
        }

        modal.classList.add('active', 'open', 'show');
        modal.style.display = 'block';
        modal.setAttribute('aria-hidden', 'false');
        modal.setAttribute('aria-modal', 'true');

        const backdrop = modal.closest('.dialog-backdrop') || modal.parentElement;
        if (backdrop && backdrop.classList.contains('dialog-backdrop')) {
            backdrop.classList.add('active', 'show');
            backdrop.style.display = 'flex';
        }

        document.body.classList.add('modal-open');

        const focusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (focusable) {
            focusable.focus();
        }
    }

    close(modalId) {
        const modal = typeof modalId === 'string' ? document.getElementById(modalId) : modalId;
        if (modal) {
            this.closeElement(modal);
        }
    }

    closeElement(modal) {
        modal.classList.remove('active', 'open', 'show');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');

        const backdrop = modal.closest('.dialog-backdrop');
        if (backdrop) {
            backdrop.classList.remove('active', 'show');
            backdrop.style.display = 'none';
        }

        this.activeModals = this.activeModals.filter(m => m !== modal);

        if (this.activeModals.length === 0) {
            document.body.classList.remove('modal-open');
        }
    }

    closeTopModal() {
        const topModal = this.activeModals.pop();
        if (topModal) {
            this.closeElement(topModal);
        }
    }

    closeAll() {
        [...this.activeModals].forEach(modal => this.closeElement(modal));
        this.activeModals = [];
        document.body.classList.remove('modal-open');
    }

    toggle(modalId) {
        const modal = typeof modalId === 'string' ? document.getElementById(modalId) : modalId;
        if (!modal) return;

        const isVisible = modal.classList.contains('active') || modal.classList.contains('show') || modal.style.display === 'block';
        if (isVisible) {
            this.close(modalId);
        } else {
            this.open(modalId);
        }
    }
}

export default new ModalManager();
