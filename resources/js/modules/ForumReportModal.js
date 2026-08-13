/**
 * ForumReportModal - SOLID JavaScript module wiring every "Denunciar"
 * button (SPEC-10 §2.2/RF26) to the shared `#report-modal` on
 * `forum/show.blade.php`. Each "Denunciar" button carries
 * `data-postable-type`/`data-postable-id` and opens the modal
 * declaratively through `data-bs-toggle="modal" data-bs-target="#report-modal"`
 * (bootstrap-conventions §9). The hidden fields are filled from
 * `event.relatedTarget` on `show.bs.modal` — the canonical Bootstrap way
 * to know which trigger opened a shared modal — and this module
 * intercepts the modal form's submit to post the reason via the shared
 * `HttpClient` to `forum-reports.store` (`ForumReportController::store()`,
 * Bucket 2).
 *
 * The third constructor argument is kept for backward compatibility with
 * the old `ModalManager` injection (now removed from the registry) and is
 * ignored: closing is done through `bootstrap.Modal.getOrCreateInstance`.
 */
export class ForumReportModal {
    constructor(httpClient, notificationService, modalManager = null) {
        this.httpClient = httpClient;
        this.notificationService = notificationService;
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
        document.querySelectorAll('[data-forum-report-form]').forEach((form) => {
            form.addEventListener('submit', (event) => this.submit(event, form));

            const modal = form.closest('.modal');
            if (!modal) return;

            modal.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget?.closest?.('[data-forum-report-button]');
                if (button) this.prefill(button, form);
            });
        });
    }

    prefill(button, form = null) {
        const target = form ?? document.querySelector('[data-forum-report-form]');
        if (!target) return;

        const typeField = target.querySelector('[data-forum-report-postable-type]');
        const idField = target.querySelector('[data-forum-report-postable-id]');
        if (typeField) typeField.value = button.getAttribute('data-postable-type') || '';
        if (idField) idField.value = button.getAttribute('data-postable-id') || '';
    }

    async submit(event, form) {
        event.preventDefault();

        const payload = {
            postable_type: form.querySelector('[data-forum-report-postable-type]')?.value,
            postable_id: form.querySelector('[data-forum-report-postable-id]')?.value,
            reason: form.querySelector('[name="reason"]')?.value,
        };

        try {
            await this.httpClient.post(form.getAttribute('action'), payload);
            this.notify('success', 'Denúncia enviada. A moderação irá revisar.');
            form.reset();
            this.close(form);
        } catch (error) {
            this.notify('error', `Falha ao enviar denúncia: ${error.message}`);
        }
    }

    close(form) {
        const element = form.closest('.modal');
        if (!element || !window.bootstrap?.Modal) return;

        window.bootstrap.Modal.getOrCreateInstance(element).hide();
    }

    notify(type, message) {
        if (!this.notificationService || typeof this.notificationService[type] !== 'function') return;
        this.notificationService[type](message);
    }
}

export default ForumReportModal;
