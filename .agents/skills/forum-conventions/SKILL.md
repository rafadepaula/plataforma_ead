---
name: forum-conventions
description: >
  Code patterns, snippets, guardrails for Course Discussion Forum feature
  (SPEC-10): ForumTopic::withoutEvents() org_id workaround,
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
  specs:
    - spec/specs/10-course-discussion-forum.md
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
    $courseModel = $this->resolveCourse($course); // Course::query()->withoutGlobalScopes()->findOrFail()
    $topicModel = $this->resolveTopic($topic, $courseModel);
    Gate::authorize('view', $topicModel);
    // ...
}
```

Both controllers keep private `resolveCourse()`/`resolveTopic()`/
`resolveReply()` helper doing `withoutGlobalScopes()->findOrFail()`.
Reuse these, never bare `Course::findOrFail($id)` inside forum
controller.

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
(never full re-fetch), ascending by `id`, capped `limit(50)`:

```json
{ "data": [ { "id": 42, "content": "...", "created_at": "02/08/2026 14:30",
              "user": { "name": "Ana" } }, ... ] }
```

`ForumPolling.js` bind one `setInterval` (10s) per
`[data-forum-polling]` container, track `lastId` client-side from
`data-last-id` and each response max `id`. Poll failure (including
`throttle:60,1` 429) swallowed silently so next tick retry. Never let
failed poll break page or clear interval. `forum-replies.fetch` carry
**own** `throttle:60,1` scoped to that route only, not whole forum route
group, so posting/editing/deleting topic/reply never subject to polling
rate limit.

## JS Modules Have No jQuery/Alpine Dependency

Spec text say "jQuery polling", but jQuery not installed dependency
(`package.json`). `ForumPolling.js` use shared `HttpClient` module
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
since polling-injected replies never pass through Blade.

## Related Specs

- `spec/specs/10-course-discussion-forum.md` — RF22, RF26, RF27.
- `spec/front_redesign/08-telas-forum.md` — Fase 6 front redesign scope
  (breadcrumb/kicker/title/subtitle contract, component usage, phantom
  class cleanup) for all 8 forum views.
- `quizzes-conventions` — `parentCourse()` cascade-authorize pattern this
  module Policies mirror.
- `courses-conventions` — `[data-reorder-url]`-style contract this
  module polling/report JS conventions follow (shared `HttpClient`/
  `NotificationService` singletons wired in `resources/js/modules/index.js`;
  dialogs go through `bootstrap.Modal`, there is no `ModalManager`).
