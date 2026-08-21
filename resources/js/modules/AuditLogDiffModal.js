/**
 * AuditLogDiffModal - SOLID JavaScript module backing the "Ver diff"
 * button on `resources/views/audit-logs/index.blade.php` 
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

        const rawOld = button.getAttribute('data-old-values');
        const rawNew = button.getAttribute('data-new-values');
        const parsedOld = this.tryParseJson(rawOld);
        const parsedNew = this.tryParseJson(rawNew);

        if (oldValues) {
            this.renderPane(oldValues, rawOld, parsedOld, parsedNew);
        }

        if (newValues) {
            this.renderPane(newValues, rawNew, parsedNew, parsedOld);
        }
    }

    /**
     * Fills one `<pre>` pane of the diff modal.
     *
     * When BOTH sides parse as plain JSON objects, each `key: value` pair
     * is rendered on its own line so the keys whose value differs from the
     * OTHER pane's value for that same key can be flagged as changed. Per
     * the accessibility guideline's "color is never the only signal" rule, a changed line is
     * NOT colored — it is marked with weight (`fw-semibold`) and a
     * container (`border-start border-3 ps-2`, all pre-existing Bootstrap
     * utility classes) plus a `diff-changed` class used purely as a test
     * hook, no new CSS class is introduced.
     *
     * Anything that is not a plain object on both sides (arrays, scalars,
     * unparsed/invalid JSON) falls back to the original pretty-printed
     * plain-text rendering — no diffing is attempted.
     */
    renderPane(paneEl, rawValue, parsed, otherParsed) {
        paneEl.textContent = '';

        if (!rawValue) {
            paneEl.textContent = '—';
            return;
        }

        if (!this.isPlainObject(parsed) || !this.isPlainObject(otherParsed)) {
            paneEl.textContent = this.formatJson(rawValue);
            return;
        }

        const keys = Object.keys(parsed);

        if (keys.length === 0) {
            paneEl.textContent = '{}';
            return;
        }

        paneEl.appendChild(document.createTextNode('{\n'));

        keys.forEach((key, index) => {
            const value = parsed[key];
            const changed = JSON.stringify(value) !== JSON.stringify(otherParsed[key]);
            const separator = index < keys.length - 1 ? ',' : '';
            const line = `  ${JSON.stringify(key)}: ${JSON.stringify(value)}${separator}`;

            if (changed) {
                const span = document.createElement('span');
                span.className = 'diff-changed fw-semibold border-start border-3 ps-2 d-inline-block';
                span.textContent = line;
                paneEl.appendChild(span);
            } else {
                paneEl.appendChild(document.createTextNode(line));
            }

            paneEl.appendChild(document.createTextNode('\n'));
        });

        paneEl.appendChild(document.createTextNode('}'));
    }

    isPlainObject(value) {
        return value !== null && typeof value === 'object' && !Array.isArray(value);
    }

    tryParseJson(rawValue) {
        if (!rawValue) return null;

        try {
            return JSON.parse(rawValue);
        } catch (error) {
            return undefined;
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
