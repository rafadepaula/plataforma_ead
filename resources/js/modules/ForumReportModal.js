/**
 * ForumReportModal - SOLID JavaScript module wiring every "Denunciar"
 * button (SPEC-10 §2.2/RF26) to the shared `#report-modal` on
 * `forum/show.blade.php`. Each "Denunciar" button carries
 * `data-postable-type`/`data-postable-id`; clicking one fills the
 * modal's hidden fields before `window.ModalManager` opens it (via the
 * button's own `data-modal-target="report-modal"`), and this module
 * intercepts the modal form's submit to post the reason via the shared
 * `HttpClient` to `forum-reports.store` (`ForumReportController::store()`,
 * Bucket 2).
 */
export class ForumReportModal {
    constructor(httpClient, notificationService, modalManager) {
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
        document.querySelectorAll('[data-forum-report-button]').forEach((button) => {
            button.addEventListener('click', () => this.prefill(button));
        });

        document.querySelectorAll('[data-forum-report-form]').forEach((form) => {
            form.addEventListener('submit', (event) => this.submit(event, form));
        });
    }

    prefill(button) {
        const form = document.querySelector('[data-forum-report-form]');
        if (!form) return;

        const typeField = form.querySelector('[data-forum-report-postable-type]');
        const idField = form.querySelector('[data-forum-report-postable-id]');
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
            if (this.modalManager) this.modalManager.close('report-modal');
        } catch (error) {
            this.notify('error', `Falha ao enviar denúncia: ${error.message}`);
        }
    }

    notify(type, message) {
        if (!this.notificationService || typeof this.notificationService[type] !== 'function') return;
        this.notificationService[type](message);
    }
}

export default ForumReportModal;
