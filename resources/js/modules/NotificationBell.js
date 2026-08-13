/**
 * NotificationBell - SOLID JavaScript module for SPEC-13 §4's (RF28)
 * topbar bell: polls `GET notifications.unread-count` every 30s to keep
 * the badge fresh, wires "marcar todas como lidas" to
 * `PATCH notifications.read-all`, and marks a single notification read
 * (`PATCH notifications.read`) as the browser follows the item's own
 * `href` (the notification's `data.action_url`).
 *
 * Abrir/fechar o dropdown NÃO é responsabilidade deste módulo: o
 * `<x-notifications-bell />` declara `data-bs-toggle="dropdown"` +
 * `.dropdown-menu`, e o `bootstrap.Dropdown` cuida de posicionamento
 * (Popper), clique fora, Escape e ARIA. Aqui só reagimos ao evento
 * `show.bs.dropdown` para refrescar o contador na abertura.
 *
 * Same rationale as `ForumPolling.js`: no jQuery, no WebSockets, the
 * shared `HttpClient` module instead of `$.ajax`.
 */
export class NotificationBell {
    constructor(httpClient, intervalMs = 30000) {
        this.httpClient = httpClient;
        this.intervalMs = intervalMs;
        this.container = null;
        this.timer = null;
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
        const container = document.querySelector('[data-notifications-bell]');
        if (!container) return;

        this.container = container;
        this.unreadCountUrl = container.getAttribute('data-unread-count-url');
        this.markAllReadUrl = container.getAttribute('data-mark-all-read-url');

        // O evento borbulha a partir do toggle, então o container é o ponto
        // de escuta seguro tanto para `show.bs.dropdown` quanto para cliques.
        container.addEventListener('show.bs.dropdown', () => {
            // Abrir o dropdown também busca um contador fresco, para que o
            // badge nunca fique atrás do ciclo de 30s do polling.
            this.refreshUnreadCount();
        });

        container.addEventListener('click', (event) => {
            const markAllLink = event.target.closest('[data-notifications-mark-all]');
            if (markAllLink) {
                event.preventDefault();
                this.markAllRead();
                return;
            }

            const item = event.target.closest('[data-notifications-item]');
            if (item) {
                this.handleItemClick(event, item);
            }
        });

        this.startPolling();
    }

    startPolling() {
        this.refreshUnreadCount();
        this.timer = window.setInterval(() => this.refreshUnreadCount(), this.intervalMs);
    }

    async refreshUnreadCount() {
        if (!this.unreadCountUrl) return;

        try {
            const response = await this.httpClient.get(this.unreadCountUrl);
            const count = (response.data && response.data.count) || 0;
            this.updateBadge(count);
        } catch (error) {
            // Silently skip this poll cycle — the next one will retry,
            // mirroring ForumPolling.js's failure handling.
        }
    }

    /**
     * Visibilidade do badge é classe, nunca `style.display`: as utilities
     * `d-flex`/`d-none` do Bootstrap são `!important`, então um
     * `style.display = 'none'` inline perde a especificidade e o badge
     * continuaria visível.
     */
    updateBadge(count) {
        if (!this.container) return;

        const badge = this.container.querySelector('[data-notifications-badge]');
        if (!badge) return;

        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.classList.remove('d-none');
            badge.classList.add('d-flex');
        } else {
            badge.textContent = '0';
            badge.classList.remove('d-flex');
            badge.classList.add('d-none');
        }
    }

    async markAllRead() {
        if (!this.markAllReadUrl) return;

        try {
            await this.httpClient.patch(this.markAllReadUrl);
            this.updateBadge(0);

            this.container.querySelectorAll('[data-notifications-item]').forEach((item) => {
                item.classList.remove('bg-primary', 'bg-opacity-10', 'fw-semibold');
            });
        } catch (error) {
            // Leave the badge/list as-is on failure; the user can retry.
        }
    }

    /**
     * O item é um `<a href="{action_url}">` de verdade: deixamos a navegação
     * nativa acontecer (funciona sem JS) e apenas disparamos o
     * `PATCH notifications.read` antes dela. `keepalive` é o que garante que
     * a requisição sobreviva ao unload da página — sem ele o navegador
     * cancelaria o fetch em voo e a notificação nunca seria marcada lida.
     */
    handleItemClick(event, item) {
        const markReadUrl = item.getAttribute('data-mark-read-url');
        if (!markReadUrl) return;

        this.httpClient.patch(markReadUrl, null, { keepalive: true }).catch(() => {
            // Mesmo que marcar-como-lida falhe, o usuário ainda é levado ao
            // recurso que clicou — a navegação nativa segue seu curso.
        });
    }

    stop() {
        if (this.timer) {
            window.clearInterval(this.timer);
            this.timer = null;
        }
    }
}

export default NotificationBell;
