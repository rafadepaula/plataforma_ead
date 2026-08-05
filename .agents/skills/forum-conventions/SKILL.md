---
name: forum-conventions
description: >
  Concrete code patterns, snippets, and guardrails for the Course
  Discussion Forum feature (SPEC-10): the ForumTopic::withoutEvents()
  org_id workaround, ForumTopicPolicy/ForumReplyPolicy cascade-authorize
  conventions, the postable_type FQCN convention shared by
  EditForumPostAction/DeleteForumPostAction/ReportForumPostAction, the
  ForumContentSanitizerService strip_tags() defense, and the
  ForumPolling.js/ForumEditHistory.js/ForumReportModal.js JS module
  contracts. Use whenever writing a controller, Policy, Form Request,
  Blade view, or JS module that manages ForumTopic/ForumReply/
  ForumPostEdit/ForumReport records.
license: MIT
metadata:
  feature: forum
  role: conventions
  specs:
    - spec/specs/10-course-discussion-forum.md
---

# Forum Conventions

## `{course}`/`{topic}`/`{reply}` Are Plain `int` Route Parameters, Never Typed Bindings

`ForumTopicController`/`ForumReplyController`/`ForumModerationController`
never type-hint `Course $course`/`ForumTopic $topic` in a route method
(except `ForumModerationController`'s `ForumReport $forumReport`, which
carries no `OrgScope`, so implicit binding is safe there). Both `Course`
and `ForumTopic` carry `OrgScope`, and a multi-org Aluno (`org_id ===
null`, no impersonation session) would have Laravel's implicit-binding
query silently filtered to nothing under that scope, turning every
request into a 404 instead of a proper Policy-driven 403:

```php
public function show(Request $request, int $course, int $topic): View
{
    $courseModel = $this->resolveCourse($course); // Course::query()->withoutGlobalScopes()->findOrFail()
    $topicModel = $this->resolveTopic($topic, $courseModel);
    Gate::authorize('view', $topicModel);
    // ...
}
```

Both controllers keep a private `resolveCourse()`/`resolveTopic()`/
`resolveReply()` helper doing `withoutGlobalScopes()->findOrFail()` —
reuse these, never a bare `Course::findOrFail($id)` inside a forum
controller.

## `ForumTopic::withoutEvents()` Is the One `store()`-Time `OrgScope` Workaround

`ForumTopicController::store()` is the **only** place in this module that
creates an `OrgScope`d model — `OrgScope`'s `creating` hook (see
`tenancy-architecture`) resolves `org_id` from `$user->org_id ??
session('active_org_id')`, which a multi-org Aluno has neither of, even
though the target Course's tenant is perfectly well known from
`$courseModel->org_id`:

```php
$topic = ForumTopic::withoutEvents(fn () => ForumTopic::query()->create([
    'org_id' => $courseModel->org_id,
    'course_id' => $courseModel->id,
    'user_id' => $request->user()->id,
    'title' => $request->validated('title'),
    'content' => $this->sanitizer->sanitize($request->validated('content')),
]));
```

Every other forum write either targets a non-`OrgScope`d model
(`ForumReply`/`ForumPostEdit`/`ForumReport`) or `update()`s an existing
`ForumTopic` row (no `creating` hook fires on update) — do not copy this
`withoutEvents()` pattern outside of `ForumTopic::create()`.

## Sanitize on Write, Escape on Read — Never `{!! !!}` a Forum Field

`ForumContentSanitizerService::sanitize()` (`trim(strip_tags($content))`)
is the **entire** write-side XSS defense — no HTML-purifier package is
installed (CLAUDE.md: no new dependencies without approval). It must be
called on every persisted forum text field: topic/reply `content`
(`ForumTopicController::store()`, `EditForumPostAction`), and report
`reason` (`ReportForumPostAction`). Blade's default `{{ }}` escaping is
the read-side layer — never render `content`/`title`/`reason`/
`previous_content` with `{!! !!}` anywhere in `resources/views/forum/`.

## Policy Ability Naming Mirrors `QuizPolicy`'s Cascade Pattern, One or Two Hops Deep

