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
 * Expected JSON response shape (the `forum-replies.fetch` contract):
 *   {
 *     "data": [
 *       {
 *         "id": 42,
 *         "content": "…",
 *         "created_at": "02/08/2026 14:30",
 *         "created_at_relative": "há 2 minutos",
 *         "initials": "AN",
 *         "role_label": "Aluno",
 *         "user": { "name": "Ana" }
 *       }
 *     ],
 *     "last_id": 42
 *   }
 * ordered ascending by `id`, containing only rows with `id > since_id`,
 * capped at 50 rows per call. `replies` is accepted as an alias of `data`.
 *
 * `initials` and `role_label` are generated SERVER-SIDE so an injected
 * reply is visually indistinguishable from one rendered by
 * `forum/partials/_reply.blade.php`; when the payload omits them the
 * module degrades gracefully (initials derived from the author name,
 * role chip suppressed) instead of rendering a broken card.
 */

/**
 * Statuses after which this endpoint can never answer this page again:
 * an expired/invalidated session (401/419), a revoked access (403) or a
 * topic removed by moderation (404). EVERYTHING else is transient and
 * only stands the loop down for a few ticks — the whole 5xx range (a
 * 503 while the app is in maintenance mode, a 500/502 mid-deploy), any
 * unexpected 4xx, `throttle:60,1`'s 429, and `status === 0` network
 * drops. Tearing the interval down on those would silently kill live
 * updates for the tab until a manual reload.
 */
const TERMINAL_STATUSES = new Set([401, 403, 404, 419]);

