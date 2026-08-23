---
name: quizzes-conventions
description: >
  Code patterns, snippets, guardrails for Quizzes/Evaluations feature
  (SPEC-08): single-page quiz-taking form contract,
  `QuizPolicy`/`QuizAttemptPolicy` cascade-authorize conventions,
  create-then-manage-questions redirect flow, `QuizBuilder.js`
  essay-type/single-correct-option behavior, reused `[data-reorder-url]`
  reorder contract. Use when writing controller, Policy, Form Request,
  Blade view, or JS module managing `Quiz`/`QuizQuestion`/`QuizOption`
  records or rendering Aluno quiz-taking or Gestor essay-grading screens.
license: MIT
metadata:
  feature: quizzes
  role: conventions
  specs:
    - spec/specs/08-quizzes-and-evaluations-engine.md
---

# Quizzes Conventions

## Student Quiz Screen Is Single-Page, Never Server-Side "In-Progress" Attempt

`spec/docs/mockups/05-quiz-avaliacao.md` models Aluno quiz screen as
one-question-per-page with `Anterior`/`Próxima` navigation plus
per-question POST, but `SubmitQuizAttemptAction` corrects *whole* attempt
in single pass (SPEC-08 §2). So `StudentQuizController::show()` never
creates `QuizAttempt` row up front — no "start attempt" step, no
partial-answer save, no server-persisted `started_at` to key real
countdown off:

```php
public function show(Lesson $lesson): View
{
    $quiz = $lesson->quiz()->with(['questions' => fn ($q) => $q->orderBy('order_index')->with('options')])->firstOrFail();
    // ...compute $canAttempt/$completedAttempts/$bestScore/$pendingAttempt...
    return view('student.quizzes.show', [...]); // no $attempt key
}
```

`resources/views/student/quizzes/show.blade.php` mirrors this: every
question rendered and POSTed together
(`answers[{question_id}][selected_option_ids][]`/`[essay_answer]`), and
`[data-quiz-timer]` countdown seeds itself from `now()` at render time
(`data-started-at="{{ now()->toIso8601String() }}"`) rather than any
persisted attempt — cosmetic approximation only, gated entirely behind
`$canAttempt` (never rendered once student out of attempts). Mockup is
**visual reference for CSS only** — never reintroduce `page=` query param
/ multi-request flow.

## Gate Form on `$canAttempt`, Not Route Middleware

`StudentQuizController::show()` always renders `200 OK` — never 403s
student out of attempts, since they still need to see best score/history.
Blade view hides form:

```blade
@if(!$canAttempt)
    <x-ui.alert variant="accent-2">...reason...</x-ui.alert>
@else
    <form method="POST" action="{{ route('student.quizzes.submit', $lesson) }}">...</form>
@endif
```

Real enforcement is `SubmitQuizAttemptAction::guardAttemptLimits()` on
`submit()` POST — view-level gate is UX only. Student who POSTs anyway
(stale tab, replayed request) still rejected server-side with
`ValidationException` controller turns into `back()->withErrors()`.

## `QuizController@store` Redirect Target Always `quizzes.edit`

Creating Quiz redirects straight to question-authoring screen, never back
to Lesson — Quiz with zero Questions not yet useful:

```php
$quiz = $lesson->quiz()->create($request->validated());
return redirect()->route('quizzes.edit', $quiz)->with('success', ...);
```

**No** standalone `quiz-questions.index`/`.create`/`.edit` full-page
screen — Questions authored exclusively via modals on
`quizzes/edit.blade.php` (`quizzes.partials._question-form`/
`_question-list`). `QuizQuestionController` exposes only
`store`/`update`/`destroy`/`reorder`; every one redirects back to
`quizzes.edit` too. Do not reintroduce bare `GET quiz-questions/create`
or `.../{id}/edit` route — design converged on inline-modal-only
authoring (see `quizzes-maintenance` for why this superseded earlier
two-screen design).

## `quizzes.lesson_id` Is UNIQUE — Always Redirect, Never Let DB 500

Both `QuizController::create()` and `::store()` check
`$lesson->quiz()->exists()` **before** anything else and redirect to
`quizzes.edit` with flash error. Never rely on DB unique constraint
throwing — that surfaces as uncaught `QueryException` (500), not friendly
422-style redirect:

