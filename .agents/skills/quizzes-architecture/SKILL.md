---
name: quizzes-architecture
description: >
  Quizzes/Evaluations domain:
  quizzes/quiz_questions/quiz_options/quiz_attempts/quiz_answers schema,
  cascade-inherited tenancy through Lesson, correction engine branching
  between automatic (single_choice/multiple_choice/true_false) and manual
  (essay) grading, max_attempts/time_limit_minutes enforcement,
  `OpenQuizAttemptAction` as sole owner of the `in_progress` attempt and
  the `open_slot` unique-index invariant. Use when
  designing or reviewing feature touching
  Quiz/QuizQuestion/QuizOption/QuizAttempt/QuizAnswer data, before adding
  new question type, or when deciding how quiz-taking or essay-grading
  screen gets scoped and gated.
license: MIT
metadata:
  feature: quizzes
  role: architecture
---

# Quizzes Architecture

## Overview

Gestor (`role:gestor`) get CRUD over single Quiz attached to one of
their Lessons (`type = quiz`) plus its Questions/Options. Aluno
(`role:aluno`) can submit attempt against that Quiz. For `essay` questions,
Gestor must grade attempt by hand before it counts. This feature
never mutates `courses`/`modules`/`lessons` (the courses domain owns those)
beyond
reading `lessons.type` and writing `lesson_progress` on passed attempt
(reuses `MarkLessonCompleteAction` — never re-implemented
here).

## Schema

| Table | Key columns | Tenancy |
| --- | --- | --- |
| `quizzes` | `lesson_id` (**UNIQUE** — 1:1), `title`, `instructions`, `allow_retries`, `max_attempts` (nullable), `time_limit_minutes` (nullable), `show_correct_answers`, `min_score_percentage` | **Cascade-inherited** via `lessons.module_id` → `modules.course_id` → `courses.org_id` — no own `org_id`, no `OrgScope` |
| `quiz_questions` | `quiz_id`, `question_text`, `type` (enum: `single_choice`\|`multiple_choice`\|`true_false`\|`essay`), `order_index` | Cascade-inherited one level deeper |
| `quiz_options` | `question_id`, `option_text`, `is_correct` — **does not apply** to `type=essay` questions | Cascade-inherited two levels deeper |
| `quiz_attempts` | `quiz_id`, `user_id`, `score_percentage` (nullable), `is_passed` (nullable), `status` (`in_progress`\|`awaiting_manual_grading`\|`graded`), `open_slot` (nullable tinyint, `1` while open / `NULL` once closed, **never mass-assignable**), `started_at`, `completed_at` | Cascade-inherited via `quiz_id` |
| `quiz_answers` | `attempt_id`, `question_id`, `selected_option_ids` (JSON array, nullable), `essay_answer` (nullable), `is_correct` (nullable — `null` means "not yet graded"), `graded_by` (nullable), `graded_at` (nullable) | Cascade-inherited via `attempt_id` |

