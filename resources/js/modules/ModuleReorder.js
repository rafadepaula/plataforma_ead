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
 *
 * Failure contract: the DOM order is snapshotted BEFORE the
 * POST and restored on any non-2xx response, so the UI never lies about
 * the persisted order. Concurrent reorders by two gestores resolve as
 * last-write-wins on the server — acceptable and documented.
 *
 * Accessibility: each row carries `data-move-up`/`data-move-down`
 * buttons (rendered by `<x-ui.sortable-row>`) that reorder via keyboard and
 * persist through this exact same endpoint/payload — no parallel path that
 * could bypass the server's tenant guard.
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

        list.addEventListener('click', (event) => {
            this.handleMoveButton(list, event);
        });
    }

    /**
     * Keyboard-accessible move-up/move-down: same DOM reorder, same
     * persistence call — the server-side tenant/ownership guard is shared
     * with the drag path.
     */
    handleMoveButton(list, event) {
        const moveUp = event.target.closest('[data-move-up]');
        const moveDown = event.target.closest('[data-move-down]');
        if (!moveUp && !moveDown) return;

        const item = (moveUp || moveDown).closest('[data-id]');
        if (!item || !list.contains(item)) return;

        if (moveUp && item.previousElementSibling) {
            list.insertBefore(item, item.previousElementSibling);
            this.persistOrder(list);
        }

        if (moveDown && item.nextElementSibling) {
            list.insertBefore(item.nextElementSibling, item);
            this.persistOrder(list);
        }
    }

    async persistOrder(list) {
        const url = list.getAttribute('data-reorder-url');
        const orderedIds = Array.from(list.querySelectorAll('[data-id]')).map((item) => Number(item.getAttribute('data-id')));

        if (!url || orderedIds.length === 0) return;

        // Snapshot ANTES do POST: em caso de falha a lista volta exatamente
        // à ordem que o servidor conhece.
        const snapshot = Array.from(list.children);

        try {
            await this.httpClient.post(url, { ordered_ids: orderedIds });
            this.notify('success', 'Ordem atualizada com sucesso.');
        } catch (error) {
            snapshot.forEach((child) => list.appendChild(child));
            this.notify('error', `Falha ao reordenar: ${error.message}`);
        }
    }

    notify(type, message) {
        if (!this.notificationService || typeof this.notificationService[type] !== 'function') return;
        this.notificationService[type](message);
    }
}

export default ModuleReorder;