```php
if ($lesson->quiz()->exists()) {
    return redirect()->route('quizzes.edit', $lesson->quiz)
        ->with('error', 'Esta lição já possui um questionário.');
}
```

## Policies: `QuizPolicy` Cascade-Authorizes Through `Lesson->Module->Course`, `QuizAttemptPolicy` Scopes to Grading Gestor's Org

`QuizPolicy` mirrors `LessonPolicy::parentCourse()` one level deeper.
`create` ability takes parent `Lesson` explicitly
(`Gate::authorize('create', [Quiz::class, $lesson])`); every other ability
loads Course **bypassing `OrgScope`** to compare `org_id`:

```php
protected function parentCourse(Quiz $quiz): Course
{
    return $quiz->lesson->module->course()->withoutGlobalScopes()->firstOrFail();
}
```

`QuizAttemptPolicy::view()`/`grade()` gate Gestor manual-grading screen
same way, walking `$quizAttempt->quiz->lesson->module->course`.
`QuizQuestionController` has **no dedicated Policy of its own** — every
action reuses `Gate::authorize('update', $quizQuestion->quiz)` (or
`$quiz` directly on `index`/`create`/`store`), since authoring Question
is part of managing parent Quiz, not separate authorization concept.

## `StoreQuizQuestionRequest`/`UpdateQuizQuestionRequest`: Options Conditional on `type`

`options` validation (`required_unless:type,essay` + `min:2` rule,
`is_correct` boolean per row) skipped entirely for `type=essay`. Essay
question sending `options` array anyway must have it silently ignored
server-side (`QuizQuestionController` never reads `options` for essay
question, see below), never validated against "at least 2 rows" rule that
only makes sense for other three types.

`QuizQuestionController::update()` double-guards this at persistence
time. Even if stray `options` payload slipped past Form Request, existing
options on essay question get deleted rather than upserted:

```php
if ($quizQuestion->type !== 'essay') {
    $quizQuestion->options()->whereNotIn('id', $keptOptionIds)->delete();
} else {
    $quizQuestion->options()->delete();
}
```

## `QuizBuilder.js`: Hide Options for Essay, Enforce Single-Correct for `single_choice`/`true_false`

Every DOM query scoped by `formSuffix` (`'create'`, `'edit-{id}'`, or
`'page'` on standalone form pages) — this partial included once per modal
on `quizzes/edit.blade.php`, so bare `document.querySelector` would only
hit first form on page:

```js
applyTypeBehavior(suffix) {
    const isEssay = this.currentType(suffix) === 'essay';
    // hides [data-options-container="{suffix}"], shows [data-essay-hint="{suffix}"],
    // and disables every option checkbox/text input so essay never submits options[]
}
```

`enforceSingleCorrect()` unchecks every sibling `[data-correct-checkbox]`
in same form when one checked, **unless** `type === 'multiple_choice'` —
`single_choice`/`true_false` only ever have one correct option. Removing
option row (persisted or not) just removes it from DOM — no separate
"removed ids" field submitted. `QuizQuestionController::update()` deletes
whatever persisted option ids no longer present in submitted `options[]`
array; everything else present in `options[]` is upsert (matched by
`options[{i}][id]`, blank for brand-new row).

## `quizzes/edit.blade.php` Is a Two-Column Grid (Material Bootstrap, SPEC-24)

Rules form and questions section sit in a Bootstrap `row g-4`: rules
`x-ui.card` in `col-lg-5` (left), questions header/list in `col-lg-7`
(right), per `spec/new_ds/DESIGN.md` §4.5. Below `lg` it stacks to a
single full-width column automatically (no bespoke breakpoint CSS) — do
not reintroduce a fixed-width layout or hardcode desktop-only widths.

## Option Rows Carry `.quiz-option-row`/`.is-correct`, Radio↔Checkbox Swap Is Cosmetic Only

