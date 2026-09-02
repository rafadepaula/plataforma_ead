---
name: forum-architecture
description: >
  Course Discussion Forum domain:
  forum_topics/forum_replies/forum_post_edits/forum_reports schema,
  OrgScope-on-ForumTopic vs cascade-inherited ForumReply tenancy,
  pseudo-polymorphic postable_type/postable_id pattern shared by
  forum_post_edits and forum_reports, no-deadline public edit-history
  contract, two moderation paths (report queue vs direct Gestor/Admin
  removal). Use when designing or reviewing feature touching
  ForumTopic/ForumReply/ForumPostEdit/ForumReport data, before adding new
  postable type, or when deciding how forum route gets
  tenant/enrollment-gated.
license: MIT
metadata:
  feature: forum
  role: architecture
---

# Forum Architecture

## Overview

Every Course has a discussion forum. Alunos with active/completed
enrollment and same-org Gestores/Admins create `forum_topics` and
`forum_replies`. jQuery-free `since_id` AJAX polling stand in for
websockets. Any viewer with topic access — not only
author — can open the public "ver histórico" edit history for a post.
A "Denunciar" report queue reviewed by Gestor also exists. That queue is
*second* moderation channel, not only one — Gestor/Admin also pin/edit/
delete any post directly, report or no report.

## Schema

| Table | Key columns | Tenancy |
| --- | --- | --- |
| `forum_topics` | `org_id`, `course_id`, `user_id`, `title` (≤200), `content`, `is_pinned`, `edited_at` (nullable), `deleted_at` (SoftDeletes) | **Directly org-scoped** — own `org_id` column, `OrgScope` trait applied |
| `forum_replies` | `topic_id`, `user_id`, `content`, `edited_at` (nullable), `deleted_at` (SoftDeletes) | **Cascade-inherited** via `topic_id` → `forum_topics.org_id` — no own `org_id`, no `OrgScope` |
| `forum_post_edits` | `postable_type` (string(50)), `postable_id`, `editor_user_id`, `previous_content`, `edited_at` | Pseudo-polymorphic, **no `org_id`, no FK on the postable pair, no `OrgScope`** |
| `forum_reports` | `postable_type`, `postable_id`, `reported_by`, `reason` (≤500), `status` (enum: `pending`\|`reviewed_dismissed`\|`reviewed_removed`, default `pending`), `reviewed_by` (nullable), `reviewed_at` (nullable) | Pseudo-polymorphic, same as `forum_post_edits` |

`forum_topics.org_id` carry real `foreign()` constraint (`cascadeOnDelete`
on `organizations`) even though declared `unsignedBigInteger` instead of
`foreignId` — see migration layout, match `certificates` style: plain FK
column plus explicit `$table->foreign(...)`. `forum_replies` carry **no**
own `org_id`. Tenant always resolve by walking `reply->topic->org_id`.

## Pseudo-Polymorphic `postable_type`/`postable_id` Pattern

`forum_post_edits` and `forum_reports` both point at `ForumTopic` or
`ForumReply` via `postable_type`/`postable_id` — but this **not**
Eloquent `morphTo()`. No database foreign key on pair (see each migration
docblock: "integrity is validated at the application layer only"), and
`postable_type` written as model **full class string**
(`ForumTopic::class`/`ForumReply::class`), not Eloquent default morph
alias. Both `ForumPostEdit::postable()` and `ForumReport::postable()`
resolve same way:

```php
public function postable(): ForumTopic|ForumReply|null
{
    /** @var class-string<ForumTopic|ForumReply> $type */
    $type = $this->postable_type;

    return $type::withTrashed()->find($this->postable_id);
}
```

`withTrashed()` required in both, not optional — post may be already
soft-deleted when history/report row read (direct moderation removal, or
report reviewed after author deleted own post). Any new code touching
either table must resolve `postable` through these methods, never bare
`find()` that miss soft-deleted target or drift from FQCN convention.

`ForumReportController::resolvePostable()` is **only** place translating
short `forum_topic`/`forum_reply` labels used by "Denunciar" button
`data-postable-type` attribute (and request payload) into real model FQCN
before anything downstream. Short label never reach
`ReportForumPostAction`, never get persisted.

## `DeleteForumPostAction`/`EditForumPostAction`: Single Write Boundary

Both actions shared between `ForumTopic` and `ForumReply` (typed
`ForumTopic|ForumReply $post`) and are **only** place writing
`forum_post_edits`:

- `EditForumPostAction` write `forum_post_edits` row with **pre-edit**
  `content`, then update post `content`/`edited_at`. No edit-window,
  no deadline ("a qualquer momento"). Resubmission with identical
  content still write history row (no no-op special case).
- `DeleteForumPostAction` write `forum_post_edits` row with post last
  content before soft-deleting it — "apagar" always logical removal
  (`SoftDeletes`), never hard delete, so pre-removal content stay visible
  in public edit-history modal and to Gestor reviewing report. Deleting
  `ForumTopic` do **not** cascade soft-delete onto its `ForumReply` rows.
  Once topic 404s (soft-deleted rows excluded from default query), its
  replies become unreachable through UI.

