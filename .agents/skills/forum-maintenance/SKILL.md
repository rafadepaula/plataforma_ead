---
name: forum-maintenance
description: >
  Debugging, testing, and edge-case guide for the Course Discussion Forum
  feature (SPEC-10): the mandatory PHPUnit/Dusk test files, common
  postable_type/withTrashed() and cross-tenant Policy failure modes, the
  ForumTopic::withoutEvents() gotcha, and the frontend-build gotcha for
  ForumPolling.js/ForumEditHistory.js/ForumReportModal.js. Use when
  ForumTopicTest, XssSanitizationTest, ForumModerationQueueTest, or
  ForumEditHistoryTest is failing; a report's postable can't be resolved;
  a multi-org Aluno gets an UnresolvedOrgContextException creating a
  topic; or the "ver histórico" modal/report button/polling isn't working
  in the browser.
license: MIT
metadata:
  feature: forum
  role: maintenance
  specs:
    - spec/specs/10-course-discussion-forum.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Forum Maintenance

## Mandatory Test Coverage for This Module

These tests guard the SPEC-10 contract and must stay green (PHPUnit, no
Pest):

- `tests/Feature/ForumTopicTest.php` — Topic/Reply CRUD, `pinnedFirst()`
  ordering, `student.enrolled` gating, `since_id` polling contract.
- `tests/Feature/XssSanitizationTest.php` — `ForumContentSanitizerService`
  stripping tags from topic/reply `content` and report `reason` on every
  write path.
- `tests/Feature/ForumModerationQueueTest.php` — `GET /forum/moderation`
  own-org filtering, dismiss/remove transitions, direct pin/edit/delete by
  Gestor/Admin independent of any report.
- `tests/Feature/ForumEditHistoryTest.php` — `forum_post_edits` rows
  written on every edit/delete, the public (non-author-only) visibility of
  the "ver histórico" modal.
- `tests/Unit/Models/ForumTopicTest.php`, `ForumReplyTest.php`,
  `ForumPostEditTest.php`, `ForumReportTest.php` — relations, casts,
  `postable()` resolution (including `withTrashed()`).
- `tests/Unit/Policies/ForumTopicPolicyTest.php`,
  `ForumReplyPolicyTest.php` — `hasCourseAccess()`/
  `isGestorOrAdminForCourse()` cross-org/enrollment matrices.
- `tests/Browser/ForumDuskTest.php` — full browser flow: create topic,
  reply, polling picking up a new reply, edit-history modal, report modal,
  moderation queue dismiss/remove.

Run the narrowest of these first after touching this module:

```bash
vendor/bin/sail artisan test --filter=ForumTopicTest
vendor/bin/sail artisan test --filter=XssSanitizationTest
vendor/bin/sail artisan test --filter=ForumModerationQueueTest
vendor/bin/sail artisan test --filter=ForumEditHistoryTest
vendor/bin/sail dusk --filter=ForumDuskTest
```

## A `ForumReport`/`ForumPostEdit`'s `postable()` Returns `null`

- Confirm `postable_type` was persisted as the model's **FQCN**
  (`App\Models\ForumTopic`), not the short `forum_topic` label — only
  `ForumReportController::resolvePostable()` is allowed to translate the
  short label, and only before calling `ReportForumPostAction`. A stray
  direct `ForumReport::create(['postable_type' => 'forum_topic', ...])`
  anywhere else breaks `$type::withTrashed()->find(...)`.
- Remember `postable()` must use `withTrashed()` — the target post may
  already be soft-deleted (direct moderation removal happened before the
  report was reviewed, or the author deleted their own post). A `find()`
  without `withTrashed()` will silently return `null` for an otherwise
  perfectly valid, resolvable report/history row.
