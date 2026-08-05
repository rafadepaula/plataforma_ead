---
name: quizzes-architecture
description: >
  Explains the Quizzes/Evaluations domain (SPEC-08): the
  quizzes/quiz_questions/quiz_options/quiz_attempts/quiz_answers schema,
  cascade-inherited tenancy through the Lesson, the correction engine's
  branching between automatic (single_choice/multiple_choice/true_false)
  and manual (essay) grading, and the max_attempts/time_limit_minutes
  enforcement rules. Use whenever designing or reviewing a feature that
  touches Quiz/QuizQuestion/QuizOption/QuizAttempt/QuizAnswer data, before
  adding a new question type, or when deciding how a quiz-taking or
  essay-grading screen should be scoped and gated.
license: MIT
metadata:
  feature: quizzes
  role: architecture
  specs:
    - spec/specs/08-quizzes-and-evaluations-engine.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Quizzes Architecture

## Overview

RF08 gives a Gestor (`role:gestor`) CRUD over the single Quiz attached to
one of their Lessons (`type = quiz`) and its Questions/Options. RF09 gives
an Aluno (`role:aluno`) the ability to submit an attempt against that Quiz
and, for `essay` questions, RN11 requires a Gestor to manually grade the
attempt before it counts. This feature never mutates `courses`/`modules`/
`lessons` themselves (SPEC-05 owns those) beyond reading `lessons.type` and
writing `lesson_progress` on a passed attempt (SPEC-07's
`MarkLessonCompleteAction`, reused — never re-implemented here).

## Schema (SPEC-00 §2.1.7–2.1.11)

| Table | Key columns | Tenancy |
| --- | --- | --- |
| `quizzes` | `lesson_id` (**UNIQUE** — 1:1), `title`, `instructions`, `allow_retries`, `max_attempts` (nullable), `time_limit_minutes` (nullable), `show_correct_answers`, `min_score_percentage` | **Cascade-inherited** via `lessons.module_id` → `modules.course_id` → `courses.org_id` — no own `org_id`, no `OrgScope` |
| `quiz_questions` | `quiz_id`, `question_text`, `type` (enum: `single_choice`\|`multiple_choice`\|`true_false`\|`essay`), `order_index` | Cascade-inherited one level deeper |
| `quiz_options` | `question_id`, `option_text`, `is_correct` — **does not apply** to `type=essay` questions | Cascade-inherited two levels deeper |
| `quiz_attempts` | `quiz_id`, `user_id`, `score_percentage` (nullable), `is_passed` (nullable), `status` (`in_progress`\|`awaiting_manual_grading`\|`graded`), `started_at`, `completed_at` | Cascade-inherited via `quiz_id` |
| `quiz_answers` | `attempt_id`, `question_id`, `selected_option_ids` (JSON array, nullable), `essay_answer` (nullable), `is_correct` (nullable — `null` means "not yet graded"), `graded_by` (nullable), `graded_at` (nullable) | Cascade-inherited via `attempt_id` |

None of these five models get the `OrgScope` trait — see each model's own
docblock and `tenancy-architecture`. The tenant boundary is implied purely
by the FK chain up to `courses.org_id`. `quiz_attempts`/`quiz_answers`
additionally carry no `SoftDeletes` — an attempt, once submitted, is a
permanent historical record (SPEC-08 §1.3's "cada attempt é preservado
individualmente no histórico").

## Question Types and Correction (SPEC-08 §1.2)

| `type` | Correction | Rule |
| --- | --- | --- |
| `single_choice` | Automatic | Exactly 1 `quiz_options.is_correct=true`; correct iff `selected_option_ids` is a 1-element set matching it. |
| `multiple_choice` | Automatic | N ≥ 1 correct options; correct iff `selected_option_ids` is **exactly** the set of correct option ids — no partial credit, a subset or superset is wrong. |
| `true_false` | Automatic | A `single_choice` special case with exactly 2 fixed options (Verdadeiro/Falso). |
| `essay` | **Manual** | No `quiz_options` rows. `essay_answer` holds free text, `is_correct = null` until a Gestor grades it via `GradeEssayAnswerAction`. |

An empty `selected_option_ids` is always incorrect — never treat "no
answer" as a vacuous match against an empty correct-set (impossible here
since every non-essay question has ≥1 correct option, but never special
-case an empty answer as "skip"/"correct").

