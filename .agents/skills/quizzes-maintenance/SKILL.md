---
name: quizzes-maintenance
description: >
  Debug, test, edge-case guide for Quizzes/Evaluations feature (SPEC-08):
  mandatory PHPUnit/Dusk test files, common
  attempt-limit/time-limit/essay-grading failure modes,
  `question-list-page`/`question-form-page` vs inline-modal view split,
  frontend-build gotcha for `QuizBuilder.js`/`QuizTimer.js`. Use when
  `SubmitQuizAttemptTest`, `QuizAttemptLimitsTest`,
  `EssayManualGradingTest`, or `QuizManagementTest` fails; quiz submission
  scores unexpectedly; essay attempt won't leave
  `awaiting_manual_grading`; or options UI doesn't hide for essay
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

These tests guard SPEC-08 contract, must stay green (PHPUnit, no Pest):

- `tests/Feature/SubmitQuizAttemptTest.php` — correction engine:
  auto-grading of `single_choice`/`multiple_choice`/`true_false`
  (exact-set matching, empty selection always wrong), essay answers
  routing attempt to `awaiting_manual_grading`, `lesson_progress` written
  with `completion_source = quiz_passed` only when `is_passed`.
- `tests/Feature/QuizAttemptLimitsTest.php` —
  `allow_retries`/`max_attempts` counting only completed
  (`awaiting_manual_grading`/`graded`) attempts, never stale
  `in_progress` one; accept-but-fail time-limit rule.
- `tests/Feature/EssayManualGradingTest.php` — `GradeEssayAnswerAction`
  grading one answer at a time, `finalizeGrading()` firing only once
  every essay answer on attempt graded, using exact same score formula as
  auto-grading (unanswered questions count wrong, still in denominator).
- `tests/Feature/QuizManagementTest.php` (Bucket 2) — Gestor CRUD of
  Quiz/QuizQuestion/QuizOption, `quizzes.lesson_id` UNIQUE
  redirect-with-error guard, cross-tenant isolation.
- `tests/Browser/StudentQuizAttemptTest.php`,
  `tests/Browser/EssayGradingScreenTest.php` (Bucket 2, Dusk E2E) — full
  browser flow through `student/quizzes/show.blade.php` and
  `quizzes/attempts/show.blade.php`.