- `ForumModerationController::index()` filters out any report whose
  `postable()` is `null` (e.g. a `Course` hard-deleted via cascade,
  removing the topic's row entirely) — this is expected, not a bug; don't
  "fix" it by throwing, since the moderation queue must never 500 on a
  dangling report.

## `UnresolvedOrgContextException` Thrown Creating a `ForumTopic`

- This means `ForumTopicController::store()`'s `ForumTopic::withoutEvents()`
  wrapper was removed or bypassed — `OrgScope`'s `creating` hook then tries
  to resolve `org_id` from `$user->org_id ?? session('active_org_id')`,
  which a multi-org Aluno (RF19, `org_id === null`, no impersonation
  session) has neither of, even though `$courseModel->org_id` is right
  there. Restore the `withoutEvents()` wrapper rather than trying to set
  `session('active_org_id')` for the Aluno — that session key is
  Admin-Impersonate-Org-only (see `tenancy-architecture`).

## Cross-Org / Unenrolled Access Returns the Wrong Status Code

- A 404 where a 403 was expected almost always means a controller method
  used a typed `Course $course`/`ForumTopic $topic` implicit binding
  instead of the `resolveCourse()`/`resolveTopic()` `int`-parameter +
  `withoutGlobalScopes()` pattern (see `forum-conventions`) — the
  `OrgScope`d implicit-binding query filtered the row out before the
  Policy ever ran.
- A 403 where enrollment access was expected: check
  `hasActiveOrCompletedEnrollment($course)` isn't being called against a
  `Course` still carrying `OrgScope` (i.e. `parentCourse()` didn't call
  `withoutGlobalScopes()`) — a cross-org lookup here comes back as if the
  course doesn't exist, turning a legitimate 200 into a false 403.

## `ForumPolling.js`/`ForumEditHistory.js`/`ForumReportModal.js` Not Working in the Browser

- Confirm all three modules are registered in `resources/js/app.js`'s
  `DOMContentLoaded` bootstrap and `public/build` is not stale relative to
  `resources/js/modules/Forum*.js` — run `vendor/bin/sail npm run build`
  (or ask the user to run `npm run dev`/`composer run dev`).
- Polling silently doing nothing: check the `[data-forum-polling]`
  container's `data-fetch-url` actually resolves to `forum-replies.fetch`
  for the **current** topic — a stale/missing attribute means `bind()`
  returns early without registering an interval, no error surfaced.
- Edit-history modal not opening: `ForumEditHistory.js` requires
  `window.ModalManager` to already be initialized and every
  `[id^="edit-history-"]` modal's backdrop to have been hidden on `init()`
  — a modal rendered *after* `bind()` ran (e.g. injected by
  `ForumPolling.js` appending a new reply with its own history modal) will
  not have its backdrop pre-hidden; this module intentionally only scans
  once at page load, matching the rest of the forum's server-rendered
  history (no AJAX re-render of history modals).
- Report modal submitting empty `postable_type`/`postable_id`: confirm the
  clicked "Denunciar" button actually carries both
  `data-postable-type`/`data-postable-id` and that `prefill()` found the
  shared `[data-forum-report-form]` — the form and every trigger button
  must share the same `data-modal-target="report-modal"` id `ModalManager`
  opens.

## Auto-Update Protocol (SPEC-03)

Per `spec/specs/03-agentic-harness-and-self-updating-skills.md`: any
change to `ForumTopicController`/`ForumReplyController`/
`ForumReportController`/`ForumModerationController`,
`EditForumPostAction`/`DeleteForumPostAction`/`ReportForumPostAction`,
`ForumTopicPolicy`/`ForumReplyPolicy`, `ForumContentSanitizerService`, the
`forum.*`/`forum-replies.*`/`forum-reports.*`/`forum-moderation.*` routes,
the Blade views under `resources/views/forum/`, or
`ForumPolling.js`/`ForumEditHistory.js`/`ForumReportModal.js` **must**
update all three forum skills (`forum-architecture`, `forum-conventions`,
`forum-maintenance`) in the same change, before the task is considered
done. Also re-check `tenancy-architecture`'s cascade-inherited table list
for `forum_replies` and its pseudo-polymorphic-table note whenever a 5th
forum table or a new `postable_type` is added.

## Related Specs

- `spec/specs/10-course-discussion-forum.md` — RF22, RF26, RF27, RN08,
  RN10.
- `quizzes-maintenance` — the analogous `parentCourse()`-cascade Policy
  test-matrix pattern this module's Policy tests copy one level shallower.
- `certificates-maintenance` — the analogous "resolve a pseudo
  -polymorphic pointer defensively, never let a dangling one 500" pattern.