export class ForumPolling {
    constructor(httpClient, intervalMs = 10000) {
        this.httpClient = httpClient;
        this.intervalMs = intervalMs;
        this.timers = new Map();
        this.backoffCycles = new Map();
        this.backoffSteps = new Map();
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
        this.backoffCycles.set(container, 0);
        this.backoffSteps.set(container, 0);

        let lastId = Number(container.getAttribute('data-last-id') || 0);

        const poll = async () => {
            // A previous cycle asked to be skipped (rate limited, server
            // briefly broken, offline). The interval itself is NEVER
            // cleared on these — we only stand down for a few ticks so a
            // `throttle:60,1` 429 or a deploy's 503 drains instead of
            // compounding.
            const pending = this.backoffCycles.get(container) || 0;
            if (pending > 0) {
                this.backoffCycles.set(container, pending - 1);
                return;
            }

            let payload;

            try {
                const separator = url.includes('?') ? '&' : '?';
                const response = await this.httpClient.get(`${url}${separator}since_id=${lastId}`);
                payload = response.data;
            } catch (error) {
                this.handleTransportFailure(container, error);

                return;
            }

            // A clean cycle also de-escalates the rate-limit back-off, so one
            // isolated 429 cannot make a thread permanently sluggish.
            this.backoffCycles.set(container, 0);
            this.backoffSteps.set(container, 0);

            let replies = [];

            if (Array.isArray(payload)) {
                replies = payload;
            } else if (payload && Array.isArray(payload.data)) {
                replies = payload.data;
            } else if (payload && Array.isArray(payload.replies)) {
                replies = payload.replies;
            } else {
                // Malformed/non-JSON body: nothing to render and no id to
                // advance. Distinct from a transport failure — no back-off,
                // the next cycle polls again at full speed.
                return;
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
        };

        const timer = window.setInterval(poll, this.intervalMs);
        this.timers.set(container, timer);
    }

    /**
     * A poll cycle failed at the transport layer. Two cases, two outcomes:
     *
     *  - Terminal statuses (see `TERMINAL_STATUSES`: 401/419 expired
     *    session, 403 revoked access, 404 topic removed by moderation):
     *    this endpoint will never answer this page again, so the interval
     *    is stopped instead of firing a doomed request every 10s for as
     *    long as the tab lives.
     *  - Transient failures — everything else: `throttle:60,1`'s 429
     *    (several tabs on the same topic or a devtools reload loop easily
     *    out-pace the 60/min budget), a plain network drop
     *    (`status === 0`), and any response the server emits while it is
     *    briefly broken (500/502 mid-deploy, 503 maintenance mode). Skip
     *    this cycle, back off a few ticks, KEEP the interval alive. The
     *    back-off escalates on consecutive failures up to 3 cycles and
     *    de-escalates on the next success, so one isolated hiccup never
     *    leaves the thread permanently slow.
     *
     * Nothing is logged in either branch: a rate-limited tab must recover
     * on its own and a dead thread must not spam the console.
     */
    handleTransportFailure(container, error) {
        const status = error && typeof error.status === 'number' ? error.status : 0;

        if (TERMINAL_STATUSES.has(status)) {
            this.stop(container);

            return;
        }

        const previous = this.backoffSteps.get(container) || 0;
        const step = Math.min(previous + 1, 3);

        this.backoffSteps.set(container, step);
        this.backoffCycles.set(container, step);
    }

    appendReply(container, reply) {
        if (!reply || !reply.id) return;
        if (container.querySelector(`[data-reply-id="${reply.id}"]`)) return;

        // A thread vazia renderiza o "Nenhuma resposta ainda" (atributo de
        // dado, não `dusk=` — o contrato de seletores é congelado). A primeira
        // resposta em tempo real encerra o estado vazio, sem reload.
        container.querySelector('[data-forum-empty-replies]')?.remove();

        // Mirrors `forum/partials/_reply.blade.php`'s card markup — avatar,
        // author name, role chip, timestamp and body — keep the two in sync:
        // a polled reply must be visually indistinguishable from a
        // server-rendered one. Everything below goes through
        // `document.createElement` + `textContent`, NEVER `innerHTML`, so the
        // XSS guarantee proven by `XssSanitizationTest` also holds client-side.
        const authorName = (reply.user && reply.user.name) || 'Usuário';

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

        // `<x-ui.avatar size="lg">` renders `<span class="ds-avatar ds-avatar-lg">`.
        const avatar = document.createElement('span');
        avatar.className = 'ds-avatar ds-avatar-lg';
        avatar.textContent = reply.initials || this.initialsFrom(authorName);
        metaWrapper.appendChild(avatar);

        const textMeta = document.createElement('div');
        textMeta.className = 'small text-body-secondary';

        const strong = document.createElement('strong');
        strong.className = 'text-body';
        strong.textContent = authorName;
        textMeta.appendChild(strong);

        // `<x-ui.badge variant="outline">` renders
        // `<span class="badge ds-badge border ds-muted">`.
        if (reply.role_label) {
            const roleBadge = document.createElement('span');
            roleBadge.className = 'badge ds-badge border ds-muted';
            roleBadge.textContent = reply.role_label;
            textMeta.appendChild(document.createTextNode(' '));
            textMeta.appendChild(roleBadge);
        }

        if (reply.created_at) {
            // `_reply.blade.php` renders `diffForHumans()` as the VISIBLE
            // text and keeps the absolute `d/m/Y H:i` only in `title=` —
            // the polled card does exactly the same, so a reply injected
            // here reads like the ones Blade rendered.
            const timeSpan = document.createElement('span');
            timeSpan.setAttribute('title', reply.created_at);
            timeSpan.textContent = reply.created_at_relative || reply.created_at;

            textMeta.appendChild(document.createTextNode(' — '));
            textMeta.appendChild(timeSpan);
        }

        metaWrapper.appendChild(textMeta);
        header.appendChild(metaWrapper);

        // Only the viewer-independent action is cloned. "Editar"/"Apagar"
        // depend on per-reply permissions the polling payload does not
        // carry, so an injected reply stays un-editable and un-moderatable
        // until the page is reloaded.
        const actions = document.createElement('div');
        actions.className = 'd-flex gap-2';

        const reportButton = document.createElement('button');
        reportButton.type = 'button';
        reportButton.className = 'btn btn-ghost ds-state-layer btn-sm';
        reportButton.setAttribute('data-forum-report-button', '');
        reportButton.setAttribute('data-postable-type', 'forum_reply');
        reportButton.setAttribute('data-postable-id', String(reply.id));
        reportButton.setAttribute('data-bs-toggle', 'modal');
        reportButton.setAttribute('data-bs-target', '#report-modal');
        reportButton.setAttribute('dusk', `report-reply-${reply.id}`);
        reportButton.textContent = 'Denunciar';
        actions.appendChild(reportButton);
        header.appendChild(actions);

        const content = document.createElement('div');
        content.className = 'text-prewrap';
        content.setAttribute('dusk', `reply-content-${reply.id}`);
        content.textContent = reply.content || '';

        body.appendChild(header);
        body.appendChild(content);
        el.appendChild(body);
        container.appendChild(el);
    }

    /**
     * Client-side fallback for the server-generated `initials` field: same
     * 2-letter rule as the Blade partial (first letter of the first two
     * whitespace-separated name parts, upper-cased).
     */
    initialsFrom(name) {
        return String(name || '')
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part.charAt(0).toUpperCase())
            .join('');
    }

    stop(container) {
        const timer = this.timers.get(container);
        if (timer) {
            window.clearInterval(timer);
            this.timers.delete(container);
        }
        this.backoffCycles.delete(container);
        this.backoffSteps.delete(container);
    }

    stopAll() {
        this.timers.forEach((timer) => window.clearInterval(timer));
        this.timers.clear();
        this.backoffCycles.clear();
        this.backoffSteps.clear();
    }
}

export default ForumPolling;
