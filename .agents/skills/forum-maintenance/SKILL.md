---
name: forum-maintenance
description: >
  Debug, test, edge-case guide for Course Discussion Forum feature
  (SPEC-10): mandatory PHPUnit/Dusk test files, common
  postable_type/withTrashed() and cross-tenant Policy failure modes,
  ForumTopic::withoutEvents() gotcha, frontend-build gotcha for
  ForumPolling.js/ForumReportModal.js. Use when
  ForumTopicTest, XssSanitizationTest, ForumModerationQueueTest, or
  ForumEditHistoryTest fail; report postable can't resolve; multi-org
  Aluno gets UnresolvedOrgContextException creating topic; or "ver
  histórico" modal/report button/polling dead in browser.
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

These tests guard SPEC-10 contract, must stay green (PHPUnit, no Pest):

- `tests/Feature/ForumTopicTest.php` — Topic/Reply CRUD, `pinnedFirst()`
  ordering, `student.enrolled` gating, `since_id` polling contract.
- `tests/Feature/XssSanitizationTest.php` — `ForumContentSanitizerService`
  strip tags from topic/reply `content` and report `reason` on every
  write path.
- `tests/Feature/ForumModerationQueueTest.php` — `GET /forum/moderation`
  own-org filtering, dismiss/remove transitions, direct pin/edit/delete by
  Gestor/Admin independent of any report.
- `tests/Feature/ForumEditHistoryTest.php` — `forum_post_edits` rows
  written on every edit/delete, public (non-author-only) visibility of
  "ver histórico" modal.
- `tests/Unit/Models/ForumTopicTest.php`, `ForumReplyTest.php`,
  `ForumPostEditTest.php`, `ForumReportTest.php` — relations, casts,
  `postable()` resolution (including `withTrashed()`).
- `tests/Unit/Policies/ForumTopicPolicyTest.php`,
  `ForumReplyPolicyTest.php` — `hasCourseAccess()`/
  `isGestorOrAdminForCourse()` cross-org/enrollment matrices.
- `tests/Browser/ForumDuskTest.php` — full browser flow: create topic,
  reply, polling picking up new reply, edit-history modal, report modal,
  moderation queue dismiss/remove.

Run narrowest of these first after touching this module:

```bash
vendor/bin/sail artisan test --filter=ForumTopicTest
vendor/bin/sail artisan test --filter=XssSanitizationTest
vendor/bin/sail artisan test --filter=ForumModerationQueueTest
vendor/bin/sail artisan test --filter=ForumEditHistoryTest
vendor/bin/sail dusk --filter=ForumDuskTest
```

## `ForumReport`/`ForumPostEdit` `postable()` Returns `null`

- Confirm `postable_type` persisted as model **FQCN**
  (`App\Models\ForumTopic`), not short `forum_topic` label. Only
  `ForumReportController::resolvePostable()` may translate short label,
  and only before calling `ReportForumPostAction`. Stray direct
  `ForumReport::create(['postable_type' => 'forum_topic', ...])`
  anywhere else break `$type::withTrashed()->find(...)`.
- `postable()` must use `withTrashed()` — target post may be already
  soft-deleted (direct moderation removal happened before report
  reviewed, or author deleted own post). `find()` without
  `withTrashed()` silently return `null` for otherwise valid, resolvable
  report/history row.
- `ForumModerationController::index()` filter out any report whose
  `postable()` is `null` (e.g. `Course` hard-deleted via cascade,
  removing topic row entirely). Expected, not bug. Do not "fix" it by
  throwing — moderation queue must never 500 on dangling report.

## `UnresolvedOrgContextException` Thrown Creating `ForumTopic`

- Means `ForumTopicController::store()` `ForumTopic::withoutEvents()`
  wrapper removed or bypassed. `OrgScope` `creating` hook then try
  resolve `org_id` from `$user->org_id ?? session('active_org_id')`,
  which multi-org Aluno (RF19, `org_id === null`, no impersonation
  session) have neither of, even though `$courseModel->org_id` right
  there. Restore `withoutEvents()` wrapper instead of setting
  `session('active_org_id')` for Aluno — that session key is
  Admin-Impersonate-Org-only (see `tenancy-architecture`).

## Cross-Org / Unenrolled Access Returns Wrong Status Code

- 404 where 403 expected almost always mean controller method used typed
  `Course $course`/`ForumTopic $topic` implicit binding instead of
  `resolveCourse()`/`resolveTopic()` `int`-parameter +
  `withoutGlobalScopes()` pattern (see `forum-conventions`).
  `OrgScope`d implicit-binding query filtered row out before Policy ran.
- 403 where enrollment access expected: check
  `hasActiveOrCompletedEnrollment($course)` not called against `Course`
  still carrying `OrgScope` (i.e. `parentCourse()` did not call
  `withoutGlobalScopes()`). Cross-org lookup there come back as if course
  do not exist, turning legitimate 200 into false 403.

## `ForumPolling.js`/`ForumReportModal.js` Dead in Browser

