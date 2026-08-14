---
name: invitations-conventions
description: >
  Code patterns, snippets, guardrails for Smart Invitation & Enrollment
  feature (SPEC-06): ProcessSmartInvitationAction lockForUpdate
  transaction, check-email/adaptive-form contract, EnrollmentController
  course_user upsert pattern, InvitationLinkPolicy/route conventions. Use
  when writing controller, Form Request, Policy, or Action managing
  InvitationLink or course_user records, or wiring /convite/{token}
  endpoints.
license: MIT
metadata:
  feature: invitations
  role: conventions
  specs:
    - spec/specs/06-smart-invitation-and-enrollment-system.md
---

# Invitations Conventions

## `ProcessSmartInvitationAction`: Lock First, Re-Check Usability, Then Branch

Whole Action runs inside one `DB::transaction()`. First thing inside that
transaction: re-fetch `InvitationLink` **with `lockForUpdate()`**,
bypassing `OrgScope` (`withoutGlobalScopes()`) since this runs with no
authenticated tenant context:

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

Never move `isUsable()` check before `lockForUpdate()` call. Never reuse
`InvitationLink` instance caller loaded before entering transaction. Either
mistake reopens exact race lock exists to close (see
`invitations-architecture` two-concurrent-requests example).

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

Wrong password on existing e-mail is `ValidationException` (surfaced back
to `password` field, HTTP 422 via normal FormRequest/Exception pipeline),
**not** `InvitationLinkInvalidException` — link itself is fine, submitted
credential is wrong. Also deliberately does not re-check "does this e-mail
exist" here: already revealed, by design, by `/convite/check-email` one
step earlier in flow (see next section). This branch job is only
authenticating against *submitted* password.

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

`course_user` has real `UNIQUE(user_id, course_id)` constraint. Second
`attach()` for pair that already has row (active, cancelled, or completed)
throws DB integrity exception. `EnrollmentController::store()` (RF21
manual-enroll form) follows identical shape:

```php
if ($course->students()->where('users.id', $userId)->exists()) {
    $course->students()->updateExistingPivot($userId, ['status' => 'active', 'enrolled_at' => now()]);
} else {
    $course->students()->attach($userId, ['enrolled_at' => now(), 'status' => 'active']);
}
```

Both call sites reactivate `cancelled` row to `active`, never attempt
second insert. See `invitations-architecture` for why this must never touch
`completed` row same way — un-completing finished course is out of this
feature scope; if ever requested, needs own explicit design, not tweak to
this upsert.

## `check-email`: Reveal Existence By Design, Then Never Re-Reveal It

`InvitationController::checkEmail()` is one endpoint in this feature that
intentionally answers "does account with this e-mail exist?" to
unauthenticated caller — whole point of adaptive form (RF03 §3). Every
other endpoint in this feature must **not** leak same fact through
different channel (timing, distinct error codes, etc.). In particular
`ProcessSmartInvitationAction` wrong-password branch above returns same
generic validation shape whether or not account exists elsewhere in request
lifecycle, since by time `store()` runs client already got answer via
`check-email`.

## `InvitationLinkPolicy`: Mirrors `CoursePolicy`, Not `ModulePolicy`

`InvitationLink` carries own `OrgScope` (like `Course`), so — same
reasoning as `courses-conventions` Course vs. Module/Lesson split — this
Policy needs only role check for `viewAny`/`create`, plus one explicit
`org_id` comparison for `delete` (route-model-bound `{invitation_link}` for
`destroy` is not confined by any parent route segment the way
`index`/`create`/`store` are via `{course}`):

```php
public function delete(User $user, InvitationLink $invitationLink): bool
{
    return $this->authorize($user, $invitationLink->course()->withoutGlobalScopes()->firstOrFail());
}
```

`withoutGlobalScopes()` here is load-bearing for exact same reason
`ModulePolicy`/`LessonPolicy` need it in `courses-conventions`: reading
parent `Course` through normal scoped relation while acting user is
*different*-org Gestor returns `null` (scope filters row out), turning
intended 403 into null-argument crash.

## Route Shape: `courses.enrollments` Is Three Explicit Routes, Not A Resource

`routes/web.php` registers all three `courses.enrollments.*` routes by
hand. No `Route::resource('courses.enrollments', ...)` at all, not even
partial:

```php
Route::get('courses/{course}/enrollments', [EnrollmentController::class, 'index'])
    ->name('courses.enrollments.index');
Route::post('courses/{course}/enrollments', [EnrollmentController::class, 'store'])
    ->name('courses.enrollments.store');
Route::delete('courses/{course}/enrollments/{user}', [EnrollmentController::class, 'destroy'])
    ->name('courses.enrollments.destroy');
```

Deliberate: no `Enrollment` Eloquent model to route-bind (`course_user` is
pivot only — see `invitations-architecture`), so `index`/`store` bind only
`{course}`, and `EnrollmentController::destroy(Course $course, User $user)`
needs **both** parent Course and target User bound from URI. Single
trailing `{enrollment}` segment (what any `shallow()` resource `destroy`
produces) cannot supply two named route parameters. Need `edit`/`update` in
this group later? Add another explicit `Route::` line same way, do not
introduce `Route::resource()`/`shallow()` for this feature.
`InvitationLinkController` routes have no such problem (`InvitationLink` is
real model, `{invitation_link}` alone is enough), so those *do* use plain
`shallow()` resource shape (`only(['index', 'create', 'store',
'destroy'])`).

## Related Specs

- `spec/specs/06-smart-invitation-and-enrollment-system.md` — RF03, RF21,
  RN09.
- `courses-conventions` — `withoutGlobalScopes()`-for-Policy-parent pattern
  this feature `InvitationLinkPolicy` reuses verbatim.
