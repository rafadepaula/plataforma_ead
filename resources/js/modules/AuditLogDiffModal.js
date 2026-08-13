/**
 * AuditLogDiffModal - SOLID JavaScript module backing the "Ver diff"
 * button on `resources/views/audit-logs/index.blade.php` (SPEC-15 §5).
 *
 * A single shared `#audit-diff-modal` (see
 * `resources/views/audit-logs/partials/_diff-modal.blade.php`) is reused
 * by every row rather than rendering one modal per row — each 25-row
 * page inlines its own `old_values`/`new_values` JSON as
 * `data-old-values`/`data-new-values` attributes on the triggering
 * button, so no AJAX round trip is needed.
 *
 * Because the body must be filled BEFORE the modal is shown, the trigger
 * cannot be a plain `data-bs-toggle="modal"`: this module renders the
 * clicked row's JSON and then opens the modal imperatively through
 * `bootstrap.Modal.getOrCreateInstance()` (never `new`, per
 * `bootstrap-conventions` §9). `ModalManager` and the `.dialog-backdrop`
 * display toggling it required are gone — a `.modal.fade` without
 * `.show` is already hidden by Bootstrap's own CSS.
 */
export class AuditLogDiffModal {
    init() {
        if (typeof document === 'undefined') return;

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.bind());
        } else {
            this.bind();
        }
    }

    bind() {
        document.querySelectorAll('[data-audit-diff-trigger]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                this.render(button);
                this.open(button);
            });
        });
    }

    /**
     * Resolves the shared modal element from the trigger's target
     * attribute, tolerating both the Bootstrap (`data-bs-target="#id"`)
     * and the legacy (`data-modal-target="id"`) spellings.
     */
    resolveModal(button) {
        const bsTarget = button?.getAttribute('data-bs-target');
        if (bsTarget) return document.querySelector(bsTarget);

        const legacyTarget = button?.getAttribute('data-modal-target');
        return document.getElementById(legacyTarget || 'audit-diff-modal');
    }

    open(button) {
        const modal = this.resolveModal(button);
        if (!modal || !window.bootstrap) return;

        window.bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    render(button) {
        const modal = this.resolveModal(button);
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
}

export default AuditLogDiffModal;
