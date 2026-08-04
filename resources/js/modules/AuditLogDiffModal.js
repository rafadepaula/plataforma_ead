/**
 * AuditLogDiffModal - SOLID JavaScript module backing the "Ver diff"
 * button on `resources/views/audit-logs/index.blade.php` (SPEC-15 §5).
 *
 * A single shared `#audit-diff-modal` (see
 * `resources/views/audit-logs/partials/_diff-modal.blade.php`) is reused
 * by every row rather than rendering one modal per row — each 25-row
 * page inlines its own `old_values`/`new_values` JSON as
 * `data-old-values`/`data-new-values` attributes on the triggering
 * button, so no AJAX round trip is needed. This module's only job is to
 * read those attributes on click, pretty-print them into the shared
 * modal's body, then delegate the actual open to `window.ModalManager`
 * (already bound globally to `[data-modal-target]` clicks) — following
 * the same "modal starts hidden because Alpine.js is not installed"
 * fix used by `ForumEditHistory.js`.
 */
export class AuditLogDiffModal {
    constructor(modalManager) {
        this.modalManager = modalManager;
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
        this.hideBackdrop();

        document.querySelectorAll('[data-audit-diff-trigger]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                this.render(button);

                const targetId = button.getAttribute('data-modal-target');
                if (targetId && this.modalManager) {
                    this.modalManager.open(targetId);
                }
            });
        });
    }

    render(button) {
        const modal = document.getElementById('audit-diff-modal');
        if (!modal) return;

        const eventLabel = modal.querySelector('[dusk="audit-diff-event"]');
        const oldValues = modal.querySelector('[dusk="audit-diff-old"]');
        const newValues = modal.querySelector('[dusk="audit-diff-new"]');

        if (eventLabel) {
            eventLabel.textContent = button.getAttribute('data-event') || '';
        }

        if (oldValues) {
            oldValues.textContent = this.formatJson(button.getAttribute('data-old-values'));
        }

        if (newValues) {
            newValues.textContent = this.formatJson(button.getAttribute('data-new-values'));
        }
    }

    formatJson(rawValue) {
        if (!rawValue) return '—';

        try {
            return JSON.stringify(JSON.parse(rawValue), null, 2);
        } catch (error) {
            return rawValue;
        }
    }

    hideBackdrop() {
        const modal = document.getElementById('audit-diff-modal');
        if (!modal) return;

        const backdrop = modal.closest('.dialog-backdrop');
        if (backdrop) backdrop.style.display = 'none';
    }
}

export default AuditLogDiffModal;
