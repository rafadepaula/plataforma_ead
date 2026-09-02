---
name: forum-conventions
description: >
  Code patterns, snippets, guardrails for Course Discussion Forum feature:
  ForumTopic::withoutEvents() org_id workaround,
  ForumTopicPolicy/ForumReplyPolicy cascade-authorize conventions,
  postable_type FQCN convention shared by
  EditForumPostAction/DeleteForumPostAction/ReportForumPostAction,
  ForumContentSanitizerService strip_tags() defense,
  ForumPolling.js/ForumReportModal.js JS module
  contracts. Use when writing controller, Policy, Form Request, Blade
  view, or JS module managing ForumTopic/ForumReply/ForumPostEdit/
  ForumReport records.
license: MIT
metadata:
  feature: forum
  role: conventions
---

# Forum Conventions

## `{course}`/`{topic}`/`{reply}` Are Plain `int` Route Params, Never Typed Bindings

`ForumTopicController`/`ForumReplyController`/`ForumModerationController`
never type-hint `Course $course`/`ForumTopic $topic` in route method
(except `ForumModerationController` `ForumReport $forumReport`, which
carry no `OrgScope`, so implicit binding safe there). Both `Course` and
`ForumTopic` carry `OrgScope`, and multi-org Aluno (`org_id === null`, no
impersonation session) would get Laravel implicit-binding query silently
filtered to nothing under that scope, turning every request into 404
instead of proper Policy-driven 403:

```php
public function show(Request $request, int $course, int $topic): View
{
    $courseModel = $this->resolveCourse($course); // Course::query()->withoutGlobalScope('org')->findOrFail()
    $topicModel = $this->resolveTopic($topic, $courseModel);
    Gate::authorize('view', $topicModel);
    // ...
}
```

Both controllers keep private `resolveCourse()`/`resolveTopic()`/
`resolveReply()` helpers instead of inline lookups. Only the first two
bypass `OrgScope` — `withoutGlobalScope('org')` +
`findOrFail()`. `resolveReply()` does **not** (and must not pretend to):
`ForumReply` carries no `OrgScope` (its tenancy is cascade-inherited via
its `ForumTopic`), so its lookup is a plain
`ForumReply::query()->where('topic_id', $topic)->findOrFail($reply)`.
Reuse these, never bare `Course::findOrFail($id)` inside forum
controller.

### Drop `OrgScope` by name — never the blanket `withoutGlobalScopes()`