`ForumModerationController::remove()` reuse `DeleteForumPostAction`
directly — "Remover post" from report queue is exact same code path as
Gestor direct-delete button, plus flipping `forum_reports.status` to
`reviewed_removed`.

## Two Independent Moderation Paths

1. **Report queue**: any enrolled Aluno or same-org Gestor create
   `pending` `forum_reports` row via `ReportForumPostAction`. Gestor/
   Admin review `GET /forum/moderation` (own-org rows only, filtered by
   resolving each report `postable()` and reusing
   `ForumTopicPolicy`/`ForumReplyPolicy::view()` instead of `org_id`
   column `forum_reports` do not have) and either dismiss
   (`reviewed_dismissed`, post stay visible) or remove
   (`reviewed_removed` + `DeleteForumPostAction`).
2. **Direct moderation**: Gestor/Admin pin (`ForumTopic` only —
   `ForumReply` have no `is_pinned` column, no `pin` ability on
   `ForumReplyPolicy`), edit, or delete any post via same
   `update`/`delete` Policy abilities author use. Independent of whether
   report exist at all.

Notifications exclude "new report" from the trigger list on
purpose — new `forum_reports` row silent outside moderation queue itself.
Do not wire notification for it without separate, deliberate change.

## Incremental `since_id` Polling Replaces Websockets

No broadcasting driver, no Echo, no jQuery — the forum screens follow the
Material Bootstrap redesign. `forum/show.blade.php` render
one `[data-forum-polling]` container carrying `data-fetch-url`
(`forum-replies.fetch`) and `data-last-id` (`$lastReplyId`, the highest
reply id rendered server-side). `ForumPolling.js` run one `setInterval`
per container, 10s, `GET {fetch-url}?since_id={lastId}`.

Layering:

- **Server owns presentation data.** `fetchNew()` publish `initials` and
  `role_label` per row plus a top-level `last_id`, all read from
  `User::initials()`/`User::roleLabel()` accessors — the same accessors
  `x-ui.avatar`'s `name` prop and the Blade role badge use. The JSON is
  therefore a full-parity contract, not a thin id/content feed, and the
  injected card cannot drift from `_reply.blade.php`.
- **Client owns DOM assembly only.** `appendReply()` rebuild the partial's
  markup with `createElement`/`textContent`. Alternative considered and
  rejected for now: returning server-rendered HTML per reply (true
  cloning, zero drift) — it would change the documented JSON contract and
  the `fetchNew` tests. If drift ever bites twice, revisit that.
- **Dedupe is DOM-based.** `container.querySelector('[data-reply-id=N]')`
  guard, so a reply already rendered by Blade or by an earlier cycle is
  never doubled.
- **Back-pressure.** `limit(50)` per call. A tab idle for hours drain 50
  replies per 10s cycle; `data-last-id` still advance correctly BECAUSE
  the query order by `id`. Removing `orderBy('id')` break that invariant
  silently.
- **Rate limit.** `forum-replies.fetch` alone carry `throttle:60,1`; one
  tab spend 6/min, so several tabs on one topic can trip 429. The loop
  skip the cycle and back off, never clear its interval — ONLY a terminal
  4xx (401/419/403/404: expired session, revoked policy, topic removed by
  moderation) stop it, because that endpoint will never answer that page
  again. The back-off de-escalates on the next success.
- **No removal path.** Polling only APPEND. A reply soft-deleted
  server-side stay visible in an open tab until reload, and a
  polling-injected reply carry no "Apagar" (per-viewer permission is not
  in the payload). Both are accepted limits, not bugs.

## Forum Screens: Desktop Header Action vs Mobile FAB

`forum/index.blade.php` expose ONE creation flow through TWO mutually
exclusive entry points around the `lg` breakpoint: the
`x-layout.page-header` action button (`d-none d-lg-inline-flex`) and
`<x-ui.fab>` (`d-lg-none`). Both open the same `#new-topic-modal`, whose
body form is bound to a footer submit button by the HTML5
`form="new-topic-form"` attribute. `forum/create.blade.php` remain the
full-page fallback on the identical `forum.store` contract. Selector-level
detail and the pin-form/stretched-link sibling rule live in
`forum-conventions`.

## `EnsureStudentIsEnrolled` (`student.enrolled`) Gates Whole Forum

Every Topic/Reply route (`routes/web.php` `courses/{course}/forum/*`
group) sit behind `['auth', 'student.enrolled']`, not only role
middleware — access require *active or completed enrollment*, which
`ForumTopicPolicy`/`ForumReplyPolicy::hasCourseAccess()` re-check via
`$user->hasActiveOrCompletedEnrollment($course)` at Policy layer
(defense in depth: middleware gate route, Policy gate individual model
action). Gestor/Admin-only pin/moderation routes use `role:admin|gestor`
instead, since Gestor/Admin never "enrolled".

## Related

- `tenancy-architecture` — general org-scoped vs cascade-inherited rule
  this module `forum_topics`/`forum_replies` split follow, and where
  pseudo-polymorphic `forum_post_edits`/`forum_reports` tables get
  cross-referenced from platform-wide tenancy table.
- `certificates-architecture` — other module with pseudo-polymorphic
  `target_id` pointer, deliberately no FK, similar rationale.
