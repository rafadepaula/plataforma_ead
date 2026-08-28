/**
 * ForumPolling - SOLID JavaScript module for
 * AJAX polling: every 10s, fetches only replies newer
 * than the last one already on the page (since_id-based, never a full
 * thread refetch) and appends them to the DOM.
 *
 * Standard polling implementation without jQuery (using native fetch API)
 * using the shared HttpClient module.
 *
 * Binds to every `[data-forum-polling]` container (one per
 * `forum/show.blade.php` page), reading:
 *   - `data-fetch-url`  the `forum-replies.fetch` route for this topic.
 *   - `data-last-id`    the highest reply id already rendered server-side.
 *
 * Expected JSON response shape:
 *   { "data": [ { "id": number, "content": string, "created_at": string,
 *                 "user": { "name": string } }, ... ] }
 *   or { "replies": [...], "last_id": ... }
 * ordered ascending by `id`, containing only rows with `id > since_id`.
 */
export class ForumPolling {
    constructor(httpClient, intervalMs = 10000) {
        this.httpClient = httpClient;
        this.intervalMs = intervalMs;
        this.timers = new Map();
        this._unloadHandler = null;
    }

    init() {
        if (typeof document === 'undefined') return;

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.bind());
        } else {
            this.bind();
        }

        if (typeof window !== 'undefined') {
            if (!this._unloadHandler) {
                this._unloadHandler = () => this.stopAll();
                window.addEventListener('beforeunload', this._unloadHandler);
                window.addEventListener('pagehide', this._unloadHandler);
            }
        }
    }

    bind() {
        document.querySelectorAll('[data-forum-polling]').forEach((container) => this.bindContainer(container));
    }

    bindContainer(container) {
        const url = container.getAttribute('data-fetch-url');
        if (!url) return;

        // Stop any existing timer for this container before creating a new one
        this.stop(container);

        let lastId = Number(container.getAttribute('data-last-id') || 0);

        const poll = async () => {
            try {
                const separator = url.includes('?') ? '&' : '?';
                const response = await this.httpClient.get(`${url}${separator}since_id=${lastId}`);

                const payload = response.data;
                let replies = [];

                if (Array.isArray(payload)) {
                    replies = payload;
                } else if (payload && Array.isArray(payload.data)) {
                    replies = payload.data;
                } else if (payload && Array.isArray(payload.replies)) {
                    replies = payload.replies;
                }

                replies.forEach((reply) => {
                    this.appendReply(container, reply);
                    if (reply.id > lastId) {
                        lastId = reply.id;
                    }
                });

                if (payload && typeof payload.last_id === 'number' && payload.last_id > lastId) {
                    lastId = payload.last_id;
                }

                container.setAttribute('data-last-id', String(lastId));
            } catch (error) {
                // Silently skip this poll cycle — the next one will retry.
                // A hard failure here (e.g. throttle:60,1's 429 Too Many Requests)
                // must never break the page or kill the interval loop.
            }
        };

        const timer = window.setInterval(poll, this.intervalMs);
        this.timers.set(container, timer);
    }

    appendReply(container, reply) {
        if (!reply || !reply.id) return;
        if (container.querySelector(`[data-reply-id="${reply.id}"]`)) return;

        // Mirrors `forum/partials/_reply.blade.php`'s Bootstrap card markup —
        // keep the two in sync, a polled reply must be visually
        // indistinguishable from a server-rendered one.
        const el = document.createElement('div');
        el.setAttribute('data-reply-id', String(reply.id));
        el.setAttribute('dusk', `reply-${reply.id}`);
        el.className = 'forum-reply card mb-2';

        const body = document.createElement('div');
        body.className = 'card-body py-3';

        const header = document.createElement('div');
        header.className = 'd-flex align-items-start justify-content-between gap-3 mb-1';

        const metaWrapper = document.createElement('div');
        metaWrapper.className = 'd-flex align-items-center gap-3';

        const textMeta = document.createElement('div');
        textMeta.className = 'small text-body-secondary';

        const strong = document.createElement('strong');
        strong.className = 'text-body';
        strong.textContent = (reply.user && reply.user.name) || 'Usuário';

        const timeSpan = document.createElement('span');
        timeSpan.textContent = reply.created_at ? ` — ${reply.created_at}` : '';

        textMeta.appendChild(strong);
        textMeta.appendChild(timeSpan);
        metaWrapper.appendChild(textMeta);
        header.appendChild(metaWrapper);

        const content = document.createElement('div');
        content.className = 'text-prewrap';
        content.setAttribute('dusk', `reply-content-${reply.id}`);
        content.textContent = reply.content || '';

        body.appendChild(header);
        body.appendChild(content);
        el.appendChild(body);
        container.appendChild(el);
    }

    stop(container) {
        const timer = this.timers.get(container);
        if (timer) {
            window.clearInterval(timer);
            this.timers.delete(container);
        }
    }

    stopAll() {
        this.timers.forEach((timer) => window.clearInterval(timer));
        this.timers.clear();
    }
}

export default ForumPolling;
