---
name: quizzes-maintenance
description: >
  Debug, test, edge-case guide for Quizzes/Evaluations feature:
  mandatory PHPUnit/Dusk test files, common
  attempt-limit/time-limit/essay-grading failure modes,
  `question-list-page`/`question-form-page` vs inline-modal view split,
  frontend-build gotcha for
  `QuizBuilder.js`/`QuizTimer.js`/`QuizTaking.js`, and the
  `OpenQuizAttemptAction` open-attempt/`open_slot` failure modes. Use when
  `SubmitQuizAttemptActionTest`, `StudentQuizControllerTest`,
  `QuizAttemptLimitsTest`,
  `EssayManualGradingTest`, or `QuizManagementTest` fails; quiz submission
  scores unexpectedly; essay attempt won't leave
  `awaiting_manual_grading`; or options UI doesn't hide for essay
  question.
license: MIT
metadata:
  feature: quizzes
  role: maintenance
---

# Quizzes Maintenance

## Mandatory Test Coverage for This Module

These tests guard this module's contract, must stay green (PHPUnit, no Pest):

- `tests/Feature/SubmitQuizAttemptActionTest.php` (renamed from
  `SubmitQuizAttemptTest.php`) — correction engine:
  auto-grading of `single_choice`/`multiple_choice`/`true_false`
  (exact-set matching, empty selection always wrong), essay answers
  routing attempt to `awaiting_manual_grading`, `lesson_progress` written
  with `completion_source = quiz_passed` only when `is_passed`.
- `tests/Feature/QuizAttemptLimitsTest.php` —
  `allow_retries`/`max_attempts` counting only completed
  (`awaiting_manual_grading`/`graded`) attempts, never stale
  `in_progress` one; accept-but-fail time-limit rule.
- `tests/Feature/StudentQuizControllerTest.php` — HTTP contract of the
  quiz page: `[data-quiz-timer]` markup (`data-started-at`/
  `data-time-limit-minutes`) seeded from the server-opened attempt,
  confirmation-modal markup, expired-attempt banner, and the
  accept-but-fail path exercised end-to-end through the POST route.
- `tests/Feature/EssayManualGradingTest.php` — `GradeEssayAnswerAction`
  grading one answer at a time, `finalizeGrading()` firing only once
  every essay answer on attempt graded, using exact same score formula as
  auto-grading (unanswered questions count wrong, still in denominator).
- `tests/Feature/EssayGradingTest.php` — `EssayGradingController`
  HTTP-layer contract: `pending()` queue FIFO-ordered by `completed_at`;
  Admin can view the queue, Aluno gets 403; cross-org Gestor gets 403 on
  both `quiz-attempts.show` and `quiz-attempts.grade` (answer stays
  ungraded); owning-org Gestor's full submission recomputes
  `score_percentage`/`is_passed` and transitions to `graded`; Admin can
  grade any org's attempt; an incomplete `grades[]` payload (bypassing
  client-side `required`) never finalizes the attempt
  (`score_percentage`/`is_passed`/the ungraded answer's `is_correct`
  all stay `null`); empty `grades` array fails validation with no state
  change; essay answer text is HTML-escaped on the grading screen.
- `tests/Feature/QuizManagementTest.php` — Gestor CRUD of
  Quiz/QuizQuestion/QuizOption, `quizzes.lesson_id` UNIQUE
  redirect-with-error guard, cross-tenant isolation.
- `tests/Browser/StudentQuizTakingDuskTest.php` (renamed from
  `StudentQuizAttemptTest.php`),
  `tests/Browser/EssayGradingScreenTest.php` (Dusk E2E) — full
  browser flow through `student/quizzes/show.blade.php` and
  `quizzes/attempts/show.blade.php`.
