/**
 * ModuleReorder - SOLID JavaScript module for AJAX drag-and-drop reordering
 * ( "Reordenação de módulos via AJAX/jQuery"). jQuery/jQuery UI
 * Sortable is not an existing dependency of this project (see
 * `package.json` and CLAUDE.md's "don't add dependencies without
 * approval"), so this binds a small native HTML5 drag-and-drop fallback
 * instead of pulling in a new library, on any `[data-reorder-url]` list —
 * used both for the Module list (nested under a Course) and the Lesson
 * list (nested under a Module). Posts the new ordered `id` sequence via
 * the shared `HttpClient` module and surfaces a `NotificationService`
 * toast on success/failure.
 */
export class ModuleReorder {
    constructor(httpClient, notificationService) {
        this.httpClient = httpClient;
        this.notificationService = notificationService;
        this.draggedItem = null;
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
        const lists = document.querySelectorAll('[data-reorder-url]');
        lists.forEach((list) => this.bindList(list));
    }

    bindList(list) {
        list.addEventListener('dragstart', (event) => {
            const item = event.target.closest('[data-id]');
            if (!item || !list.contains(item)) return;
            this.draggedItem = item;
            event.dataTransfer.effectAllowed = 'move';
        });

        list.addEventListener('dragover', (event) => {
            event.preventDefault();
            const target = event.target.closest('[data-id]');
            if (!target || target === this.draggedItem || !list.contains(target)) return;

            const rect = target.getBoundingClientRect();
            const isAfter = event.clientY - rect.top > rect.height / 2;
            list.insertBefore(this.draggedItem, isAfter ? target.nextSibling : target);
        });

        list.addEventListener('drop', (event) => {
            event.preventDefault();
            this.persistOrder(list);
        });

        list.addEventListener('dragend', () => {
            this.draggedItem = null;
        });
    }

    async persistOrder(list) {
        const url = list.getAttribute('data-reorder-url');
        const orderedIds = Array.from(list.querySelectorAll('[data-id]')).map((item) => Number(item.getAttribute('data-id')));

        if (!url || orderedIds.length === 0) return;

        try {
            await this.httpClient.post(url, { ordered_ids: orderedIds });
            this.notify('success', 'Ordem atualizada com sucesso.');
        } catch (error) {
            this.notify('error', `Falha ao reordenar: ${error.message}`);
        }
    }

    notify(type, message) {
        if (!this.notificationService || typeof this.notificationService[type] !== 'function') return;
        this.notificationService[type](message);
    }
}

export default ModuleReorder;
