---
name: invitations-conventions
description: >
  Code patterns, snippets, guardrails for the per-student unique
  Invitation feature: RedeemStudentInvitationAction lockForUpdate
  transaction with post-lock host re-check, identity-bound token
  (no identity fields in the request), FinalizeStudentInvitationRequest
  contract, convite/show.blade.php guest-shell + readonly identity,
  StudentInvitationController get-or-create/rotate conventions, implicit
  route binding ({user}) for the gestor endpoints. Use when writing
  controller, Form Request, Policy, or Action managing StudentInvitation
  or course_user records, or wiring /convite/{token} endpoints.
license: MIT
metadata:
  feature: invitations
  role: conventions
---

# Invitations Conventions

## `RedeemStudentInvitationAction`: Lock First, Re-Check Usability, Then Branch

Whole Action runs inside one `DB::transaction()`. First thing inside that
transaction: re-fetch `StudentInvitation` **with `lockForUpdate()`**,
bypassing `OrgScope` (`withoutGlobalScopes()`) since this runs with no
authenticated tenant context:

```php
return DB::transaction(function () use ($token, $data) {
    $invitation = StudentInvitation::query()
        ->withoutGlobalScopes()
        ->where('token', $token)
        ->lockForUpdate()
        ->first();

    if (! $invitation) {
        throw InvitationInvalidException::notFound($token);
    }

    if ((int) $invitation->org_id !== (int) OrgContext::current()->orgId()) {
        throw InvitationInvalidException::notFound($token);
    }

    if ($reason = $invitation->unusableReason()) {
        throw InvitationInvalidException::forReason($reason, $token);
    }
    // ...
});
```

Never construct `new InvitationInvalidException('some sentence')` at a call
site: the visitor-facing copy lives on the exception (`userMessage()`), keyed
by reason, and is rendered once by `bootstrap/app.php` — see
`invitations-architecture`. Call sites only pick the *reason*: `::notFound()`
for a null row **and** for a wrong-host row **and** for a staff-target row,
`::forReason($invitation->unusableReason(), $token)` for a row that exists
but may not be redeemed, `::forReason(REASON_INACTIVE, $token)` for the
Gestor-deactivated credential.
`InvitationController::resolveUsableInvitation()` uses the exact same
three-step shape, minus the lock. The host check must sit *inside* the
transaction, after the lock — a redemption is arbitrated from the freshly
locked row, same as the usability verdict.

Never move the `isUsable()` check before the `lockForUpdate()` call. Never
reuse an `StudentInvitation` instance the caller loaded before entering the
transaction. Either mistake reopens the exact race the lock exists to close
(two concurrent redemptions of one single-use token).

## Identity Comes From The Token, Never From The Request

The Action resolves the account from `$invitation->user_id`; the Form
Request (`FinalizeStudentInvitationRequest`) validates ONLY
`password` (`required|min:8|confirmed`) and `consent`
(`accepted`, message
`'É necessário concordar para concluir o cadastro.'`). There is no
`email`/`name`/`cpf` field and no `check-email` endpoint — adding one back
reopens account enumeration and re-introduces the duplicate-identity
problems the unique-per-student paradigm exists to kill. The public view
renders e-mail/name as **readonly inputs** fed from `$invitation->student`;
the readonly-ness is UX, the *real* immutability is that the POST body's
identity fields are never read.

```php
$student = $invitation->student()->withoutGlobalScopes()->firstOrFail();

// staff promotion after issuance → token can't become a staff password
// reset; reads as 404 like every other forbidden redemption.
if ($student->hasAnyRole([RolesEnum::GESTOR->value, RolesEnum::ADMIN->value])) {
    throw InvitationInvalidException::notFound($token);
}

$credential = Credential::query()
    ->forOrg($invitation->org_id)
    ->where('user_id', $student->id)
    ->first();

if ($credential && $credential->status === 'inactive') {
    throw InvitationInvalidException::forReason(InvitationInvalidException::REASON_INACTIVE, $token);
}

if ($credential) {
    $credential->update(['password' => Hash::make($data['password']), 'status' => 'active']);
} else {
    Credential::create([...same shape, 'status' => 'active']);
}

$invitation->update(['used_at' => now()]);
Auth::login($student);
```

`pending` and missing credentials are both redeemable (the missing one is
recreated — same semantics as the Gestor issuing a fresh invite); only a
Gestor-imposed `inactive` credential is rejected. Redemption never touches
`course_user` — enrollment is Gestor work done before the link goes out.

## `convite/show.blade.php`: Guest Shell, `level="h2"`, Readonly Identity

The public invitation screen extends `layouts.guest` (the split shell:
institutional panel at `col-lg-5`, 440px form column) and opens with
`<x-layout.page-header kicker="Convite" title="Finalize seu cadastro"
level="h2" ... />`. **`level="h2"` is mandatory here** — the guest shell's
institutional panel already renders the page's only `<h1>`, so a default
`page-header` would emit a second one. `InvitationController::show()`
passes `tenantName` explicitly (`$invitation->organization?->name`)
because a visitor arriving from an invite has no tenant session for
`<x-layout.guest-panel>` to read.

