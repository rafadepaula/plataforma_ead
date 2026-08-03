---
name: forum-architecture
description: >
  Explains the Course Discussion Forum domain (SPEC-10): the
  forum_topics/forum_replies/forum_post_edits/forum_reports schema, the
  OrgScope-on-ForumTopic vs. cascade-inherited ForumReply tenancy, the
  pseudo-polymorphic postable_type/postable_id pattern shared by
  forum_post_edits and forum_reports, the no-deadline public edit-history
  contract, and the two moderation paths (report queue vs. direct
  Gestor/Admin removal). Use whenever designing or reviewing a feature
  that touches ForumTopic/ForumReply/ForumPostEdit/ForumReport data,
  before adding a new postable type, or when deciding how a forum route
  should be tenant/enrollment-gated.
license: MIT
metadata:
  feature: forum
  role: architecture
  specs:
    - spec/specs/10-course-discussion-forum.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Forum Architecture

## Overview

RF22 gives every Course a discussion forum: Alunos with an active/completed
enrollment (RN10) and same-org Gestores/Admins create `forum_topics` and
`forum_replies`, with jQuery-free `since_id` AJAX polling standing in for
websockets (§2). RF27 requires **any** viewer with access to the topic —
not just the author — to be able to open a public "ver histórico" edit
history for a post (§2.1). RF26 adds a "Denunciar" report queue reviewed
by the Gestor (§2.2), which is explicitly a *second* moderation channel,
not the only one — Gestor/Admin can also pin/edit/delete any post
directly, report or no report.

## Schema (SPEC-00 §2.1.16–2.1.19)

| Table | Key columns | Tenancy |
| --- | --- | --- |
| `forum_topics` | `org_id`, `course_id`, `user_id`, `title` (≤200), `content`, `is_pinned`, `edited_at` (nullable), `deleted_at` (SoftDeletes) | **Directly org-scoped** — own `org_id` column, `OrgScope` trait applied |
| `forum_replies` | `topic_id`, `user_id`, `content`, `edited_at` (nullable), `deleted_at` (SoftDeletes) | **Cascade-inherited** via `topic_id` → `forum_topics.org_id` — no own `org_id`, no `OrgScope` |
| `forum_post_edits` | `postable_type` (string(50)), `postable_id`, `editor_user_id`, `previous_content`, `edited_at` | Pseudo-polymorphic, **no `org_id`, no FK on the postable pair, no `OrgScope`** |
| `forum_reports` | `postable_type`, `postable_id`, `reported_by`, `reason` (≤500), `status` (enum: `pending`\|`reviewed_dismissed`\|`reviewed_removed`, default `pending`), `reviewed_by` (nullable), `reviewed_at` (nullable) | Pseudo-polymorphic, same as `forum_post_edits` |

`forum_topics.org_id` has a real `foreign()` constraint (`cascadeOnDelete`
on `organizations`) even though it's declared `unsignedBigInteger` rather
than `foreignId` — see the migration's own layout, matching
`certificates`' style of a plain FK column plus an explicit
`$table->foreign(...)`. `forum_replies` has **no** own `org_id` at all;
its tenant is always resolved by walking `reply->topic->org_id`.

## The Pseudo-Polymorphic `postable_type`/`postable_id` Pattern

`forum_post_edits` and `forum_reports` both point at either a `ForumTopic`
or a `ForumReply` via `postable_type`/`postable_id` — but this is **not**
Eloquent's `morphTo()`. There is no database foreign key on the pair (see
each migration's own docblock: "integrity is validated at the application
layer only"), and `postable_type` is written as the model's **full class
string** (`ForumTopic::class`/`ForumReply::class`), not Eloquent's default
morph alias. Both `ForumPostEdit::postable()` and `ForumReport::postable()`
resolve it the same way:

```php
public function postable(): ForumTopic|ForumReply|null
{
    /** @var class-string<ForumTopic|ForumReply> $type */
    $type = $this->postable_type;

    return $type::withTrashed()->find($this->postable_id);
}
```

