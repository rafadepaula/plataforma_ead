/**
 * ForumPolling - SOLID JavaScript module for SPEC-10 §2's
 * `fetchNewReplies` AJAX polling: every 10s, fetches only replies newer
 * than the last one already on the page (`since_id`-based, never a full
 * thread refetch) and appends them to the DOM.
 *
 * The spec calls for "jQuery polling", but jQuery is not an installed
 * dependency of this project (see `package.json` and CLAUDE.md's "don't
 * add dependencies without approval") — same rationale as
 * `ModuleReorder.js`'s native drag-and-drop fallback — so this uses the
 * shared `HttpClient` module instead of jQuery's `$.ajax`.
 *
 * Binds to every `[data-forum-polling]` container (one per
 * `forum/show.blade.php` page), reading:
 *   - `data-fetch-url`  the `forum-replies.fetch` route for this topic.
 *   - `data-last-id`    the highest reply id already rendered server-side.
 *
 * Expected `forum-replies.fetch` JSON response shape (Bucket 2 contract):
 *   { "data": [ { "id": number, "content": string, "created_at": string,
 *                 "user": { "name": string } }, ... ] }
 * ordered ascending by `id`, containing only rows with `id > since_id`.
 */
export class ForumPolling {
    constructor(httpClient, intervalMs = 10000) {
        this.httpClient = httpClient;
        this.intervalMs = intervalMs;
        this.timers = new Map();
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
        document.querySelectorAll('[data-forum-polling]').forEach((container) => this.bindContainer(container));
    }

    bindContainer(container) {
        const url = container.getAttribute('data-fetch-url');
        if (!url) return;

        let lastId = Number(container.getAttribute('data-last-id') || 0);

        const poll = async () => {
            try {
                const response = await this.httpClient.get(`${url}${url.includes('?') ? '&' : '?'}since_id=${lastId}`);
                const replies = (response.data && response.data.data) || [];

                replies.forEach((reply) => {
                    this.appendReply(container, reply);
                    if (reply.id > lastId) lastId = reply.id;
                });
            } catch (error) {
                // Silently skip this poll cycle — the next one will retry.
                // A hard failure here (e.g. throttle:60,1's 429) must never
                // break the page or stop the interval.
            }
        };

        const timer = window.setInterval(poll, this.intervalMs);
        this.timers.set(container, timer);
    }

    appendReply(container, reply) {
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

        const author = document.createElement('div');
        author.className = 'small text-body-secondary mb-1';
        author.textContent = `${(reply.user && reply.user.name) || 'Usuário'} — ${reply.created_at || ''}`;

        const content = document.createElement('div');
        content.className = 'text-prewrap';
        content.setAttribute('dusk', `reply-content-${reply.id}`);
        content.textContent = reply.content;

        body.appendChild(author);
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
}

export default ForumPolling;
