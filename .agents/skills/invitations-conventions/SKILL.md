---
name: invitations-conventions
description: >
  Code patterns, snippets, guardrails for Smart Invitation & Enrollment
  feature: ProcessSmartInvitationAction lockForUpdate
  transaction with post-lock host re-check, credential-based
  check-email/adaptive-form contract, EnrollmentController
  course_user upsert pattern, convite/show.blade.php guest-shell +
  .d-none-only visibility contract, InvitationLinkPolicy/route
  conventions. Use
  when writing controller, Form Request, Policy, or Action managing
  InvitationLink or course_user records, or wiring /convite/{token}
  endpoints.
license: MIT
metadata:
  feature: invitations
  role: conventions
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

    if (! $invitationLink) {
        throw InvitationLinkInvalidException::notFound($token);
    }

    if ((int) $invitationLink->org_id !== (int) OrgContext::current()->orgId()) {
        throw InvitationLinkInvalidException::notFound($token);
    }

    if ($reason = $invitationLink->unusableReason()) {
        throw InvitationLinkInvalidException::forReason($reason, $token);
    }
    // ...
});
```

Never construct `new InvitationLinkInvalidException('some sentence')` at a call
site: the visitor-facing copy lives on the exception (`userMessage()`), keyed by
reason, and is rendered once by `bootstrap/app.php` — see
`invitations-architecture`. Call sites only pick the *reason*: `::notFound()`
for a null row **and** for a wrong-host row, `::forReason($link->unusableReason(),
$token)` for a row that exists but may not be consumed.
`InvitationController::resolveUsableLink()` uses the exact same three-step
shape, minus the lock. The host check must sit *inside* the transaction,
after the lock — a redemption racing a link transfer between orgs is
arbitrated from the freshly locked row, same as the usability verdict.

Never move `isUsable()` check before `lockForUpdate()` call. Never reuse
`InvitationLink` instance caller loaded before entering transaction. Either
mistake reopens exact race lock exists to close (see
`invitations-architecture` two-concurrent-requests example).

## Existing Person: Branch On The Host Org's Credential, Never On A Global Column

```php
$user = User::query()->where('email', $data['email'])->first();

if ($user) {
    $credential = Credential::query()
        ->forOrg($invitationLink->org_id)
        ->where('user_id', $user->id)
        ->first();

    if ($credential) {
        if (! Hash::check($data['password'], $credential->password)) {
            throw ValidationException::withMessages([
                'password' => ['Senha incorreta para o e-mail informado.'],
            ]);
        }

        if ($credential->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['Esta conta está inativa. Procure o gestor da sua organização.'],
            ]);
        }
    } else {
        // Person exists in another portal only: typed password creates
        // THIS portal's account.
        Credential::create([
            'user_id' => $user->id,
            'org_id' => $invitationLink->org_id,
            'password' => Hash::make($data['password']),
            'status' => 'active',
        ]);
    }
} else {
    $user = User::create([...(identity fields: name/email/cpf/email_verified_at)...]);
    $user->assignRole(RolesEnum::ALUNO->value);
    Credential::create([...same shape as above...]);
}
```

Order inside the `$credential` branch is load-bearing: password first,
status second. Inactive status is blocked **after** the password check so
the status of an account is never disclosed to someone who cannot
authenticate into it (same order as login). Never validate against
`$user->password` — `users.password` no longer exists; the account hash
lives on the org's `credentials` row (`Credential::forOrg(...)`). Wrong
password is `ValidationException` (surfaced back to `password` field,
HTTP 422 via normal FormRequest/Exception pipeline), **not**
`InvitationLinkInvalidException` — link itself is fine, submitted
credential is wrong. Never write `org_id` on the `User::create` call:
`users` has no such column; the link's org lands on the `Credential`.

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
throws DB integrity exception. `EnrollmentController::store()` (the
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

## `check-email`: Existence Is Scoped To The Host Org's Credentials

`InvitationController::checkEmail()` is one endpoint in this feature that
intentionally answers "does account with this e-mail exist?" to
unauthenticated caller — the whole point of the adaptive form. The verdict
is **credential existence in the host org**, not mere user existence:

```php
$exists = $user !== null
    && Credential::query()->forOrg($context->orgId())->where('user_id', $user->id)->exists();
