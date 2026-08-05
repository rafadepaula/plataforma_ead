---
name: quizzes-maintenance
description: >
  Debugging, testing, and edge-case guide for the Quizzes/Evaluations
  feature (SPEC-08): the mandatory PHPUnit/Dusk test files, common
  attempt-limit/time-limit/essay-grading failure modes, the
  `question-list-page`/`question-form-page` vs. inline-modal view split,
  and the frontend-build gotcha for `QuizBuilder.js`/`QuizTimer.js`. Use
  when `SubmitQuizAttemptTest`, `QuizAttemptLimitsTest`,
  `EssayManualGradingTest`, or `QuizManagementTest` is failing; a quiz
  submission scores unexpectedly; an essay attempt won't leave
  `awaiting_manual_grading`; or the options UI doesn't hide for an essay
  question.
license: MIT
metadata:
  feature: quizzes
  role: maintenance
  specs:
    - spec/specs/08-quizzes-and-evaluations-engine.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Quizzes Maintenance

## Mandatory Test Coverage for This Module

These tests guard the SPEC-08 contract and must stay green (PHPUnit, no
Pest):

- `tests/Feature/SubmitQuizAttemptTest.php` — the correction engine:
  auto-grading of `single_choice`/`multiple_choice`/`true_false` (exact-set
  matching, empty selection is always wrong), essay answers routing the
  attempt to `awaiting_manual_grading`, `lesson_progress` written with
  `completion_source = quiz_passed` only when `is_passed`.
- `tests/Feature/QuizAttemptLimitsTest.php` — `allow_retries`/`max_attempts`
  counting only completed (`awaiting_manual_grading`/`graded`) attempts,
  never a stale `in_progress` one; the accept-but-fail time-limit rule.
- `tests/Feature/EssayManualGradingTest.php` — `GradeEssayAnswerAction`
  grading one answer at a time, `finalizeGrading()` only firing once every
  essay answer on the attempt is graded, and using the exact same score
  formula as auto-grading (unanswered questions count as wrong, still in
  the denominator).
- `tests/Feature/QuizManagementTest.php` (Bucket 2) — Gestor CRUD of
  Quiz/QuizQuestion/QuizOption, the `quizzes.lesson_id` UNIQUE
  redirect-with-error guard, cross-tenant isolation.
- `tests/Browser/StudentQuizAttemptTest.php`,
  `tests/Browser/EssayGradingScreenTest.php` (Bucket 2, Dusk E2E) — full
  browser flow through `student/quizzes/show.blade.php` and
  `quizzes/attempts/show.blade.php`.

Run the narrowest of these first after touching this module:

```bash
vendor/bin/sail artisan test --filter=SubmitQuizAttemptTest
vendor/bin/sail artisan test --filter=QuizAttemptLimitsTest
vendor/bin/sail artisan test --filter=EssayManualGradingTest
vendor/bin/sail artisan test --filter=QuizManagementTest
vendor/bin/sail dusk --filter=StudentQuizAttemptTest
vendor/bin/sail dusk --filter=EssayGradingScreenTest
```

## A Quiz Submission Scores Unexpectedly

- Check `isObjectiveAnswerCorrect()`'s exact-set comparison first — a
  `multiple_choice` question with a subset or superset of the correct
  options is **entirely** wrong, there is no partial credit. Sorting both
  sides (`->sort()->values()->all()`) before comparing is required — an
  unsorted array comparison would falsely reject a correctly-selected set
  submitted in a different order.
- An empty `selected_option_ids` must never be treated as correct — even
  if (hypothetically) a question had no correct options configured,
  `isObjectiveAnswerCorrect()` short-circuits `empty($selectedOptionIds)`
  to `false` before ever reaching the set comparison.
- `GradeEssayAnswerAction::finalizeGrading()`'s score formula
  (`correctCount / totalQuestions * 100`) must match
  `SubmitQuizAttemptAction::finalize()`'s exactly — both divide by the
  **total** question count, including any essay question, never just the
  count of graded/auto-graded ones. A mismatch here is the first thing to
  check if a graded essay attempt's final score looks off.

## An Essay Attempt Never Leaves `awaiting_manual_grading`

- Confirm the Gestor's grading form (`quizzes/attempts/show.blade.php`)
  actually submitted a `grades[{answer_id}]` value for **every** essay
  `QuizAnswer` on the attempt — `finalizeGrading()` only runs once none
  remain with `is_correct === null`. A `required` radio group that's
  missing for one answer (e.g. a Blade loop bug skipping a question) will
  silently leave the attempt stuck with no error surfaced.
