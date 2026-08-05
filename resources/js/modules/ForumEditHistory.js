/**
 * ForumEditHistory - SOLID JavaScript module opening the "ver histórico"
 * edit-history modal rendered by
 * `resources/views/forum/partials/_edit-history-modal.blade.php`
 * (SPEC-10 §2.1). The history itself is server-rendered into the modal
 * (no AJAX round trip), so this module's job is purely presentational:
 * delegate opening to the shared `window.ModalManager` (already bound to
 * `[data-modal-target]` clicks globally) and — like
 * `certificates/index.blade.php`'s inline fix — ensure every history
 * modal's backdrop starts hidden, since Alpine.js's `x-show` is inert
 * (Alpine is not an installed dependency of this project).
 */
export class ForumEditHistory {
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
        this.hideBackdrops();

        document.querySelectorAll('[data-edit-history-trigger]').forEach((button) => {
            button.addEventListener('click', (event) => {
                const targetId = button.getAttribute('data-modal-target');
                if (!targetId || !this.modalManager) return;

                event.preventDefault();
                this.modalManager.open(targetId);
            });
        });
    }

    hideBackdrops() {
        document.querySelectorAll('[id^="edit-history-"]').forEach((modal) => {
            const backdrop = modal.closest('.dialog-backdrop');
            if (backdrop) backdrop.style.display = 'none';
        });
    }
}

export default ForumEditHistory;
