---
name: forum-conventions
description: >
  Code patterns, snippets, guardrails for Course Discussion Forum feature
  (SPEC-10): ForumTopic::withoutEvents() org_id workaround,
  ForumTopicPolicy/ForumReplyPolicy cascade-authorize conventions,
  postable_type FQCN convention shared by
  EditForumPostAction/DeleteForumPostAction/ReportForumPostAction,
  ForumContentSanitizerService strip_tags() defense,
  ForumPolling.js/ForumEditHistory.js/ForumReportModal.js JS module
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
Likewise `ForumEditHistory.js` do not use Alpine `x-show` (not
installed) — it manually hide every `[id^="edit-history-"]` modal
backdrop on init and delegate opening to shared `window.ModalManager`.
`ForumReportModal.js` prefill shared `#report-modal` hidden
`postable_type`/`postable_id` fields from clicked "Denunciar" button
`data-postable-type`/`data-postable-id` before `ModalManager` open it,
then intercept modal form submit to POST via `HttpClient` to
`forum-reports.store`. All three modules registered once in
`resources/js/app.js`, following same `DOMContentLoaded`-or-immediate
`init()` guard every other SOLID module in this project use.

## Related Specs

- `spec/specs/10-course-discussion-forum.md` — RF22, RF26, RF27.
- `quizzes-conventions` — `parentCourse()` cascade-authorize pattern this
  module Policies mirror.
- `courses-conventions` — `[data-reorder-url]`-style contract this
  module polling/report JS conventions follow (shared `HttpClient`/
  `ModalManager`/`NotificationService` singletons wired in `app.js`).