- Confirm both modules registered in `resources/js/modules/index.js`
  `DOMContentLoaded` bootstrap and `public/build` not stale relative to
  `resources/js/modules/Forum*.js` — run `vendor/bin/sail npm run build`
  — `vendor/bin/sail npm run build` only, never `npm run dev`, which
  leaves `public/hot` behind and kills the whole Dusk suite (see
  `laravel-dusk`).
- Polling silently doing nothing: check `[data-forum-polling]` container
  `data-fetch-url` really resolve to `forum-replies.fetch` for **current**
  topic. Stale/missing attribute mean `bind()` return early without
  registering interval, no error surfaced.
- Edit-history modal not opening: it is declarative
  (`data-bs-toggle="modal"` in
  `forum/partials/_edit-history-modal.blade.php`), so the failure is
  almost always the JS bundle never loading `bootstrap` at all, or a
  stale `public/build`. A history modal injected *after* page load by
  `ForumPolling.js` appending a new reply still works, because
  `bootstrap.Modal` resolves the trigger by delegation — what will not
  work is a duplicate `id`, which makes the trigger open the first
  matching modal
  pre-hidden. This module scan once at page load on purpose, matching
  rest of forum server-rendered history (no AJAX re-render of history
  modals).
- Report modal submitting empty `postable_type`/`postable_id`: confirm
  clicked "Denunciar" button carry both
  `data-postable-type`/`data-postable-id` and that `prefill()` found
  shared `[data-forum-report-form]`. Form and every trigger button must
  share the same `#report-modal` id, opened declaratively by
  `data-bs-toggle="modal"` + `data-bs-target="#report-modal"`.

## `DuskSelectorContractTest` Fails After Only Adding Explanatory Comments

`DuskSelectorContractTest` regex-matches `dusk="..."` literally, including
inside Blade `{{-- ... --}}` comments — it does not parse HTML/Blade, it
greps the raw file. Writing a comment that quotes the frozen selector as
`dusk="remove-form-{id}"` (e.g. explaining why a selector moved onto a
wrapping `<div>`) inflates the matched-selector count and fails the
snapshot diff, even though no real `dusk=` attribute changed. When
documenting a selector in a comment, describe it without the literal
`dusk="..."` attribute form (e.g. "the remove-form-{id} selector"), or the
contract test will count it as a new occurrence.

## Auto-Update Protocol (SPEC-03)

Per `spec/specs/03-agentic-harness-and-self-updating-skills.md`: any
change to `ForumTopicController`/`ForumReplyController`/
`ForumReportController`/`ForumModerationController`,
`EditForumPostAction`/`DeleteForumPostAction`/`ReportForumPostAction`,
`ForumTopicPolicy`/`ForumReplyPolicy`, `ForumContentSanitizerService`,
`forum.*`/`forum-replies.*`/`forum-reports.*`/`forum-moderation.*` routes,
Blade views under `resources/views/forum/`, or
`ForumPolling.js`/`ForumReportModal.js` **must**
update all three forum skills (`forum-architecture`, `forum-conventions`,
`forum-maintenance`) in same change, before task counted done. Also
re-check `tenancy-architecture` cascade-inherited table list for
`forum_replies` and its pseudo-polymorphic-table note whenever 5th forum
table or new `postable_type` added.

## Related Specs

- `spec/specs/10-course-discussion-forum.md` — RF22, RF26, RF27, RN08,
  RN10.
- `quizzes-maintenance` — analogous `parentCourse()`-cascade Policy
  test-matrix pattern this module Policy tests copy one level shallower.
- `certificates-maintenance` — analogous "resolve pseudo-polymorphic
  pointer defensively, never let dangling one 500" pattern.

---

## E2E Coverage Lives in Lifecycle Chains, Not in Per-Module File

Browser tests in `tests/Browser/` grouped by **user journey (lifecycle
chain)** — one method drive create → edit → state change → delete →
consequence — **not** by module, spec, or use case. Consequences when
maintaining this module:

- **Finding coverage**: Dusk scenarios listed above may be asserted as
  numbered steps inside chain method, possibly in file named after
  another module when journey cross module boundaries. Locate them with
  `grep -rn "<route name|dusk selector>" tests/Browser/`, not by file
  name. Missing per-module file is **not** coverage gap.
- **Adding coverage**: extend existing chain for that journey with new
  numbered step carrying own UI **and** DB assertion. New method only for
  independent negatives (403, cross-tenant, other actor); new file only
  for genuinely new journey.
- **Debugging failure**: stack trace point at step, not whole scenario —
  match line to its `// N.` comment. Late failure usually mean earlier
  step did not persist what it should.
- **Database**: no DB trait declared in `tests/Browser/*`;
  `DatabaseTruncation` inherited from `Tests\DuskTestCase`. Re-adding
  `DatabaseMigrations` is suite-wide performance regression. Files,
  cache and session **not** reset between methods.

Full rule: `testing-conventions`. Chain debugging: `testing-maintenance`.