`ForumTopicPolicy::parentCourse()` walks one hop
(`$topic->course()->withoutGlobalScopes()->firstOrFail()`);
`ForumReplyPolicy::parentCourse()` walks two
(`reply->topic()->withoutGlobalScopes()` then reuses
`parentTopicCourse()` on that topic) — both bypass every `OrgScope`
along the chain, exactly like `QuizPolicy::parentCourse()` two levels
into `Lesson->Module->Course`. `update`/`delete` are always: post author
by `user_id` match, **or** same-org Gestor/Admin via
`isGestorOrAdminForCourse()`. `pin` exists only on `ForumTopicPolicy` —
`ForumReply` has no `is_pinned` column and no `pin` ability.

When authorizing a `ForumReply` create against its parent `ForumTopic`,
always pass the pair explicitly — `Gate::authorize('create', $topicModel)`
alone resolves the Policy by `$topicModel`'s own class
(`ForumTopicPolicy`, whose `create(User, Course)` signature won't match):

```php
Gate::authorize('create', [ForumReply::class, $topicModel]);
```

## `postable_type` Is Always Written as an FQCN, Never a Short Label

`EditForumPostAction`/`DeleteForumPostAction`/`ReportForumPostAction` all
write `'postable_type' => $post::class` (i.e. `ForumTopic::class`/
`ForumReply::class`) — a real PHP class-string, because
`ForumPostEdit::postable()`/`ForumReport::postable()` call
`$type::withTrashed()->find(...)` directly on that column's value. The
`forum_topic`/`forum_reply` short strings only exist at the HTTP/view/JS
boundary (`data-postable-type` attributes, `StoreForumReportRequest`'s
input) — `ForumReportController::resolvePostable()` is the single place
that maps a short label (or an already-FQCN value) to the real model
class before any Action runs. Never persist the short string directly.

## `forum-replies.fetch`'s `since_id` Polling Contract

`ForumReplyController::fetchNew()` only returns rows with `id > since_id`
(never a full re-fetch), ascending by `id`, capped `limit(50)`:

```json
{ "data": [ { "id": 42, "content": "...", "created_at": "02/08/2026 14:30",
              "user": { "name": "Ana" } }, ... ] }
```

`ForumPolling.js` binds one `setInterval` (10s) per
`[data-forum-polling]` container, tracking `lastId` client-side from
`data-last-id` and each response's max `id`; a poll failure (including
`throttle:60,1`'s 429) is swallowed silently so the next tick just
retries — never let a failed poll break the page or clear the interval.
`forum-replies.fetch` carries its **own** `throttle:60,1` scoped to just
that route, not the whole forum route group, so posting/editing/deleting
a topic/reply is never subject to the polling rate limit.

## JS Modules Have No jQuery/Alpine Dependency

The spec text says "jQuery polling", but jQuery is not an installed
dependency (`package.json`) — `ForumPolling.js` uses the shared
`HttpClient` module instead, same rationale as `ModuleReorder.js`'s
native drag-and-drop. Likewise `ForumEditHistory.js` doesn't use Alpine's
`x-show` (not installed) — it manually hides every `[id^="edit-history-"]`
modal's backdrop on init and delegates opening to the shared
`window.ModalManager`. `ForumReportModal.js` prefills the shared
`#report-modal`'s hidden `postable_type`/`postable_id` fields from the
clicked "Denunciar" button's `data-postable-type`/`data-postable-id`
before `ModalManager` opens it, then intercepts the modal form's submit
to POST via `HttpClient` to `forum-reports.store`. All three modules are
registered once in `resources/js/app.js`, following the same
`DOMContentLoaded`-or-immediate `init()` guard every other SOLID module
in this project uses.

## Related Specs

- `spec/specs/10-course-discussion-forum.md` — RF22, RF26, RF27.
- `quizzes-conventions` — the `parentCourse()` cascade-authorize pattern
  this module's Policies mirror.
- `courses-conventions` — the `[data-reorder-url]`-style contract this
  module's polling/report JS conventions follow (shared `HttpClient`/
  `ModalManager`/`NotificationService` singletons wired in `app.js`).
