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

        const el = document.createElement('div');
        el.setAttribute('data-reply-id', String(reply.id));
        el.setAttribute('dusk', `reply-${reply.id}`);
        el.style.cssText = 'padding: 14px 16px; border: 1px solid var(--color-divider); background: var(--color-surface); margin-bottom: 10px;';

        const author = document.createElement('div');
        author.style.cssText = 'font-size: 12px; color: var(--color-neutral-600); margin-bottom: 6px;';
        author.textContent = `${(reply.user && reply.user.name) || 'Usuário'} — ${reply.created_at || ''}`;

        const content = document.createElement('div');
        content.style.cssText = 'font-size: 14px; white-space: pre-wrap;';
        content.setAttribute('dusk', `reply-content-${reply.id}`);
        content.textContent = reply.content;

        el.appendChild(author);
        el.appendChild(content);
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