```

So a person registered only in another portal gets `exists: false` here,
sees the full new-account form, and the typed password provisions this
portal's credential on submit (see `invitations-architecture`). Every
other endpoint in this feature must **not** leak same fact through
different channel (timing, distinct error codes, etc.). In particular
`ProcessSmartInvitationAction` wrong-password branch above returns same
generic validation shape whether or not account exists elsewhere in request
lifecycle, since by time `store()` runs client already got answer via
`check-email`.

## `convite/show.blade.php`: Guest Shell + `.d-none`-Only Visibility

The public invitation screen extends `layouts.guest` (the split shell: 46%
institutional panel at `col-lg-5`, 440px form column) and opens with
`<x-layout.page-header kicker="Convite" :title="'Matrícula em '.$courseTitle"
level="h2" subtitle="..." />`. **`level="h2"` is mandatory here** — the guest
shell's institutional panel already renders the page's only `<h1>`, so a
default `page-header` would emit a second one. `InvitationController::show()`
passes `tenantName` explicitly (`$invitationLink->organization?->name`) because
a visitor arriving from an invite has no tenant session for
`<x-layout.guest-panel>` to read.

Visibility of the adaptive fields is **the `.d-none` class and nothing else** —
never the `hidden` attribute, never `style.display`. The server renders the same
screen without JavaScript and `ProcessInvitationRequest` validates
conditionally, so the hidden state must be one single, inspectable decision.
`SmartInvitationForm.applyVisibility()` is the module's only door to that class
(and clears a stray `hidden`/`display:none` when showing, to keep the class
authoritative).

Field wrappers keep the contract the JS module reads: every registration-only
field sits in `<div data-invitation-field="new-account">`, the existing-account
hint is a neutral `<p class="guest-hint ... d-none">` (block in `--blue-50`,
radius 12px — **not** an `.alert`) carrying
`data-invitation-existing-hint` / `data-invitation-field="existing-account-hint"`
/ `dusk="invitation-existing-account-hint"` on the same node, and `password`
stays outside any wrapper (both branches need it). Consent is
`<x-ui.switch name="consent" value="1" required label="Concordo em compartilhar
meus dados com a organização responsável por este curso." dusk="invitation-consent" />`
— label verbatim.

`ProcessInvitationRequest::messages()` owns the consent copy:
`'consent.accepted' => 'É necessário concordar para concluir a matrícula.'`.
Client-side `required` on the switch is a convenience only; the rule is what
holds when the attribute is stripped.

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

## Route Shape: `courses.enrollments` Is Seven Explicit Routes, Not A Resource

`routes/web.php:257-270` registers all seven `courses.enrollments.*` routes by
hand. No `Route::resource('courses.enrollments', ...)` at all, not even
partial:

```php
Route::get('courses/{course}/enrollments', [EnrollmentController::class, 'index'])
    ->name('courses.enrollments.index');
Route::get('courses/{course}/enrollments/search', [EnrollmentController::class, 'search'])
    ->name('courses.enrollments.search');
Route::get('courses/{course}/enrollments/create', [EnrollmentController::class, 'create'])
    ->name('courses.enrollments.create');
Route::post('courses/{course}/enrollments/store-student', [EnrollmentController::class, 'storeStudent'])
    ->name('courses.enrollments.store-student');
Route::post('courses/{course}/enrollments', [EnrollmentController::class, 'store'])
    ->name('courses.enrollments.store');
Route::delete('courses/{course}/enrollments/{user}', [EnrollmentController::class, 'destroy'])
    ->name('courses.enrollments.destroy');
Route::post('courses/{course}/enrollments/{user}/restore', [EnrollmentController::class, 'restore'])
    ->name('courses.enrollments.restore');
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

## Related Skills

- `courses-conventions` — `withoutGlobalScopes()`-for-Policy-parent pattern
  this feature `InvitationLinkPolicy` reuses verbatim.