## Attempt Limits and Time (SPEC-08 §1.3)

- `max_attempts` (nullable): when set, blocks a new submission once the
  student has already reached N **completed** attempts — counting only
  `status IN (awaiting_manual_grading, graded)`, never a stale
  `in_progress` attempt (see edge case in `quizzes-conventions`). `null`
  is unlimited, independent of `allow_retries`.
- `allow_retries` (bool): when `false`, blocks any second submission
  outright regardless of `max_attempts`.
- `time_limit_minutes` (nullable): the timer starts at
  `quiz_attempts.started_at`. An over-limit submission is **accepted, not
  blocked** — network races must never cost a student their answers — but
  is forced `is_passed = false`. There is no `notes`/warning column on
  `quiz_attempts` in the current schema (SPEC-00 §2.1 doesn't define one);
  the "Tempo excedido" state must be **computed on read** from
  `started_at`/`completed_at`/`time_limit_minutes`, not persisted as text,
  unless a follow-up migration is explicitly approved.
- Best score across attempts: any UI/certificate logic showing the
  student's standing on a Quiz uses `MAX(score_percentage)` across their
  `status = graded` attempts, not the latest attempt — every attempt still
  remains individually queryable in history.

## The Correction Engine (`SubmitQuizAttemptAction`, SPEC-08 §2)

1. Verify the student has an active enrollment in the Quiz's Course's Org
   (same `hasActiveOrCompletedEnrollment()` check `EnsureStudentIsEnrolled`
   uses — see `learning-architecture`).
2. Enforce `allow_retries`/`max_attempts` (above) — block with a
   domain exception before touching `quiz_attempts` at all.
3. Auto-grade every `single_choice`/`multiple_choice`/`true_false`
   question and persist `quiz_answers` for all of them, essay included
   (essay rows get `is_correct = null`).
4. If the quiz has **any** `essay` question: `status =
   awaiting_manual_grading`, `score_percentage = null`, `is_passed = null`
   — `lesson_progress` is **not** written yet, even if every
   auto-graded question was answered correctly.
5. If there is no pending essay question (either none exist, or
   `GradeEssayAnswerAction` just finished grading the last one via
   `finalizeGrading()`): `status = graded`, recompute
   `score_percentage`, and if `is_passed` (score ≥
   `min_score_percentage`), write `lesson_progress` with
   `completion_source = quiz_passed` — dispatching
   `LessonMarkedAsCompleted` (SPEC-07's existing event, reused unchanged;
   see `learning-architecture`).

## Manual Grading (`GradeEssayAnswerAction`, SPEC-08 §2.1)

A Gestor's grading screen lists `quiz_attempts.status =
awaiting_manual_grading` scoped to their own Org (via
`QuizAttemptPolicy`, mirroring `LessonPolicy`'s cascade-authorize
pattern). Grading one `quiz_answers` row sets `is_correct`/`graded_by`/
`graded_at`; once **every** essay answer on the attempt has a non-null
`is_correct`, `finalizeGrading()` recomputes the whole attempt's
`score_percentage` **using the exact same formula as auto-grading**
(correct answers ÷ total questions × 100, with any unanswered question
still counted in the denominator as wrong) and re-enters step 5 above.

## Related Specs

- `spec/specs/08-quizzes-and-evaluations-engine.md` — RF08, RF09, RN02,
  RN03, RN04, RN11, this feature's full requirements.
- `spec/specs/00-architecture-database-and-guardrails.md` §2.1.7–2.1.11 —
  full column/index/`onDelete` definitions.
- `courses-architecture` — the Lesson this feature hangs off of, and the
  cascade-inherited-tenancy pattern this module copies one level deeper.
- `learning-architecture` — `MarkLessonCompleteAction`,
  `LessonMarkedAsCompleted`, `EnsureStudentIsEnrolled`, all reused
  unmodified by this feature's student-facing side.
- `tenancy-architecture` — `OrgScope`, `RolesEnum`.
