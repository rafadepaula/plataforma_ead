---
name: quizzes-conventions
description: >
  Concrete code patterns, snippets, and guardrails for the Quizzes/
  Evaluations feature (SPEC-08): the single-page quiz-taking form contract,
  `QuizPolicy`/`QuizAttemptPolicy` cascade-authorize conventions, the
  create-then-manage-questions redirect flow, `QuizBuilder.js`'s
  essay-type/single-correct-option behavior, and the reused
  `[data-reorder-url]` reorder contract. Use whenever writing a controller,
  Policy, Form Request, Blade view, or JS module that manages `Quiz`/
  `QuizQuestion`/`QuizOption` records or renders the Aluno's quiz-taking or
  Gestor's essay-grading screens.
license: MIT
metadata:
  feature: quizzes
  role: conventions
  specs:
    - spec/specs/08-quizzes-and-evaluations-engine.md
---

# Quizzes Conventions

## The Student Quiz Screen Is Single-Page, Never a Server-Side "In-Progress" Attempt

`spec/docs/mockups/05-quiz-avaliacao.md` models the Aluno's quiz screen as
one-question-per-page with `Anterior`/`Próxima` navigation and a per
-question POST, but `SubmitQuizAttemptAction` corrects the *whole* attempt
in a single pass (SPEC-08 §2). `StudentQuizController::show()` therefore
never creates a `QuizAttempt` row up front — there is no "start attempt"
step, no partial-answer save, and no server-persisted `started_at` to key
a real countdown off of:

```php
public function show(Lesson $lesson): View
{
    $quiz = $lesson->quiz()->with(['questions' => fn ($q) => $q->orderBy('order_index')->with('options')])->firstOrFail();
    // ...compute $canAttempt/$completedAttempts/$bestScore/$pendingAttempt...
    return view('student.quizzes.show', [...]); // no $attempt key
}
```

`resources/views/student/quizzes/show.blade.php` mirrors this: every
question is rendered and POSTed together
(`answers[{question_id}][selected_option_ids][]`/`[essay_answer]`), and the
`[data-quiz-timer]` countdown seeds itself from `now()` at render time
(`data-started-at="{{ now()->toIso8601String() }}"`) rather than any
persisted attempt — it is a cosmetic approximation only, gated entirely
behind `$canAttempt` (never rendered at all once the student is out of
attempts). The mockup is a **visual reference for CSS only** — never
reintroduce a `page=` query param / multi-request flow.

## Gating the Form on `$canAttempt`, Not a Route Middleware

`StudentQuizController::show()` always renders `200 OK` — it never 403s a
student who's out of attempts, because they still need to see their best
score/history. The Blade view is what hides the form:

```blade
@if(!$canAttempt)
    <x-ui.alert variant="accent-2">...reason...</x-ui.alert>
@else
    <form method="POST" action="{{ route('student.quizzes.submit', $lesson) }}">...</form>
@endif
```

The real enforcement is `SubmitQuizAttemptAction::guardAttemptLimits()` on
the `submit()` POST — the view-level gate is UX only, a student who somehow
POSTs anyway (stale tab, replayed request) still gets rejected server-side
with a `ValidationException` the controller turns into `back()->withErrors()`.

## `QuizController@store`'s Redirect Target Is Always `quizzes.edit`

Creating a Quiz redirects straight to the question-authoring screen, never
back to the Lesson — a Quiz with zero Questions is not yet useful:

```php
$quiz = $lesson->quiz()->create($request->validated());
return redirect()->route('quizzes.edit', $quiz)->with('success', ...);
```

There is **no** standalone `quiz-questions.index`/`.create`/`.edit`
full-page screen — Questions are authored exclusively via modals on
`quizzes/edit.blade.php` (`quizzes.partials._question-form`/
`_question-list`). `QuizQuestionController` only exposes
`store`/`update`/`destroy`/`reorder`; every one of those redirects back to
`quizzes.edit` too. Do not reintroduce a bare `GET quiz-questions/create`
or `.../{id}/edit` route — the design converged on inline-modal-only
authoring (see `quizzes-maintenance` for why this superseded an earlier
two-screen design).

## `quizzes.lesson_id` Is UNIQUE — Always Redirect, Never Let the DB 500

Both `QuizController::create()` and `::store()` check
`$lesson->quiz()->exists()` **before** doing anything else and redirect to
`quizzes.edit` with a flash error — never rely on the DB unique constraint
throwing, since that would surface as an uncaught `QueryException` (500),
not a friendly 422-style redirect:

```php
if ($lesson->quiz()->exists()) {
    return redirect()->route('quizzes.edit', $lesson->quiz)
        ->with('error', 'Esta lição já possui um questionário.');
}
```

## Policies: `QuizPolicy` Cascade-Authorizes Through `Lesson->Module->Course`, `QuizAttemptPolicy` Scopes to the Grading Gestor's Org

`QuizPolicy` mirrors `LessonPolicy::parentCourse()` one level deeper — the
`create` ability takes the parent `Lesson` explicitly
(`Gate::authorize('create', [Quiz::class, $lesson])`), every other ability
loads the Course **bypassing `OrgScope`** to compare `org_id`:

```php
protected function parentCourse(Quiz $quiz): Course
{
    return $quiz->lesson->module->course()->withoutGlobalScopes()->firstOrFail();
}
```

