---
name: invitations-conventions
description: >
  Concrete code patterns, snippets, and guardrails for the Smart Invitation
  & Enrollment feature (SPEC-06): ProcessSmartInvitationAction's
  lockForUpdate transaction, the check-email/adaptive-form contract,
  EnrollmentController's course_user upsert pattern, and the
  InvitationLinkPolicy/route conventions. Use whenever writing a
  controller, Form Request, Policy, or Action that manages InvitationLink
  or course_user records, or wires the /convite/{token} endpoints.
license: MIT
metadata:
  feature: invitations
  role: conventions
  specs:
    - spec/specs/06-smart-invitation-and-enrollment-system.md
---

# Invitations Conventions

## `ProcessSmartInvitationAction`: Lock First, Re-Check Usability, Then Branch

The whole Action runs inside one `DB::transaction()`, and the very first
thing it does inside that transaction is re-fetch the `InvitationLink`
**with `lockForUpdate()`**, bypassing `OrgScope` (`withoutGlobalScopes()`)
since this runs with no authenticated tenant context:

```php
return DB::transaction(function () use ($token, $data) {
    $invitationLink = InvitationLink::query()
        ->withoutGlobalScopes()
        ->where('token', $token)
        ->lockForUpdate()
        ->first();

    if (! $invitationLink || ! $invitationLink->isUsable()) {
        throw new InvitationLinkInvalidException(/* ... */);
    }
    // ...
});
```

Never move the `isUsable()` check to before the `lockForUpdate()` call, and
never reuse an `InvitationLink` instance the caller already loaded before
entering the transaction — either mistake reopens the exact race the lock
exists to close (see `invitations-architecture`'s two-concurrent-requests
example).

## New Account vs. Existing Account: Password Check, Never a Silent Account Switch

```php
$user = User::query()->where('email', $data['email'])->first();

if ($user) {
    if (! Hash::check($data['password'], $user->password)) {
        throw ValidationException::withMessages([
            'password' => ['Senha incorreta para o e-mail informado.'],
        ]);
    }
} else {
    $user = User::create([...(new-account fields)..., 'org_id' => $invitationLink->org_id]);
    $user->assignRole(RolesEnum::ALUNO->value);
}
```

A wrong password on an existing e-mail is a `ValidationException` (surfaced
back to the `password` field, HTTP 422 via the normal FormRequest/Exception
pipeline), **not** an `InvitationLinkInvalidException` — the link itself is
perfectly usable, it's the submitted credential that's wrong. This also
deliberately does not re-check "does this e-mail exist" here: that was
already revealed, by design, by `/convite/check-email` one step earlier in
the flow (see the next section) — this branch's only job is authenticating
against the *submitted* password.

## `course_user` Upsert: Read-Then-Branch, Never a Blind `attach()`

```php
$enrollment = $user->courses()->withoutGlobalScopes()
    ->wherePivot('course_id', $invitationLink->course_id)->first();

if (! $enrollment) {
    $user->courses()->attach($invitationLink->course_id, ['enrolled_at' => now(), 'status' => 'active']);
} elseif ($enrollment->pivot->status === 'cancelled') {
    $user->courses()->updateExistingPivot($invitationLink->course_id, ['status' => 'active', 'enrolled_at' => now()]);
}
```

`course_user` has a real `UNIQUE(user_id, course_id)` constraint — a second
`attach()` for a pair that already has a row (active, cancelled, or
completed) would throw a DB integrity exception. `EnrollmentController::
store()` (RF21's manual-enroll form) follows the identical shape:

```php
if ($course->students()->where('users.id', $userId)->exists()) {
    $course->students()->updateExistingPivot($userId, ['status' => 'active', 'enrolled_at' => now()]);
} else {
    $course->students()->attach($userId, ['enrolled_at' => now(), 'status' => 'active']);
}
```

Both call sites reactivate a `cancelled` row to `active` rather than ever
attempting a second insert — see `invitations-architecture` for why this
must never touch a `completed` row the same way (out of this feature's
scope to un-complete a finished course; if that's ever requested, it needs
its own explicit design, not a tweak to this upsert).

## `check-email`: Reveal Existence By Design, Then Never Re-Reveal It

`InvitationController::checkEmail()` is the one endpoint in this feature
that intentionally answers "does an account with this e-mail exist?" to an
unauthenticated caller — that's the whole point of the adaptive form (RF03
§3). Every other endpoint in this feature must **not** leak that same fact
through a different channel (timing, distinct error codes, etc.) — in
particular, `ProcessSmartInvitationAction`'s wrong-password branch above
returns the same generic validation shape whether or not the account
exists elsewhere in the request lifecycle, since by the time `store()` is
called the client has already been told the answer via `check-email`.

## `InvitationLinkPolicy`: Mirrors `CoursePolicy`, Not `ModulePolicy`

`InvitationLink` carries its own `OrgScope` (like `Course`), so — same
reasoning as `courses-conventions`' Course vs. Module/Lesson split — this
Policy needs only the role check for `viewAny`/`create`, and one explicit
`org_id` comparison for `delete` (since a route-model-bound
`{invitation_link}` for `destroy` is not itself confined by any parent
route segment the way `index`/`create`/`store` are via `{course}`):

```php
public function delete(User $user, InvitationLink $invitationLink): bool
{
    return $this->authorize($user, $invitationLink->course()->withoutGlobalScopes()->firstOrFail());
}
```

The `withoutGlobalScopes()` here is load-bearing for the exact same reason
`ModulePolicy`/`LessonPolicy` need it in `courses-conventions`: reading the
parent `Course` through the normal scoped relation while the acting user is
a *different*-org Gestor returns `null` (the scope filters the row out),
turning an intended 403 into a null-argument crash.

## Route Shape: `courses.enrollments` Is Three Explicit Routes, Not A Resource

`routes/web.php` registers all three `courses.enrollments.*` routes
by hand — there is no `Route::resource('courses.enrollments', ...)` at
all, not even a partial one:

```php
Route::get('courses/{course}/enrollments', [EnrollmentController::class, 'index'])
    ->name('courses.enrollments.index');
Route::post('courses/{course}/enrollments', [EnrollmentController::class, 'store'])
    ->name('courses.enrollments.store');
Route::delete('courses/{course}/enrollments/{user}', [EnrollmentController::class, 'destroy'])
    ->name('courses.enrollments.destroy');
```

This is deliberate: there is no `Enrollment` Eloquent model to route-bind
(`course_user` is a pivot only — see `invitations-architecture`), so
`index`/`store` bind only `{course}`, and `EnrollmentController::destroy(
Course $course, User $user)` needs **both** the parent Course and the
target User bound from the URI — a single trailing `{enrollment}` segment
(what any `shallow()` resource's `destroy` would produce) cannot supply
two named route parameters. If you ever need to add `edit`/`update` to
this group, add another explicit `Route::` line the same way rather than
introducing `Route::resource()`/`shallow()` for this feature.
`InvitationLinkController`'s routes have no such problem (`InvitationLink`
is a real model, `{invitation_link}` alone is enough), so those *do* use
the plain `shallow()` resource shape (`only(['index', 'create', 'store',
'destroy'])`).

## Related Specs

- `spec/specs/06-smart-invitation-and-enrollment-system.md` — RF03, RF21,
  RN09.
- `courses-conventions` — the `withoutGlobalScopes()`-for-Policy-parent
  pattern this feature's `InvitationLinkPolicy` reuses verbatim.