`Course` and `ForumTopic` carry **both** `OrgScope` (named `'org'`, see
`OrgScope::bootOrgScope()`) and `SoftDeletes`. Bare
`withoutGlobalScopes()` drop `SoftDeletingScope` too, so a topic removed
by moderation (`DeleteForumPostAction` soft-delete it) stay fully
reachable: still listed on `forum.index`, still readable on
`forum.show`, still pinnable, still repliable, and `fetchNew` keep
streaming its replies into an open tab. `DeleteForumPostAction`'s own
docblock rely on the opposite ("once the parent topic's route resolve to
a 404 … its replies become unreachable"). So in **controller** lookup
always:

```php
Course::query()->withoutGlobalScope('org')->findOrFail($course);       // yes
ForumTopic::query()->withoutGlobalScope('org')->where(...)->findOrFail($topic);
Course::query()->withoutGlobalScopes()->findOrFail($course);           // no
```

Two deliberate exception, both keep bare `withoutGlobalScopes()`:
**Policy** parent-resolution (`ForumTopicPolicy::parentCourse()`,
`ForumReplyPolicy`) must resolve parent regardless of trashed state or
`firstOrFail()` turn an intended 403 into a type error; and
`ForumReportController::resolvePostable()`, where moderation queue must
still open a report filed against an already-removed post.

Locked by `ForumTopicControllerTest::test_index_omits_a_topic_that_was_
removed_by_moderation`, `::test_show_reports_a_removed_topic_as_missing_
instead_of_rendering_it`, `::test_a_removed_topic_can_no_longer_be_
edited_pinned_or_replied_to`, and
`ForumReplyControllerTest::test_fetch_new_reports_a_removed_topic_as_
missing`.

## `ForumTopic::withoutEvents()` Is One `store()`-Time `OrgScope` Workaround

`ForumTopicController::store()` is **only** place in this module creating
`OrgScope`d model. `OrgScope` `creating` hook (see
`tenancy-architecture`) resolve `org_id` from `$user->org_id ??
session('active_org_id')`, which multi-org Aluno have neither of, even
though target Course tenant well known from `$courseModel->org_id`:

```php
$topic = ForumTopic::withoutEvents(fn () => ForumTopic::query()->create([
    'org_id' => $courseModel->org_id,
    'course_id' => $courseModel->id,
    'user_id' => $request->user()->id,
    'title' => $request->validated('title'),
    'content' => $this->sanitizer->sanitize($request->validated('content')),
]));
```

Every other forum write either target non-`OrgScope`d model
(`ForumReply`/`ForumPostEdit`/`ForumReport`) or `update()` existing
`ForumTopic` row (no `creating` hook fire on update). Do not copy this
`withoutEvents()` pattern outside `ForumTopic::create()`.

## Sanitize on Write, Escape on Read — Never `{!! !!}` Forum Field

`ForumContentSanitizerService::sanitize()` (`trim(strip_tags($content))`)
is **entire** write-side XSS defense — no HTML-purifier package installed
(CLAUDE.md: no new dependencies without approval). Must be called on
every persisted forum text field: topic/reply `content`
(`ForumTopicController::store()`, `EditForumPostAction`), and report
`reason` (`ReportForumPostAction`). Blade default `{{ }}` escaping is
read-side layer. Never render `content`/`title`/`reason`/
`previous_content` with `{!! !!}` anywhere in `resources/views/forum/`.

## Policy Ability Naming Mirrors `QuizPolicy` Cascade Pattern, One or Two Hops Deep

`ForumTopicPolicy::parentCourse()` walk one hop
(`$topic->course()->withoutGlobalScopes()->firstOrFail()`).
`ForumReplyPolicy::parentCourse()` walk two
(`reply->topic()->withoutGlobalScopes()` then reuse
`parentTopicCourse()` on that topic). Both bypass every `OrgScope`
along chain, exactly like `QuizPolicy::parentCourse()` two levels into
`Lesson->Module->Course`. `update`/`delete` always: post author by
`user_id` match, **or** same-org Gestor/Admin via
`isGestorOrAdminForCourse()`. `pin` exist only on `ForumTopicPolicy` —
`ForumReply` have no `is_pinned` column, no `pin` ability.

When authorizing `ForumReply` create against parent `ForumTopic`, always
pass pair explicitly. `Gate::authorize('create', $topicModel)` alone
resolve Policy by `$topicModel` own class (`ForumTopicPolicy`, whose
`create(User, Course)` signature won't match):

```php
Gate::authorize('create', [ForumReply::class, $topicModel]);
```

## `postable_type` Always Written as FQCN, Never Short Label

`EditForumPostAction`/`DeleteForumPostAction`/`ReportForumPostAction` all
write `'postable_type' => $post::class` (i.e. `ForumTopic::class`/
`ForumReply::class`) — real PHP class-string, because
`ForumPostEdit::postable()`/`ForumReport::postable()` call
`$type::withTrashed()->find(...)` directly on that column value.
`forum_topic`/`forum_reply` short strings exist only at HTTP/view/JS
boundary (`data-postable-type` attributes, `StoreForumReportRequest`
input). `ForumReportController::resolvePostable()` is single place
mapping short label (or already-FQCN value) to real model class before
any Action run. Never persist short string directly.

## `forum-replies.fetch` `since_id` Polling Contract

`ForumReplyController::fetchNew()` return only rows with `id > since_id`
(never full re-fetch), ascending by `id`, capped `limit(50)`. Payload is
PUBLISHED CONTRACT consumed by `ForumPolling.js` — it carry every field
`forum/partials/_reply.blade.php` render, so injected card cannot drift
from server-rendered one:

```json
{
  "data": [
    {
      "id": 42,
      "content": "...",
      "created_at": "02/08/2026 14:30",
      "created_at_relative": "há 2 minutos",
      "initials": "AN",
      "role_label": "Aluno",
      "user": { "name": "Ana" }
    }
  ],
  "last_id": 42
}
```

- `initials` and `role_label` come from `User::initials()` /
  `User::roleLabel()` Eloquent accessors (`$user->initials`,
  `$user->role_label`) — SAME accessors the forum views pass to
  `x-ui.avatar`/`x-ui.badge`, so an injected card cannot drift from a
  server-rendered one. Never re-implement either rule in a controller or
  a view `@php` closure; `initialsFrom()` in `ForumPolling.js` is a
  degraded-mode fallback for a payload WITHOUT `initials`, not a second
  source of the rule.
- `last_id` is top level, `(int) ($replies->max('id') ?? 0)`. Client
  honour it after appending rows.
- `fetchNew()` eager-load `with('user.roles')`, not `with('user')` —
  `role_label` read the role relation per row, and a 10s poll would
  otherwise fire one extra query per reply.

`ForumPolling.js` bind one `setInterval` (10s) per
`[data-forum-polling]` container, track `lastId` client-side from
`data-last-id` and each response `last_id`/max `id`, and write `lastId`
back onto `data-last-id` after every successful cycle.
`forum-replies.fetch` carry **own** `throttle:60,1` scoped to that route
only, not whole forum route group, so posting/editing/deleting topic/reply
never subject to polling rate limit.

## `ForumPolling.js::appendReply()` Mirror `_reply.blade.php`, Build With `textContent`

`appendReply()` emit the SAME card `forum/partials/_reply.blade.php`
render — root `forum-reply card mb-2`, `card-body py-3`, avatar
`ds-avatar ds-avatar-lg` (rendered class of `<x-ui.avatar size="lg">`),
author `<strong class="text-body">`, role badge
`badge ds-badge border ds-muted` (rendered class of
`<x-ui.badge variant="outline">`), timestamp span, `text-prewrap` body.
Change the partial, change `appendReply()` in the same commit.

Timestamp render is split, and BOTH halves must match the partial: the
VISIBLE text is the relative `created_at_relative` (`diffForHumans()`,
same as Blade prints), while the absolute `created_at` (`d/m/Y H:i`)
only lives in the span's `title=`. The ` — ` before the span is its own
text node, mirroring the partial's literal dash. There is NO
`[data-edit-history-slot]` placeholder: `_edit-history-modal` render
nothing for a reply with no `edited_at`, so an injected fresh reply must
render nothing there either.

Hard rules:

- Every node built with `document.createElement` + `textContent`. NEVER
  `innerHTML`, `insertAdjacentHTML`, or template string assignment — the
  `XssSanitizationTest` write-side guarantee is bypassed client-side
  otherwise. `ForumPollingAndInteractionDuskTest` post a raw
  `<script>window.forumPollingXss = true;</script>` reply straight into
  the DB (no sanitizer) and assert it stay inert.
- Missing `initials` degrade to a client-side 2-letter fallback
  (`initialsFrom()`); missing `role_label` suppress the badge. Never
  render a broken card.
- Only the viewer-INDEPENDENT action is cloned: "Denunciar"
  (`data-forum-report-button` + `data-postable-type="forum_reply"` +
  `data-bs-toggle="modal"` `data-bs-target="#report-modal"`), which works
  on an injected node because Bootstrap resolve the trigger by
  delegation and `ForumReportModal` prefill from `event.relatedTarget`.
  "Apagar" is NOT cloned — it depend on per-reply permissions the payload
  do not carry, so a polled reply stay un-moderatable until reload.
  Adding it require the endpoint to publish per-viewer permissions first.

## Poll Failure Handling Is Explicit, Never a Blanket Empty `catch`

Four outcomes, four code paths in `bindContainer()`'s `poll()`:

1. **Transient failure** (429 from `throttle:60,1`, network drop —
   `status === 0`, and the whole 5xx range: 500/502 during a deploy,
   503 while the app is in maintenance mode): `handleTransportFailure()`
   — skip the cycle, KEEP the interval, and on consecutive failures back
   off up to 3 cycles (`backoffSteps`/`backoffCycles` Maps keyed by
   container). Silent: a rate-limited tab must recover without spamming
   the console. The back-off DE-escalates: a successful cycle reset
   `backoffSteps` back to 0 alongside `backoffCycles`, so one isolated
   hiccup can never leave a thread permanently slow — only consecutive
   ones stack. A 503 must NEVER tear the loop down: the tab come back on
   its own the moment the app is up again.
2. **Terminal failure** (only the four statuses in
   `ForumPolling.js`'s `TERMINAL_STATUSES` set: 401/419 expired session,
   403 revoked policy, 404 topic removed by moderation): the endpoint
   will never answer that page again, so `handleTransportFailure()` call
   `stop(container)` and the interval end. Polling a dead endpoint every
   10s for as long as the tab live is not resilience, it is doomed
   traffic. Still silent — no `console.error`. The set is EXPLICIT for a
   reason: do not widen it to "any non-429 status >= 400" or a transient
   5xx silently kill live updates for the tab until a manual reload.
3. **Malformed body** (no `data`/`replies` array): return without
   back-off. Next cycle poll at full speed.
4. **Success**: reset BOTH back-off maps to 0, append rows, advance
   `lastId`, write `data-last-id` back.

`HttpClient` throw an `Error` carrying `.status`, which is how a terminal
status is told apart from a transient one. Never `window.clearInterval`
on a TRANSIENT failure, and never leave a bare `catch {}` — a named
handler with a comment is required so the resilience contract is visible
in the code.

## JS Modules Have No jQuery/Alpine Dependency

An earlier design note called for "jQuery polling", but jQuery is not an
installed dependency (`package.json`). `ForumPolling.js` use shared `HttpClient` module
instead, same rationale as `ModuleReorder.js` native drag-and-drop.
The edit-history modal has **no JS module at all** since the Bootstrap
5.3 migration: `forum/partials/_edit-history-modal.blade.php` is fully
declarative (`data-bs-toggle="modal"` + `data-bs-target`), driven by
`bootstrap.Modal`. The old `ForumEditHistory.js` was deleted — do not
recreate it.
`ForumReportModal.js` prefill shared `#report-modal` hidden
`postable_type`/`postable_id` fields from clicked "Denunciar" button
`data-postable-type`/`data-postable-id`, then intercept modal form submit
to POST via `HttpClient` to `forum-reports.store`; closing goes through
`bootstrap.Modal.getOrCreateInstance`, never the retired `ModalManager`.
Both modules registered once in
`resources/js/app.js`, following same `DOMContentLoaded`-or-immediate
`init()` guard every other SOLID module in this project use.

## Blade Views: `x-layout.page-header`, `x-ui.confirm-modal`, No Inline `style=`

Every top-level forum screen (`forum/index`, `forum/show`, `forum/create`,
`forum/edit`, `forum/moderation/index`) render `<x-layout.page-header>`
with `:breadcrumb` (array of `['label' => ..., 'url' => ...]`), `kicker`,
`title`, and `subtitle` — no exception, even for screens with a simple
one-line title. Partials (`_topic`, `_reply`, `_edit-history-modal`) never
carry their own page-header.

`forum/moderation/index.blade.php` "Remover Publicação" goes through
`<x-ui.confirm-modal>` (hard rule: every removal does), never a bare
form-submit button. Because `x-ui.confirm-modal` owns the real `<form>`
internally, the frozen `dusk="remove-form-{id}"` selector (from
`tests/fixtures/dusk-selectors-snapshot.json`) moved onto the wrapping
`<div>` around trigger button + modal, while `dusk="remove-post-{id}"`
stays on the trigger `<x-ui.button>` that opens the modal via
`data-bs-toggle="modal"`/`data-bs-target`. Follow this same
container-owns-the-form-selector pattern for any other frozen `dusk=` on
a button that gets wrapped in `x-ui.confirm-modal`.

`forum/create.blade.php`/`forum/edit.blade.php` use `<x-ui.textarea>`
(not `x-ui.input type="textarea"`) for topic `content`, wrapped in
`max-w-640` (project's utility closest to a ~760px reading column — do
not invent a new `max-w-*` value), and use `<x-ui.form-actions
align="end">` for the Cancel/Submit row (max one primary button, Cancel
as `variant="ghost"`).

`.forum-reply` (on `forum/partials/_reply.blade.php`'s root `<div>`) is a
real class defined in `resources/scss/components/_card.scss` — it used to
be a phantom (no CSS backing it). Keep the literal string `forum-reply`
unchanged in both the Blade view and `ForumPolling.js::appendReply()`
(`el.className = 'forum-reply card mb-2'`) — they must always match,
since polling-injected replies never pass through Blade. Same rule for
every other class `appendReply()` hard-code (`ds-avatar ds-avatar-lg`,
`badge ds-badge border ds-muted`, `card-body py-3`, `text-prewrap`): they
are the RENDERED output of `x-ui.avatar`/`x-ui.badge`, so changing either
component's class list is a `ForumPolling.js` change too.

## Forum UI Contract (Material Bootstrap Redesign)

Frozen contract shared by `forum/index.blade.php`,
`forum/partials/_topic.blade.php` and every browser test. Breaking any
line here break `ForumPollingAndInteractionDuskTest` or
`DuskSelectorContractTest`.

Dusk selectors:

| Selector | Element |
| --- | --- |
| `new-topic-button` | Header "Novo tópico" button, `≥ lg` only |
| `new-topic-fab` | Mobile FAB, `< lg` only |
| `empty-new-topic-button` | Empty-state action |
| `no-topics` | Empty state root |
| `topic-row-{id}` | Topic card |
| `open-topic-{id}` | Topic title anchor |
| `pinned-badge-{id}` | "Fixado" status chip |
| `pin-form-{id}` / `pin-topic-{id}` | Pin toggle form / submit button |
| `new-topic-form` / `new-topic-title` / `new-topic-content` / `new-topic-submit` | Creation modal |
| `replies-list` | `[data-forum-polling]` container |
| `reply-{id}` / `reply-content-{id}` | Reply card / body (also emitted by `appendReply()`) |
| `report-reply-{id}` | "Denunciar" on a reply (also emitted by `appendReply()`) |

Rules:

- **FAB vs header button, `lg` breakpoint.** Header action wrapped in
  `d-none d-lg-inline-flex`; `<x-ui.fab>` wrapped in `d-lg-none`. The two
  are never visible together, and the FAB must reach the SAME
  `#new-topic-modal`. `.forum-container-with-fab` add the bottom safety
  gap below `lg`; `.ds-fab` is `position: fixed`, so no ancestor may get
  `overflow: hidden` or a `transform`.
- **Footer-submit pattern.** `<form id="new-topic-form">` live in the
  modal BODY; the publish button live in the modal footer and reach it
  with the HTML5 `form="new-topic-form"` attribute. No nested form, no
  JS submit shim.
- **Pin form and topic link are DOM SIBLINGS, never nested.** The title
  anchor carry `stretched-link` and cover the whole card, so the pin
  `<form>` stay hit-testable only inside a `position-relative z-2`
  wrapper. Changing card layout or z-index silently make
  `pin-topic-{id}` unclickable while the DOM still look right — always
  re-run the pin step of `ForumPollingAndInteractionDuskTest`.
- **Avatar initials come from the shared `User` accessor, never a view
  closure.** `<x-ui.avatar :initials="$user->initials" size="lg" />` —
  the exact accessor the polling payload's `initials` field is read from,
  so a polled card and its server-rendered twin cannot drift. Do NOT
  compute initials in a view `@php` closure, and do NOT pass `:name` for
  a `User`: the component's `name` prop re-derive the initials in Blade
  and exist only for names that are not a user's (report aggregations,
  fixed copy, an Organization's name). `User::initials()` is the rule;
  `x-ui.avatar`'s `name` prop and `ForumPolling.js`'s `initialsFrom()`
  (the JS fallback used ONLY when the payload omit `initials`) both
  mirror it and must stay character-for-character identical to it.
- **"Fixado" is a STATIC chip.** `<x-ui.chip :static="true"
  variant="info">` renders `<span class="ds-chip ds-chip-info
  ds-chip-static">`. A default `<x-ui.chip>` render a `<button>` — a
  focusable control that submits nothing, an a11y and keyboard-order
  defect inside a stretched-link card. Never raw Bootstrap
  `<span class="badge bg-info-subtle">` here (bootstrap-conventions
  no-raw-Bootstrap-markup guardrail).

## Related Skills

- `quizzes-conventions` — `parentCourse()` cascade-authorize pattern this
  module Policies mirror.
- `courses-conventions` — `[data-reorder-url]`-style contract this
  module polling/report JS conventions follow (shared `HttpClient`/
  `NotificationService` singletons wired in `resources/js/modules/index.js`;
  dialogs go through `bootstrap.Modal`, there is no `ModalManager`).