`QuizAttemptPolicy::view()`/`grade()` gate the Gestor's manual-grading
screen the same way, walking `$quizAttempt->quiz->lesson->module->course`.
`QuizQuestionController` has **no dedicated Policy of its own** — every
action reuses `Gate::authorize('update', $quizQuestion->quiz)` (or `$quiz`
directly on `index`/`create`/`store`), since authoring a Question is part
of managing its parent Quiz, not a separate authorization concept.

## `StoreQuizQuestionRequest`/`UpdateQuizQuestionRequest`: Options Are Conditional on `type`

`options` validation (`required_unless:type,essay` + a `min:2` rule,
`is_correct` boolean per row) is skipped entirely for `type=essay` — an
essay question sending an `options` array anyway must have it silently
ignored server-side (`QuizQuestionController` never reads `options` for an
essay question, see below), never validated against the "at least 2 rows"
rule that only makes sense for the other three types.

`QuizQuestionController::update()` independently double-guards this at
persistence time — even if a stray `options` payload somehow slipped past
the Form Request, existing options on an essay question are deleted rather
than upserted:

```php
if ($quizQuestion->type !== 'essay') {
    $quizQuestion->options()->whereNotIn('id', $keptOptionIds)->delete();
} else {
    $quizQuestion->options()->delete();
}
```

## `QuizBuilder.js`: Hide Options for Essay, Enforce Single-Correct for `single_choice`/`true_false`

Every DOM query is scoped by a `formSuffix` (`'create'`, `'edit-{id}'`, or
`'page'` on the standalone form pages) — this partial is included once per
modal on `quizzes/edit.blade.php`, so a bare `document.querySelector` would
only ever hit the first form on the page:

```js
applyTypeBehavior(suffix) {
    const isEssay = this.currentType(suffix) === 'essay';
    // hides [data-options-container="{suffix}"], shows [data-essay-hint="{suffix}"],
    // and disables every option checkbox/text input so essay never submits options[]
}
```

`enforceSingleCorrect()` unchecks every sibling `[data-correct-checkbox]`
in the same form when one is checked, **unless** `type === 'multiple_choice'`
— `single_choice`/`true_false` only ever have one correct option. Removing
an option row (persisted or not) just removes it from the DOM — no
separate "removed ids" field is submitted. `QuizQuestionController::update()`
deletes whatever persisted option ids are no longer present in the
submitted `options[]` array; everything else present in `options[]` is an
upsert (matched by `options[{i}][id]`, blank for a brand-new row).

## Reorder: Reuses `ModuleReorder.js`, No New JS Module

`quizzes/partials/_question-list.blade.php`'s `<ul>` carries the exact same
`[data-reorder-url]`/`<li data-id="...">` contract
`courses/modules/_list.blade.php`/`modules/lessons/index.blade.php` already
use — `window.ModuleReorder` (registered once in `app.js`) binds to *any*
matching list on the page, so no `quiz-questions-reorder.js` was written.
`QuizQuestionController::reorder()` follows the identical dense
`0..n-1`-reassignment + "every submitted id belongs to this Quiz" guard
`ModuleController::reorder()`/`LessonController::reorder()` use (see
`courses-conventions`).

## Time-Limit Display: Compute On Read, Never Persist Text

Because `quiz_attempts` has no `notes`/warning column, "Tempo excedido" is
never written to the database — `SubmitQuizAttemptAction::timeExceeded()`
computes it from `started_at`/`completed_at`/`time_limit_minutes` every
time it's needed. Any future view that needs to *display* this (e.g. an
attempt-history screen) must recompute it the same way rather than reading
a stored flag:

```php
$timeExceeded = $quiz->time_limit_minutes
    && $attempt->completed_at
    && $attempt->started_at->diffInMinutes($attempt->completed_at) > $quiz->time_limit_minutes;
```

## Two Different `grades`/`answers` Payload Shapes — Don't Confuse Them

`SubmitQuizAttemptRequest`'s `answers` is **keyed by `question_id`**
(`answers[{{ $question->id }}][selected_option_ids][]`/`[essay_answer]`) —
`StudentQuizController::submit()` folds the array key back in as
`question_id` before calling `SubmitQuizAttemptAction::execute()`, so the
Blade form never needs a hidden `question_id` input.

`GradeEssayAnswerRequest`'s `grades`, by contrast, is a **plain list** of
`{answer_id, is_correct}` entries — `grades[{{ $index }}][answer_id]`/
`[is_correct]`, sharing the loop's own `$index` (not the answer's own id as
the array key). `quizzes/attempts/show.blade.php` carries the id via a
hidden input precisely because, unlike `answers`, the array key itself is
never read as data here:

```blade
<input type="hidden" name="grades[{{ $index }}][answer_id]" value="{{ $answer?->id }}" />
<input type="radio" name="grades[{{ $index }}][is_correct]" value="1" ... />
```

If you change either Form Request's validation shape, grep the
corresponding Blade view's `name="..."` attributes in the same change — a
shape mismatch here fails silently at the HTTP layer (a 302 back with
session errors, never a 500), so it is easy to miss without actually
submitting the form end-to-end.

## Manual Grading Form Requires Every Essay Answer Graded Before Submitting

`quizzes/attempts/show.blade.php` puts `required` on both radio inputs of
every `grades[{answer_id}]` group — `GradeEssayAnswerAction::
finalizeGrading()` only recomputes the attempt once **every** essay answer
has a non-null `is_correct`, so a partial submission that silently skipped
a question would leave the attempt stuck in `awaiting_manual_grading`
forever. Never make a `grades[]` radio group optional on this screen.
