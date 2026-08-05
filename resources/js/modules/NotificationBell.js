/**
 * NotificationBell - SOLID JavaScript module for SPEC-13 §4's (RF28)
 * topbar bell: polls `GET notifications.unread-count` every 30s to keep
 * the badge fresh, toggles the pre-rendered dropdown (server-rendered by
 * `<x-notifications-bell />` with the 10 most recent rows — no separate
 * "list" AJAX endpoint exists per Bucket 2's contract), wires "marcar
 * todas como lidas" to `PATCH notifications.read-all`, and marks a single
 * notification read (`PATCH notifications.read`) before redirecting the
 * click to its `data.action_url`.
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

        const toggle = container.querySelector('[data-notifications-toggle]');
        const dropdown = container.querySelector('[data-notifications-dropdown]');

        if (toggle && dropdown) {
            toggle.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                this.toggleDropdown(dropdown);
            });

            document.addEventListener('click', (event) => {
                if (!container.contains(event.target)) {
                    this.closeDropdown(dropdown);
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') this.closeDropdown(dropdown);
            });
        }

        const markAllLink = container.querySelector('[data-notifications-mark-all]');
        if (markAllLink) {
            markAllLink.addEventListener('click', (event) => {
                event.preventDefault();
                this.markAllRead();
            });
        }

        container.querySelectorAll('[data-notifications-item]').forEach((item) => {
            item.addEventListener('click', (event) => this.handleItemClick(event, item));
        });

        this.startPolling();
    }

    toggleDropdown(dropdown) {
        const isOpen = dropdown.style.display === 'block';
        if (isOpen) {
            this.closeDropdown(dropdown);
        } else {
            dropdown.style.display = 'block';
            // Opening the dropdown also fetches a fresh unread count, so
            // the badge never lags the 30s polling tick.
            this.refreshUnreadCount();
        }
    }

    closeDropdown(dropdown) {
        dropdown.style.display = 'none';
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

    updateBadge(count) {
        if (!this.container) return;

        const badge = this.container.querySelector('[data-notifications-badge]');
        if (!badge) return;

        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.style.display = 'flex';
        } else {
            badge.textContent = '0';
            badge.style.display = 'none';
        }
    }

    async markAllRead() {
        if (!this.markAllReadUrl) return;

        try {
            await this.httpClient.patch(this.markAllReadUrl);
            this.updateBadge(0);

            this.container.querySelectorAll('[data-notifications-item]').forEach((item) => {
                item.style.background = '';
                item.style.fontWeight = '';
            });
        } catch (error) {
            // Leave the badge/list as-is on failure; the user can retry.
        }
    }

    handleItemClick(event, item) {
        event.preventDefault();

        const markReadUrl = item.getAttribute('data-mark-read-url');
        const actionUrl = item.getAttribute('href') || '#';
        const redirect = () => {
            window.location.href = actionUrl;
        };

        if (!markReadUrl) {
            redirect();
            return;
        }

        this.httpClient
            .patch(markReadUrl)
            .catch(() => {
                // Even if marking-as-read fails, the user still expects
                // to be taken to the resource they clicked.
            })
            .finally(redirect);
    }

    stop() {
        if (this.timer) {
            window.clearInterval(this.timer);
            this.timer = null;
        }
    }
}

export default NotificationBell;
