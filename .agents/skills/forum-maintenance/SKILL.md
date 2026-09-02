---
name: forum-maintenance
description: >
  Debug, test, edge-case guide for Course Discussion Forum feature:
  mandatory PHPUnit/Dusk test files, common
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
---

# Forum Maintenance

## Mandatory Test Coverage for This Module

These tests guard this module's contract, must stay green (PHPUnit, no Pest):

- `tests/Feature/ForumTopicTest.php` — Topic/Reply CRUD, `pinnedFirst()`
  ordering, `student.enrolled` gating, `since_id` polling contract.
- `tests/Feature/ForumTopicControllerTest.php` — controller contract:
  `canCreateTopic`/`canPin` flags per role, 15-per-page pagination,
  `pinnedFirst()` ordering, store validation + sanitization, multi-org
  Aluno store (`org_id === null`) NOT throwing
  `UnresolvedOrgContextException`, `lastReplyId` on show, pin both ways,
  cross-org Gestor 403, non-enrolled Aluno redirect.
- `tests/Feature/ForumReplyControllerTest.php` — reply store/update/
  destroy plus the `fetchNew` payload: `id > since_id` only, ascending,
  capped at 50, and the `initials`/`role_label`/`last_id` keys.
  Duplicate coverage with `ForumTopicTest` is intentional — both files are
  mandatory and no test may be removed without approval.
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
  reply, edit-history modal, report modal, moderation queue
  dismiss/remove, author self-delete via confirm-modal.
- `tests/Browser/ForumPollingAndInteractionDuskTest.php` — 7 chains:
  (1) empty state copy + desktop modal creation + listing + pin/unpin
  lifecycle + static `ds-chip-info` chip assertions;
  (2) below-`lg` viewport (390x900) — header button not visible, FAB
  visible, publish through the FAB, resize BACK to 1440 at the end;
  (3) the NATURAL 10s interval delivering a background-created reply,
  with avatar-initials/role-badge parity, the relative-vs-absolute
  timestamp split (visible text is `created_at_relative`, `title=` holds
  `d/m/Y H:i`), the `textContent` XSS assertions on a raw
  `<script>window.forumPollingXss = true;</script>` row written straight
  into the DB, the `data-last-id` write-back, and the ACTION parity of
  the injected card — `report-reply-{id}` present with
  `data-postable-type="forum_reply"` + `#report-modal` wiring, and ZERO
  `<form>` inside it (the per-viewer "Apagar" form is never cloned);
  (4) the injected "Denunciar" is not just attributes — clicking it on a
  polled card open the shared `#report-modal`,
  `ForumReportModal` prefill the hidden `postable_*` fields from
  `event.relatedTarget`, `forum-reports.store` persist a
  `ForumReply::class` row with `status = pending`, and the moderation
  queue list the reply content — with NO page reload in between;
  (5) the loop surviving a `throttle:60,1` 429 — the page burns the
  60/min budget itself, `window.__forum429` proves the limiter answered,
  then a later reply must still land with the interval still alive;
  (6) the terminal branch for the moderation 404 — a topic removed while
  the tab is open answers 404 and
  `window.ForumPolling.timers.size === 0` (the loop re-binds itself at
  500ms first so the doomed cycle come fast, and a spy on
  `handleTransportFailure()` pin `window.__forumTeardownStatus === 404`,
  because a bare `timers.size === 0` would also pass for any other reason
  the interval disappear);
  (7) the rest of `TERMINAL_STATUSES` — 401/419/403 each end the loop,
  while the control (502 mid-deploy) keep it alive with
  `backoffCycles > 0`. Driven by stubbing `window.ForumPolling.httpClient`
  and re-binding the SAME container, so the production
  `bindContainer()`/`poll()`/`handleTransportFailure()` path run with only
  the network faked.
  Runtime: chain (3) ~20s, chain (5) ~70s (a full rate-limit window plus
  the client back-off). Slow BY DESIGN — do not "fix" them by hand-firing
  a `fetch` from `browser->script()`, which proves nothing about the
  interval.

Run narrowest of these first after touching this module:

