---
name: forum-maintenance
description: >
  Course Discussion Forum: `forum_topics`/`forum_replies`/`forum_post_edits`/
  `forum_reports` schema, OrgScope-on-ForumTopic vs cascade-inherited ForumReply,
  pseudo-polymorphic postable pattern, `ForumTopic::withoutEvents()` org_id
  workaround, ForumContentSanitizerService defense, JS module contracts, moderation
  paths. Use when designing or reviewing features touching
  ForumTopic/ForumReply/ForumPostEdit/ForumReport data, before adding a new postable
  type, when writing controller/Policy/Form Request/Blade/JS managing forum records,
  or when `ForumTopicTest`, `XssSanitizationTest`, `ForumModerationQueueTest` or
  `ForumEditHistoryTest` fails, a report postable cannot resolve, or "ver histórico"
  modal/report/polling goes dead in the browser.
license: MIT
metadata:
  feature: forum
  roles: [architecture, conventions, maintenance]
---

# Course Discussion Forum (`forum-maintenance`)

Course Discussion Forum: `forum_topics`/`forum_replies`/`forum_post_edits`/`forum_reports` schema, OrgScope-on-ForumTopic vs cascade-inherited ForumReply, pseudo-polymorphic `postable_type`/`postable_id`, public edit-history contract, two moderation paths.

The detailed knowledge for this module lives in the reference files below
— read only the one the task needs:

| Reference | Read when |
| --- | --- |
| `resource/architecture.md` | Designing or reviewing features touching ForumTopic/ForumReply/ForumPostEdit/ForumReport data, before adding a new postable type, or when deciding how a forum route gets tenant/enrollment-gated. |
| `resource/conventions.md` | Writing controller, Policy, Form Request, Blade view, or JS module managing ForumTopic/ForumReply/ForumPostEdit/ForumReport records. |
| `resource/maintenance.md` | `ForumTopicTest`, `XssSanitizationTest`, `ForumModerationQueueTest`, or `ForumEditHistoryTest` fails; report postable cannot resolve; multi-org Aluno gets `UnresolvedOrgContextException` creating a topic; "ver histórico" modal/report button/polling dead in browser. |