Every `[data-option-row]` (server-rendered, blank-form, and the
`<template data-option-template>` clone target) also carries
`.quiz-option-row` (base styling in `resources/scss/components/
_quiz-builder.scss`). A row whose `[data-correct-checkbox]` is checked
gets `.is-correct` added (mint `--mint-100` background, `--secondary`
border) — server sets it on initial render when `$option->is_correct` is
true; client keeps it in sync via `QuizBuilder.js`'s
`syncRowHighlight()`/`syncCorrectHighlight()`, called from the `change`
listener, `applyTypeBehavior()` (per-row loop, on init/type switch), and
`applyRowDisabledState()` (freshly cloned rows always start
unchecked/uncorrect — never highlighted on clone).

`applyTypeBehavior()`/`applyRowDisabledState()` also toggle each row's
`[data-correct-checkbox].type` between `radio`/`checkbox` to match
`single_choice`/`true_false` vs `multiple_choice` visually. This is
**purely cosmetic** — the checkboxes are never given a shared `name`
attribute, so native radio-group exclusivity never applies.
`enforceSingleCorrect()` (existing, see below) remains the **only**
mechanism enforcing single-correct-option for `single_choice`/
`true_false`. Do not remove `enforceSingleCorrect()` under the
assumption that swapping `type="radio"` makes it redundant.

## Reorder: Reuses `ModuleReorder.js`, No New JS Module

`quizzes/partials/_question-list.blade.php` `<ul>` carries exact same
`[data-reorder-url]`/`<li data-id="...">` contract
`courses/modules/_list.blade.php`/`modules/lessons/index.blade.php`
already use — `window.ModuleReorder` (registered once in `app.js`) binds
to *any* matching list on page, so no `quiz-questions-reorder.js` was
written. `QuizQuestionController::reorder()` follows identical dense
`0..n-1`-reassignment + "every submitted id belongs to this Quiz" guard
`ModuleController::reorder()`/`LessonController::reorder()` use (see
`courses-conventions`).

## Time-Limit Display: Compute On Read, Never Persist Text

`quiz_attempts` has no `notes`/warning column, so "Tempo excedido" never
written to database. `SubmitQuizAttemptAction::timeExceeded()` computes
it from `started_at`/`completed_at`/`time_limit_minutes` every time
needed. Any future view that needs to *display* this (e.g.
attempt-history screen) must recompute same way rather than read stored
flag:

```php
$timeExceeded = $quiz->time_limit_minutes
    && $attempt->completed_at
    && $attempt->started_at->diffInMinutes($attempt->completed_at) > $quiz->time_limit_minutes;
```

## Two Different `grades`/`answers` Payload Shapes — Don't Confuse Them

`SubmitQuizAttemptRequest` `answers` is **keyed by `question_id`**
(`answers[{{ $question->id }}][selected_option_ids][]`/`[essay_answer]`).
`StudentQuizController::submit()` folds array key back in as
`question_id` before calling `SubmitQuizAttemptAction::execute()`, so
Blade form never needs hidden `question_id` input.

`GradeEssayAnswerRequest` `grades`, by contrast, is **plain list** of
`{answer_id, is_correct}` entries — `grades[{{ $index }}][answer_id]`/
`[is_correct]`, sharing loop's own `$index` (not answer's own id as array
key). `quizzes/attempts/show.blade.php` carries id via hidden input
precisely because, unlike `answers`, array key itself never read as data
here:

```blade
<input type="hidden" name="grades[{{ $index }}][answer_id]" value="{{ $answer?->id }}" />
<input type="radio" name="grades[{{ $index }}][is_correct]" value="1" ... />
```

Change either Form Request validation shape, grep corresponding Blade
view `name="..."` attributes in same change — shape mismatch here fails
silently at HTTP layer (302 back with session errors, never 500), so easy
to miss without submitting form end-to-end.

## Manual Grading Form Requires Every Essay Answer Graded Before Submit

`quizzes/attempts/show.blade.php` puts `required` on both radio inputs of
every `grades[{answer_id}]` group. `GradeEssayAnswerAction::
finalizeGrading()` only recomputes attempt once **every** essay answer
has non-null `is_correct`, so partial submission that silently skipped
question would leave attempt stuck in `awaiting_manual_grading` forever.
Never make `grades[]` radio group optional on this screen.