```bash
vendor/bin/sail artisan test --filter=ForumTopicTest
vendor/bin/sail artisan test --filter=ForumTopicControllerTest
vendor/bin/sail artisan test --filter=ForumReplyControllerTest
vendor/bin/sail artisan test --filter=XssSanitizationTest
vendor/bin/sail artisan test --filter=ForumModerationQueueTest
vendor/bin/sail artisan test --filter=ForumEditHistoryTest
vendor/bin/sail npm run build   # MANDATORY before Dusk after any JS edit
vendor/bin/sail dusk --filter=ForumDuskTest
vendor/bin/sail dusk --filter=ForumPollingAndInteractionDuskTest
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
  which multi-org Aluno (`org_id === null`, no impersonation
  session) have neither of, even though `$courseModel->org_id` right
  there. Restore `withoutEvents()` wrapper instead of setting
  `session('active_org_id')` for Aluno — that session key is
  Admin-Impersonate-Org-only (see `tenancy-architecture`).

## Cross-Org / Unenrolled Access Returns Wrong Status Code

- 404 where 403 expected almost always mean controller method used typed
  `Course $course`/`ForumTopic $topic` implicit binding instead of
  `resolveCourse()`/`resolveTopic()` `int`-parameter +
  `withoutGlobalScope('org')` pattern (see `forum-conventions`).
  `OrgScope`d implicit-binding query filtered row out before Policy ran.
- 403 where enrollment access expected: check
  `hasActiveOrCompletedEnrollment($course)` not called against `Course`
  still carrying `OrgScope` (i.e. `parentCourse()` did not call
  `withoutGlobalScopes()`). Cross-org lookup there come back as if course
  do not exist, turning legitimate 200 into false 403.

## Topic Removed by Moderation Still Visible / Still Accepting Replies

- Symptom: a soft-deleted `ForumTopic` keep showing on `forum.index`,
  `forum.show` render 200 instead of 404, pin and reply still work, and
  an open tab keep receiving polled replies out of it.
- Cause: a controller lookup regressed to bare `withoutGlobalScopes()`,
  which drop `SoftDeletingScope` along with `OrgScope`. Fix is
  `withoutGlobalScope('org')` in `ForumTopicController::index()`/
  `resolveCourse()`/`resolveTopic()` and
  `ForumReplyController::resolveTopic()` — see `forum-conventions`,
  "Drop `OrgScope` by name".
- Policy `parentCourse()` and `ForumReportController::resolvePostable()`
  are the two intentional bare-`withoutGlobalScopes()` site. Do not
  "fix" those: the first must resolve a parent even when trashed or the
  intended 403 become a type error, the second must open a report filed
  against an already-removed post.
- Reply-level equivalent (a single reply soft-deleted, topic alive) is
  already safe: `fetchNew` read `$topicModel->replies()`, which keep its
  own `SoftDeletingScope`. Regression there would mean the relation
  itself grew a `withTrashed()`.

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

## Polled Reply Renders, But Looks Wrong

- **Blank avatar circle / missing role badge**: `fetchNew()` stopped
  publishing `initials`/`role_label`, or it eager-loads `with('user')`
  instead of `with('user.roles')` so `role_label` resolve empty.
  `appendReply()` degrade instead of breaking (client-side initials
  fallback, badge suppressed), so the symptom is cosmetic and silent —
  check the JSON first with
  `curl` on `forum-replies.fetch`, not the JS.
- **Card structurally different from a server-rendered one**: someone
  changed `forum/partials/_reply.blade.php`, `x-ui.avatar` or
  `x-ui.badge` without changing `appendReply()`'s hard-coded class
  strings. Chain (3) of `ForumPollingAndInteractionDuskTest` assert
  `.ds-avatar` text, `.ds-badge` text and the timestamp split (relative
  text visible, absolute only in `title=`) inside
  `[dusk="reply-{id}"]`.
- **Injected reply shows an absolute date where server-rendered replies
  show "há 2 minutos"**: `appendReply()` printed `created_at` instead of
  `created_at_relative`, or `fetchNew()` stopped publishing
  `created_at_relative`. The JS falls back to the absolute value when the
  relative one is missing, so a payload regression degrade quietly —
  chain (3) catch it.
- **Raw HTML rendering inside a reply**: `appendReply()` gained an
  `innerHTML`/`insertAdjacentHTML` call. Hard rule violation — the DB
  content is only sanitized on the WRITE paths that go through
  `ForumContentSanitizerService`, so a row inserted any other way is raw.

## Polling Loop Dies or Spams the Console

- **Loop stops after a burst of traffic**: `handleTransportFailure()` was
  replaced by a `clearInterval`, or the `catch` swallowed the error
  without preserving the interval. `throttle:60,1` on
  `forum-replies.fetch` is 60/min while one tab spend 6/min — two or
  three tabs on the same topic, or a devtools reload loop, trip 429
  routinely. Assert with chain (5) of
  `ForumPollingAndInteractionDuskTest`; check
  `window.ForumPolling.timers.size > 0` in the console. The back-off
  DE-escalates on the next successful cycle (`backoffSteps` reset to 0
  with `backoffCycles`), so a single 429 never leave a thread skipping
  cycles forever.
- **Loop stops on a 404/403/419, not on a 429**: expected, by design.
  `handleTransportFailure()` call `stop(container)` ONLY for the four
  statuses in `TERMINAL_STATUSES` (`ForumPolling.js`) — topic removed by
  moderation, expired session, revoked policy — because that endpoint
  will never answer that page again. If a topic got removed while tabs
  were open, this is the loop ending, not a bug. Everything else keeps
  the interval alive: 429, network drops (`status === 0`) AND the 5xx
  range (500/502 mid-deploy, 503 maintenance mode) — a deploy or a
  maintenance window must stand the loop down for a few ticks, never
  kill it. If live updates died during a deploy, someone widened
  `TERMINAL_STATUSES` to "any non-429 status >= 400"; narrow it back.
- **429 noise in the console**: the failure handler must stay silent. No
  `console.error`/`console.warn` on a transport failure — terminal ones
  included.
- **Replies stop arriving but no error**: `data-last-id` advanced past
  what the server has. It is written back only after a SUCCESSFUL cycle,
  and it honour the payload's top-level `last_id`; a controller returning
  a wrong `last_id` (e.g. a global max instead of this batch's max) skip
  every reply in between forever.
- **New replies duplicate**: the `[data-reply-id="N"]` dedupe guard was
  dropped, or Blade stopped emitting `data-reply-id` on the reply root.

## Dusk Polling Chain Fails Right After a JS Edit

`public/build` is stale. Run `vendor/bin/sail npm run build` — never
`npm run dev`, which leaves `public/hot` behind and kills the whole Dusk
suite. This is the single most common wrong-reason failure in this
module: the assertion that breaks is usually a parity assertion
(`.ds-avatar` text, `.ds-badge` text), which makes it look like a Blade
or controller bug.

## Mobile FAB Chain Fails

- `assertMissing('@new-topic-button')` failing at 390px: the header
  action lost its `d-none d-lg-inline-flex` wrapper, or the breakpoint
  moved. The two entry points must never both be visible.
- FAB present but unclickable/clipped: an ancestor added
  `overflow: hidden` or a `transform`, which break `.ds-fab`'s
  `position: fixed`. Also check `.forum-container-with-fab` still sit on
  the wrapper that holds the LAST card, or the FAB cover it.
- Chain (2) resize back to 1440x900 at the end on purpose — the browser
  instance is reused across methods and a leftover mobile viewport make
  the next chain fail on `new-topic-button`.

## `pin-topic-{id}` Not Clickable Though the DOM Looks Right

The topic title anchor carry `stretched-link` and cover the entire card.
The pin `<form>` stay hit-testable ONLY because its wrapper carry
`position-relative z-2`. Any card layout or z-index change in
`_topic.blade.php` silently reintroduce the overlap; Dusk report a click
that navigated to the thread instead of submitting. Re-run step 4 of
chain (1).

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

## Auto-Update Protocol

Any
change to `ForumTopicController`/`ForumReplyController`/
`ForumReportController`/`ForumModerationController`,
`EditForumPostAction`/`DeleteForumPostAction`/`ReportForumPostAction`,
`ForumTopicPolicy`/`ForumReplyPolicy`, `ForumContentSanitizerService`,
`forum.*`/`forum-replies.*`/`forum-reports.*`/`forum-moderation.*` routes,
Blade views under `resources/views/forum/`, or
`ForumPolling.js`/`ForumReportModal.js`, or the `User::initials()`/
`User::roleLabel()` accessors the polling payload and the Blade avatar/
badge share, **must**
update all three forum skills (`forum-architecture`, `forum-conventions`,
`forum-maintenance`) in same change, before task counted done. Also
re-check `tenancy-architecture` cascade-inherited table list for
`forum_replies` and its pseudo-polymorphic-table note whenever 5th forum
table or new `postable_type` added.

## Related

- `quizzes-maintenance` — analogous `parentCourse()`-cascade Policy
  test-matrix pattern this module Policy tests copy one level shallower.
- `certificates-maintenance` — analogous "resolve pseudo-polymorphic
  pointer defensively, never let dangling one 500" pattern.

---

## E2E Coverage Lives in Lifecycle Chains, Not in Per-Module File

Browser tests in `tests/Browser/` grouped by **user journey (lifecycle
chain)** — one method drive create → edit → state change → delete →
consequence — **not** by module or feature. Consequences when
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