None of these five models get `OrgScope` trait — see each model docblock
and `tenancy-architecture`. Tenant boundary implied purely by FK chain up
to `courses.org_id`. `quiz_attempts`/`quiz_answers` also carry no
`SoftDeletes` — attempt, once submitted, is permanent historical record
("cada attempt é preservado individualmente no
histórico").

## Question Types and Correction

| `type` | Correction | Rule |
| --- | --- | --- |
| `single_choice` | Automatic | Exactly 1 `quiz_options.is_correct=true`; correct iff `selected_option_ids` is a 1-element set matching it. |
| `multiple_choice` | Automatic | N ≥ 1 correct options; correct iff `selected_option_ids` is **exactly** the set of correct option ids — no partial credit, a subset or superset is wrong. |
| `true_false` | Automatic | A `single_choice` special case with exactly 2 fixed options (Verdadeiro/Falso). |
| `essay` | **Manual** | No `quiz_options` rows. `essay_answer` holds free text, `is_correct = null` until a Gestor grades it via `GradeEssayAnswerAction`. |

Empty `selected_option_ids` always wrong. Never treat "no answer" as
vacuous match against empty correct-set (impossible here since every
non-essay question has ≥1 correct option, but never special-case empty
answer as "skip"/"correct").

## Attempt Limits and Time

- `max_attempts` (nullable): when set, blocks new submission once student
  reached N **completed** attempts — count only
  `status IN (awaiting_manual_grading, graded)`, never stale
  `in_progress` attempt (see edge case in `quizzes-conventions`). `null`
  means unlimited, independent of `allow_retries`.
- `allow_retries` (bool): when `false`, blocks any second submission
  outright regardless of `max_attempts`.
- `time_limit_minutes` (nullable): timer starts at
  `quiz_attempts.started_at`, stamped server-side by
  `OpenQuizAttemptAction::openOrResume()` when the student opens the quiz
  page (`StudentQuizController::show()`) and resumed by the same call
  inside `SubmitQuizAttemptAction::execute()`; never accepted from the
  client. Over-limit submission is **accepted, not
  blocked** — network races must never cost student their answers — but
  forced `is_passed = false`. No `notes`/warning column on
  `quiz_attempts` in current schema.
  "Tempo excedido" state must be **computed on read** from
  `started_at`/`completed_at`/`time_limit_minutes`, never persisted as
  text, unless follow-up migration explicitly approved.
- Best score across attempts: any UI/certificate logic showing student
  standing on Quiz uses `MAX(score_percentage)` across their
  `status = graded` attempts, not latest attempt. Every attempt still
  individually queryable in history.

## The Open-Attempt Lifecycle (`OpenQuizAttemptAction`)

`OpenQuizAttemptAction` is the **single owner** of the `in_progress`
`QuizAttempt` row. Both the quiz page and the submission go through it, so
there is exactly one definition of "an attempt is open" and one place the
row is created, resumed or expired. Never create an `in_progress`
`QuizAttempt` anywhere else (factories/tests aside).

- `openOrResume(Quiz, User): QuizAttempt` — returns the student's open
  attempt untouched when one exists (so reloading the page can never reset
  the countdown), otherwise creates it with `started_at = now()`. Runs in
  a transaction with `lockForUpdate()`.
- `expireStaleAttempt(Quiz, User): ?QuizAttempt` — for timed quizzes only.
  Closes an abandoned open attempt whose limit already ran out: `status =
  graded`, `score_percentage = 0`, `is_passed = false`, `completed_at`
  pinned to the deadline (`started_at + time_limit_minutes`). The zombie
  row becomes a visible, counted attempt, so **letting the clock run out
  costs an attempt** and is never a free way to earn a fresh countdown.
  `StudentQuizController::show()` calls it *before* counting attempts.

**Concurrency invariant — at most one open attempt per (quiz, user).**
The row lock alone cannot carry it: on the very first open there is no row
to lock, so two concurrent requests could both insert. The real guarantee
is the partial-style unique index `quiz_attempts_open_slot_unique`
(`quiz_id, user_id, open_slot`); `open_slot` holds `1` while open and
`NULL` once closed, and both MySQL and SQLite treat `NULL`s as distinct,
so completed attempts never collide. `QuizAttempt::booted()` keeps
`open_slot` in sync from `status` on every save — application code must
never set it directly. A racer that loses the insert catches
`UniqueConstraintViolationException` and simply resumes the winner's row.

An `in_progress` attempt carries no answers and **never** counts toward
`max_attempts` — only `awaiting_manual_grading` and `graded` do.

## The Correction Engine (`SubmitQuizAttemptAction`)

1. Verify student has active enrollment in Quiz's Course's Org (same
   `hasActiveOrCompletedEnrollment()` check `EnsureStudentIsEnrolled`
   uses — see `learning-architecture`).
2. Enforce `allow_retries`/`max_attempts` (above) — block with domain
   exception before touching `quiz_attempts` at all. Only then call
   `OpenQuizAttemptAction::openOrResume()` to resume the page's open
   attempt (or create one, for an untimed quiz submitted without ever
   opening the page).
3. Auto-grade every `single_choice`/`multiple_choice`/`true_false`
   question, persist `quiz_answers` for all of them, essay included
   (essay rows get `is_correct = null`).
4. If quiz has **any** `essay` question: `status =
   awaiting_manual_grading`, `score_percentage = null`, `is_passed = null`
   — `lesson_progress` **not** written yet, even if every auto-graded
   question answered correctly.
5. If no pending essay question (none exist, or `GradeEssayAnswerAction`
   just finished grading last one via `finalizeGrading()`):
   `status = graded`, recompute `score_percentage`, and if `is_passed`
   (score ≥ `min_score_percentage`), write `lesson_progress` with
   `completion_source = quiz_passed`, dispatching
   `LessonMarkedAsCompleted` (existing event, reused
   unchanged; see `learning-architecture`).

## Manual Grading (`GradeEssayAnswerAction`)

Gestor grading screen lists `quiz_attempts.status =
awaiting_manual_grading` scoped to their own Org (via
`QuizAttemptPolicy`, mirroring `LessonPolicy` cascade-authorize pattern).
Grading one `quiz_answers` row sets `is_correct`/`graded_by`/
`graded_at`. Once **every** essay answer on attempt has non-null
`is_correct`, `finalizeGrading()` recomputes whole attempt
`score_percentage` **using exact same formula as auto-grading** (correct
answers ÷ total questions × 100, any unanswered question still counted in
denominator as wrong) and re-enters step 5 above.

## Related

- `courses-architecture` — Lesson this feature hangs off, plus
  cascade-inherited-tenancy pattern this module copies one level deeper.
- `learning-architecture` — `MarkLessonCompleteAction`,
  `LessonMarkedAsCompleted`, `EnsureStudentIsEnrolled`, all reused
  unmodified by student-facing side of this feature.
- `tenancy-architecture` — `OrgScope`, `RolesEnum`.