Dusk contract on this screen: `dusk="invitation-form"` on the `<form>`,
`dusk="invitation-email"` / `dusk="invitation-name"` on the readonly
identity inputs, `dusk="invitation-password"` /
`dusk="invitation-password-confirmation"` on the credential fields and
`dusk="invitation-consent"` on the `<x-ui.switch name="consent">`.
Frozen in `tests/fixtures/dusk-selectors-snapshot.json` — do not rename
without updating the snapshot deliberately.

## Gestor Endpoints: Get-Or-Create For Copy, Transaction For Rotation

`StudentInvitationController::issue()` is deliberately **get-or-create**
(`StudentInvitation::where('user_id', $student->id)->usable()->first() ??
create`): the "Copiar convite" button must be idempotent — clicking it
twice hands back the same live token instead of silently rotating.
`regenerate()` is the opposite: revoke live + create new **in one
`DB::transaction()`**, so there is never a window with zero usable
invitations. Both return the URL built with plain
`url('/convite/'.$token)` — safe because the Gestor is browsing their own
portal's host (same assumption `OrgUrl` documents for non-queued
contexts).

Authorization is `Gate::authorize('updateStudent', $user)` — the student
directory's own boundary (`UserPolicy::managesSameOrgAluno`: gestor of
the host org + target holds account in that org + target is ALUNO). No
separate `StudentInvitationPolicy` exists; do not create one.

## Implicit Binding: The Route Placeholder Is `{user}` — Name The Parameter `$user`

The gestor invitation routes are
`gestor/students/{user}/convite[/renovar]`. Laravel's implicit binding
matches the placeholder name to the **parameter name**; naming the
controller parameter `$student` silently injects an empty `User` (no
exception), and the first thing to fail is the Policy with a 403 that
looks like an authorization bug but isn't. Match `GestorStudentController`:
`public function issue(User $user)`. Same trap documented in
`auth-orgs-conventions` for the `{user}` segment.

## Clipboard: Inline Script With `execCommand` Fallback

`navigator.clipboard` exists only in secure contexts (HTTPS or
localhost); the Dusk portal (`http://laravel.test` inside the compose
network) and any HTTP deployment have no such API, so both copy scripts
(the students-index "Copiar convite" fetch handler and the
`invitation_url` flash banners) go through one `copyInvitationText()`
helper that uses `navigator.clipboard.writeText()` when
`window.isSecureContext`, else a fixed-position `<textarea>` +
`document.execCommand('copy')`. Keep the toast via
`window.NotificationService.success(...)` guarded by `if
(window.NotificationService)` — unit tests render these views without the
module registry. Inline in the view's `@push('scripts')`, never a new
`resources/js/modules/` file (repo convention for screen-local glue).

## Route Shape: Explicit Routes, `{user}`-Nested, Under `role:gestor`

```php
Route::post('gestor/students/{user}/convite', [StudentInvitationController::class, 'issue'])
    ->name('gestor.students.invitations.issue');
Route::post('gestor/students/{user}/convite/renovar', [StudentInvitationController::class, 'regenerate'])
    ->name('gestor.students.invitations.regenerate');
Route::delete('gestor/students/{user}/convite', [StudentInvitationController::class, 'destroy'])
    ->name('gestor.students.invitations.destroy');
```

They live in the same `role:gestor` group as `gestor.students.*` (the
Gestor's exclusive Aluno directory), NOT in the `role:admin|gestor`
group: an Admin has no org-scoped Aluno directory and therefore no
invitation surface. The public `convite/{token}` pair stays in the
`guest` group, throttled (`throttle:10,1`).

## `CreateOrgStudentAction`: One Engine, Two Surfaces, Multi-Org Linking

Both create-a-student flows delegate to `App\Actions\CreateOrgStudentAction`:
`EnrollmentController::storeStudent` (course-nested form) and
`GestorStudentController::store` (directory-level "Cadastrar aluno", where
the Course picker is REQUIRED — the directory lists only enrolled Alunos).
Inside one transaction the Action writes: `User` (or REUSES the existing
person), `credentials` row (`Hash::make(Str::random(32))`,
`status: 'pending'` — never the CPF, never a known password; provisioned
only when the person has none in this org), `course_user` attach
(read-then-branch upsert, `cancelled` rows reactivated), and a
get-or-create usable `StudentInvitation` (reuse, never rotate).
`EnrollmentConfirmed` dispatches only after commit AND only when
`$result['enrolled']` is true — a no-op run never notifies. The redirect
flashes `invitation_url` plus a message differentiated by
`$result['existed']` (new person vs linked person vs already-member).

Multi-org people are LINKED, never duplicated: the identity rules are the
pair `App\Rules\AlunoEmail`/`AlunoCpf` (used by BOTH
`StoreStudentEnrollmentRequest` and `StoreGestorStudentRequest` in place
of `unique:users`) — an existing e-mail with the MATCHING CPF is the same
person and passes; a mismatched CPF, a CPF owned by another e-mail, or a
staff (gestor/admin) account fail with friendly, actionable copy. The
Action re-verifies the staff case defensively (assigns ALUNO when the
existing person lacks it). One `users` row, one `credentials` row per
Organization.

The manual-enroll side (`search` + `StoreEnrollmentRequest`) accepts
credentials in `['active', 'pending']` — a pending account is enrollable;
only a Gestor-imposed `inactive` is not.

## Related Skills

- `courses-conventions` — pivot-only `course_user` upsert shape the
  manual-enroll panel follows.
- `auth-orgs-conventions` — the `credentials` enum and per-org account
  rules this feature extends with the `pending` state.