`withTrashed()` is required in both, not optional — a post may already be
soft-deleted by the time its history/report row is read (direct
moderation removal, or a report reviewed after the author deleted their
own post). Any new code touching either table must resolve `postable`
through these methods, never via a bare `find()` that would miss a
soft-deleted target or drift from the FQCN convention.

`ForumReportController::resolvePostable()` is the **only** place that
translates the short `forum_topic`/`forum_reply` labels used by the
"Denunciar" button's `data-postable-type` attribute (and the request
payload) into the real model FQCN before anything downstream — the short
label never reaches `ReportForumPostAction` or gets persisted.

## `DeleteForumPostAction`/`EditForumPostAction`: the Single Write Boundary

Both actions are shared between `ForumTopic` and `ForumReply` (typed
`ForumTopic|ForumReply $post`) and are the **only** place that writes
`forum_post_edits`:

- `EditForumPostAction` writes a `forum_post_edits` row with the **pre-edit**
  `content`, then updates the post's `content`/`edited_at`. There is no
  edit-window/deadline (§2.1 — "a qualquer momento"), and a resubmission
  with identical content still writes a history row (no no-op special
  case).
- `DeleteForumPostAction` writes a `forum_post_edits` row with the post's
  last content before soft-deleting it — "apagar" is always a logical
  removal (`SoftDeletes`), never a hard delete, so the pre-removal content
  stays visible in the public edit-history modal and to a Gestor reviewing
  a report. Deleting a `ForumTopic` does **not** cascade a soft-delete
  onto its `ForumReply` rows — once the topic 404s (soft-deleted rows are
  excluded from the default query), its replies simply become
  unreachable through the UI.

`ForumModerationController::remove()` reuses `DeleteForumPostAction`
directly — "Remover post" from the report queue is the exact same code
path as a Gestor's direct-delete button, just additionally flipping the
`forum_reports.status` to `reviewed_removed`.

## Two Independent Moderation Paths

1. **Report queue (RF26)**: any enrolled Aluno or same-org Gestor creates a
   `pending` `forum_reports` row via `ReportForumPostAction`. A Gestor/
   Admin reviews `GET /forum/moderation` (own-org rows only, filtered by
   resolving each report's `postable()` and re-using
   `ForumTopicPolicy`/`ForumReplyPolicy::view()` rather than an `org_id`
   column `forum_reports` doesn't have) and either dismisses
   (`reviewed_dismissed`, post stays visible) or removes
   (`reviewed_removed` + `DeleteForumPostAction`).
2. **Direct moderation**: Gestor/Admin can pin (`ForumTopic` only —
   `ForumReply` has no `is_pinned` column, no `pin` ability on
   `ForumReplyPolicy`), edit, or delete any post via the same
   `update`/`delete` Policy abilities an author uses — independent of
   whether a report exists at all.

SPEC-13 (Notifications) intentionally excludes "new report" from its
trigger list (§2.2) — a new `forum_reports` row is silent outside the
moderation queue itself. Do not wire a notification for it without a
separate, deliberate spec change.

## `EnsureStudentIsEnrolled` (`student.enrolled`) Gates the Whole Forum

Every Topic/Reply route (`routes/web.php`'s `courses/{course}/forum/*`
group) sits behind `['auth', 'student.enrolled']`, not just a role
middleware — RN10 requires an *active or completed enrollment*, which
`ForumTopicPolicy`/`ForumReplyPolicy::hasCourseAccess()` also re-check via
`$user->hasActiveOrCompletedEnrollment($course)` at the Policy layer
(defense in depth: the middleware gates the route, the Policy gates the
individual model action). The Gestor/Admin-only pin/moderation routes use
`role:admin|gestor` instead, since a Gestor/Admin is never "enrolled".

## Related Specs

- `spec/specs/10-course-discussion-forum.md` — RF22, RF26, RF27, RN08,
  RN10.
- `tenancy-architecture` — the general org-scoped vs. cascade-inherited
  rule this module's `forum_topics`/`forum_replies` split follows, and
  where the pseudo-polymorphic `forum_post_edits`/`forum_reports` tables
  are cross-referenced from the platform-wide tenancy table.
- `certificates-architecture` — the other module with a pseudo-polymorphic
  `target_id` pointer with intentionally no FK, for a similar rationale.