- Check `QuizAttemptPolicy` isn't rejecting the Gestor for a cross-org
  attempt — a 403 on `quiz-attempts.show`/`quiz-attempts.grade` is often
  mistaken for "the grading form is broken" when it's actually a
  tenant-boundary check working as intended.

## The Options UI Doesn't Hide for `type=essay`

- Check `window.QuizBuilder` is actually initialized (`app.js`'s
  `DOMContentLoaded` listener calls `window.QuizBuilder.init()`) — if
  `public/build` is stale relative to `resources/js/quiz-builder.js`/
  `app.js`, run `vendor/bin/sail npm run build` (or ask the user to run
  `npm run dev`/`composer run dev`).
- Confirm the `<select data-question-type-select="{formSuffix}">` and the
  options container it's supposed to toggle
  (`[data-options-container="{formSuffix}"]`) share the **same**
  `formSuffix` — `quizzes/edit.blade.php` renders one `_question-form`
  instance per modal (`'create'`, `'edit-{id}'` per question), so a
  mismatched suffix between two included partials is the most common cause
  of "the select changes but nothing else on the page reacts."
- Remember the client-side hide/disable is UX only —
  `StoreQuizQuestionRequest`/`UpdateQuizQuestionRequest` and
  `QuizQuestionController::update()`'s own `type !== 'essay'` branch are
  the real boundary; a bug here is never "fixed" by only editing the JS.

## Question Authoring Is Inline-Modal-Only — No Standalone `quiz-questions` Pages

`quizzes/edit.blade.php` is the **only** screen that authors Questions —
every Question is created/edited via a modal in place (no full page
navigation, no `formSuffix` collision since each modal gets its own
`'create'`/`'edit-{id}'` suffix), using the shared
`quizzes.partials._question-form`/`_question-list` partials.
`QuizQuestionController` only routes `store`/`update`/`destroy`/`reorder`
— there is no `index`/`create`/`edit` method, no `quiz-questions.index`
route, and no standalone `question-list-page`/`question-form-page` view.
`QuizController::store()`'s redirect target after creating a Quiz is
`quizzes.edit`, not a question-authoring index page. An earlier iteration
of this feature had a two-screen design (standalone pages plus inline
modals sharing one `formSuffix = 'page'`); it was dropped mid-implementation
in favor of modals-only — do not resurrect the standalone pages/routes
without updating this skill and `quizzes-conventions` again.

## `QuizTimer.js` Is Cosmetic Only — Never the Source of Truth

`[data-quiz-timer]`'s `data-started-at` is seeded from the Blade view's own
`now()` at render time (there is no persisted `QuizAttempt.started_at` yet
when the student is looking at the form — see `quizzes-conventions`). If a
Dusk test needs to assert time-limit *enforcement* (not just the visual
countdown), it must submit the form and inspect the resulting
`QuizAttempt`/redirect message — never assert against `QuizTimer`'s
on-screen text reaching zero, since that only proves the cosmetic countdown
ticked, not that the server's accept-but-fail rule fired.

## Auto-Update Protocol (SPEC-03)

Per `spec/specs/03-agentic-harness-and-self-updating-skills.md`: any change
to `QuizController`/`QuizQuestionController`/`StudentQuizController`/
`EssayGradingController`, `SubmitQuizAttemptAction`/
`GradeEssayAnswerAction`, `QuizPolicy`/`QuizAttemptPolicy`, the
`quizzes*`/`quiz-questions*`/`quiz-attempts*`/`student.quizzes.*` routes,
the Blade views under `resources/views/quizzes/`+
`resources/views/student/quizzes/`, or `quiz-builder.js`/`quiz-timer.js`
**must** update all three quizzes skills (`quizzes-architecture`,
`quizzes-conventions`, `quizzes-maintenance`) in the same change, before
the task is considered done. Also re-check:

- `.agents/agents/code-reviewer.md` — if the change affects what a
  reviewer must check for this module.
- Run `vendor/bin/sail artisan harness:check-skills` — it fails the build
  if any of the three `quizzes-*` skills is missing.

## Related Specs

- `spec/specs/08-quizzes-and-evaluations-engine.md` — RF08, RF09, RN02,
  RN03, RN04, RN11.
- `courses-maintenance` — the analogous reorder/Policy cascade-authorize
  pattern this module copies one level deeper.
- `learning-maintenance` — `MarkLessonCompleteAction`,
  `EnsureStudentIsEnrolled`, both reused unmodified by this feature's
  student-facing side.