- `tests/Feature/MultiTenantQuizManagementTest.php` (SPEC-24) —
  additive cross-org isolation on Quiz/QuizQuestion (view/update/
  delete/reorder 403 on another org's records via direct route hits),
  options-payload isolation (smuggled foreign option id untouched), the
  4 question-type CRUD happy paths, and reorder dense-reassignment from
  non-contiguous `order_index`.
- `tests/Browser/QuizAuthoringDuskTest.php` (SPEC-24) — single
  lifecycle-chain test (per `testing-conventions` journey-grouping
  convention, not a per-module file) covering type-switch options/essay
  toggling, template-clone add-option, min-2-options guard toast, marking
  an option correct applying `.is-correct`, `true_false`'s 2 fixed
  readonly rows, then create → edit → reorder through the shared
  `ModuleReorder.js` path.

Run narrowest of these first after touching this module:

```bash
vendor/bin/sail artisan test --filter=SubmitQuizAttemptTest
vendor/bin/sail artisan test --filter=QuizAttemptLimitsTest
vendor/bin/sail artisan test --filter=EssayManualGradingTest
vendor/bin/sail artisan test --filter=QuizManagementTest
vendor/bin/sail artisan test --filter=MultiTenantQuizManagementTest
vendor/bin/sail dusk --filter=StudentQuizAttemptTest
vendor/bin/sail dusk --filter=EssayGradingScreenTest
vendor/bin/sail dusk --filter=QuizAuthoringDuskTest
```

## Quiz Submission Scores Unexpectedly

- Check `isObjectiveAnswerCorrect()` exact-set comparison first.
  `multiple_choice` question with subset or superset of correct options
  is **entirely** wrong, no partial credit. Sorting both sides
  (`->sort()->values()->all()`) before comparing required — unsorted
  array comparison falsely rejects correctly-selected set submitted in
  different order.
- Empty `selected_option_ids` must never count correct. Even if
  (hypothetically) question had no correct options configured,
  `isObjectiveAnswerCorrect()` short-circuits `empty($selectedOptionIds)`
  to `false` before reaching set comparison.
- `GradeEssayAnswerAction::finalizeGrading()` score formula
  (`correctCount / totalQuestions * 100`) must match
  `SubmitQuizAttemptAction::finalize()` exactly — both divide by
  **total** question count, including any essay question, never just
  count of graded/auto-graded ones. Mismatch here is first thing to check
  when graded essay attempt final score looks off.

## Essay Attempt Never Leaves `awaiting_manual_grading`

- Confirm Gestor grading form (`quizzes/attempts/show.blade.php`) really
  submitted `grades[{answer_id}]` value for **every** essay `QuizAnswer`
  on attempt — `finalizeGrading()` runs only once none remain with
  `is_correct === null`. `required` radio group missing for one answer
  (e.g. Blade loop bug skipping question) silently leaves attempt stuck,
  no error surfaced.
- Check `QuizAttemptPolicy` isn't rejecting Gestor for cross-org attempt.
  403 on `quiz-attempts.show`/`quiz-attempts.grade` often mistaken for
  "grading form broken" when it's tenant-boundary check working as
  intended.

## Options UI Doesn't Hide for `type=essay`

- Check `window.QuizBuilder` actually initialized (`app.js`
  `DOMContentLoaded` listener calls `window.QuizBuilder.init()`). If
  `public/build` stale relative to `resources/js/modules/QuizBuilder.js`/
  `app.js`, run `vendor/bin/sail npm run build` (or ask user to run
  never `npm run dev`/`composer run dev`, which leave `public/hot` behind
  and break every Dusk run (see `laravel-dusk`).
- Confirm `<select data-question-type-select="{formSuffix}">` and options
  container it toggles (`[data-options-container="{formSuffix}"]`) share
  **same** `formSuffix`. `quizzes/edit.blade.php` renders one
  `_question-form` instance per modal (`'create'`, `'edit-{id}'` per
  question), so mismatched suffix between two included partials is most
  common cause of "select changes but nothing else on page reacts."
- Client-side hide/disable is UX only —
  `StoreQuizQuestionRequest`/`UpdateQuizQuestionRequest` and
  `QuizQuestionController::update()` own `type !== 'essay'` branch are
  real boundary; bug here never "fixed" by editing JS alone.

## Question Authoring Inline-Modal-Only — No Standalone `quiz-questions` Pages

`quizzes/edit.blade.php` is **only** screen authoring Questions — every
Question created/edited via modal in place (no full page navigation, no
`formSuffix` collision since each modal gets own `'create'`/`'edit-{id}'`
suffix), using shared `quizzes.partials._question-form`/`_question-list`
partials. `QuizQuestionController` routes only
`store`/`update`/`destroy`/`reorder` — no `index`/`create`/`edit` method,
no `quiz-questions.index` route, no standalone
`question-list-page`/`question-form-page` view.
`QuizController::store()` redirect target after creating Quiz is
`quizzes.edit`, not question-authoring index page. Earlier iteration had
two-screen design (standalone pages plus inline modals sharing one
`formSuffix = 'page'`); dropped mid-implementation in favor of
modals-only. Do not resurrect standalone pages/routes without updating
this skill and `quizzes-conventions` again.

## `QuizTimer.js` Cosmetic Only — Never Source of Truth

`[data-quiz-timer]` `data-started-at` seeded from Blade view own `now()`
at render time (no persisted `QuizAttempt.started_at` yet when student
looks at form — see `quizzes-conventions`). Dusk test asserting
time-limit *enforcement* (not just visual countdown) must submit form and
inspect resulting `QuizAttempt`/redirect message — never assert against
`QuizTimer` on-screen text reaching zero, since that proves only cosmetic
countdown ticked, not that server accept-but-fail rule fired.

## Auto-Update Protocol (SPEC-03)

Per `spec/specs/03-agentic-harness-and-self-updating-skills.md`: any
change to `QuizController`/`QuizQuestionController`/
`StudentQuizController`/`EssayGradingController`,
`SubmitQuizAttemptAction`/`GradeEssayAnswerAction`,
`QuizPolicy`/`QuizAttemptPolicy`, the
`quizzes*`/`quiz-questions*`/`quiz-attempts*`/`student.quizzes.*` routes,
Blade views under `resources/views/quizzes/`+
`resources/views/student/quizzes/`, or `QuizBuilder.js`/`QuizTimer.js`
**must** update all three quizzes skills (`quizzes-architecture`,
`quizzes-conventions`, `quizzes-maintenance`) in same change, before task
counts done. Also re-check:

- `.agents/agents/code-reviewer.md` — if change affects what reviewer
  must check for this module.
- Run `vendor/bin/sail artisan harness:check-skills` — fails build if any
  of three `quizzes-*` skills missing.

## Related Specs

- `spec/specs/08-quizzes-and-evaluations-engine.md` — RF08, RF09, RN02,
  RN03, RN04, RN11.
- `spec/specs/24-quiz-authoring-and-question-builder.md` — Material
  Bootstrap redesign of `quizzes/edit.blade.php`/
  `_question-form.blade.php` (two-column layout, `.is-correct` highlight,
  radio/checkbox visual swap); UI-only, no schema or business-rule
  change.
- `courses-maintenance` — analogous reorder/Policy cascade-authorize
  pattern this module copies one level deeper.
- `learning-maintenance` — `MarkLessonCompleteAction`,
  `EnsureStudentIsEnrolled`, both reused unmodified by student-facing
  side of this feature.

---

## E2E Coverage Lives in Lifecycle Chains, Not in Per-Module File

Browser tests in `tests/Browser/` grouped by **user journey (lifecycle
chain)** — one method drives create → edit → state change → delete →
consequence — **not** by module, spec, or use case. Consequences when
maintaining this module:

- **Finding coverage**: Dusk scenarios listed above may be asserted as
  numbered steps inside chain method, possibly in file named after
  another module when journey crosses module boundaries. Locate with
  `grep -rn "<route name|dusk selector>" tests/Browser/`, not by file
  name. Missing per-module file is **not** coverage gap.
- **Adding coverage**: extend existing chain for that journey with new
  numbered step carrying its own UI **and** DB assertion. Create new
  method only for independent negatives (403, cross-tenant, other actor);
  create new file only for genuinely new journey.
- **Debugging failure**: stack trace points at step, not whole scenario —
  match line to its `// N.` comment. Late failure usually means earlier
  step did not persist what it should.
- **Database**: no DB trait declared in `tests/Browser/*`;
  `DatabaseTruncation` inherited from `Tests\DuskTestCase`. Re-adding
  `DatabaseMigrations` is suite-wide performance regression. Files, cache
  and session **not** reset between methods.

Full rule: `testing-conventions`. Chain debugging: `testing-maintenance`.