- `tests/Feature/MultiTenantQuizManagementTest.php` —
  additive cross-org isolation on Quiz/QuizQuestion (view/update/
  delete/reorder 403 on another org's records via direct route hits),
  options-payload isolation (smuggled foreign option id untouched), the
  4 question-type CRUD happy paths, and reorder dense-reassignment from
  non-contiguous `order_index`.
- `tests/Browser/QuizAuthoringDuskTest.php` — single
  lifecycle-chain test (per `testing-conventions` journey-grouping
  convention, not a per-module file) covering type-switch options/essay
  toggling, template-clone add-option, min-2-options guard toast, marking
  an option correct applying `.is-correct`, `true_false`'s 2 fixed
  readonly rows, then create → edit → reorder through the shared
  `ModuleReorder.js` path.

Run narrowest of these first after touching this module:

```bash
vendor/bin/sail artisan test --filter=SubmitQuizAttemptActionTest
vendor/bin/sail artisan test --filter=StudentQuizControllerTest
vendor/bin/sail artisan test --filter=QuizAttemptLimitsTest
vendor/bin/sail artisan test --filter=EssayManualGradingTest
vendor/bin/sail artisan test --filter=EssayGradingTest
vendor/bin/sail artisan test --filter=QuizManagementTest
vendor/bin/sail artisan test --filter=MultiTenantQuizManagementTest
vendor/bin/sail dusk --filter=StudentQuizTakingDuskTest
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

## `QuizTimer.js` Display Only — Never Source of Truth

`[data-quiz-timer]` `data-started-at` seeded from the `started_at` of the
`in_progress` `QuizAttempt` that `StudentQuizController::show()` opens
for a timed Quiz (see `quizzes-conventions`), so reloading the page does
not restart the countdown. Expiry only writes "Tempo esgotado" — it never
auto-submits. A Dusk test visiting a timed quiz therefore
finds an `in_progress` row: assert absence of a *`graded`* attempt, not
absence of any attempt. Dusk test asserting
time-limit *enforcement* (not just visual countdown) must submit form and
inspect resulting `QuizAttempt`/redirect message — never assert against
`QuizTimer` on-screen text reaching zero, since that proves only cosmetic
countdown ticked, not that server accept-but-fail rule fired.

## Student Can't Start a New Attempt / "Ghost" `in_progress` Row

Symptom: student still has retries left but the quiz page behaves as if an
attempt were running, or `max_attempts` counting looks off by one.

- Attempt counting deliberately ignores `in_progress` rows, so an
  abandoned open attempt is invisible. `OpenQuizAttemptAction::
  expireStaleAttempt()` is what converts it into a `graded`, zero-score,
  failed attempt — and `StudentQuizController::show()` must call it
  **before** `$completedAttempts` is counted. Moving that call after the
  count silently reintroduces the ghost row for one request.
- `expireStaleAttempt()` is a no-op on untimed quizzes (no
  `time_limit_minutes` means no deadline to miss) — an untimed quiz's
  open attempt is simply resumed forever, by design.
- Expiring **costs** an attempt (`completed_at` pinned to the deadline).
  If a test expects "abandon the timer, get a fresh countdown", the test
  is wrong, not the action.

## `UniqueConstraintViolationException` on `quiz_attempts`

`quiz_attempts_open_slot_unique` (`quiz_id, user_id, open_slot`) enforces
at most one open attempt per student per quiz. `OpenQuizAttemptAction::
start()` already catches the violation and resumes the winner's row, so a
leaking exception means the row was created **outside** the action — an
inline `QuizAttempt::create([... 'status' => 'in_progress'])` in a
controller, seeder, or test. Route it through `openOrResume()`, or (in a
factory) make sure the state does not produce a second open attempt.
Never set `open_slot` by hand: `QuizAttempt::booted()` derives it from
`status` on every save.

## Confirmation Modal Doesn't Submit / Submits Twice

`student/quizzes/show.blade.php` renders `<x-ui.confirm-modal>` **after**
`</form>` and links them with the `form="quiz-attempt-form"` prop. Two
regressions to watch for:

- Nesting the modal inside the form (or passing `action` instead of
  `form`) emits a nested `<form>` — the browser drops it and the confirm
  button becomes inert.
- Leaving `dusk="quiz-attempt-submit"` as `type="submit"` submits on the
  first click and the modal never gates anything; it must be
  `type="button"` with `data-bs-toggle="modal"`.

Both are covered by `tests/Feature/BladeComponentsTest.php` (component
level) and `tests/Browser/StudentQuizTakingDuskTest.php` (flow level).
The unanswered-count summary lives in `QuizTaking.js`; there is **no JS
test runner in this project**, so any change there must be verified by
`vendor/bin/sail npm run build` plus the Dusk test — and new `dusk=`
selectors must be added to `tests/fixtures/dusk-selectors-snapshot.json`
or `DuskSelectorContractTest` fails.

## Auto-Update Protocol

Any
change to `QuizController`/`QuizQuestionController`/
`StudentQuizController`/`EssayGradingController`,
`SubmitQuizAttemptAction`/`OpenQuizAttemptAction`/
`GradeEssayAnswerAction`, the `quiz_attempts.open_slot` column or its
`quiz_attempts_open_slot_unique` index,
`QuizPolicy`/`QuizAttemptPolicy`, the
`quizzes*`/`quiz-questions*`/`quiz-attempts*`/`student.quizzes.*` routes,
Blade views under `resources/views/quizzes/`+
`resources/views/student/quizzes/`, or `QuizBuilder.js`/`QuizTimer.js`/
`QuizTaking.js`
**must** update all three quizzes skills (`quizzes-architecture`,
`quizzes-conventions`, `quizzes-maintenance`) in same change, before task
counts done. Also re-check:

- `.agents/agents/code-reviewer.md` — if change affects what reviewer
  must check for this module.
- Run `vendor/bin/sail artisan harness:check-skills` — fails build if any
  of three `quizzes-*` skills missing.

## Related

- `courses-maintenance` — analogous reorder/Policy cascade-authorize
  pattern this module copies one level deeper.
- `learning-maintenance` — `MarkLessonCompleteAction`,
  `EnsureStudentIsEnrolled`, both reused unmodified by student-facing
  side of this feature.

---

## E2E Coverage Lives in Lifecycle Chains, Not in Per-Module File

Browser tests in `tests/Browser/` grouped by **user journey (lifecycle
chain)** — one method drives create → edit → state change → delete →
consequence — **not** by module or feature. Consequences when
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
