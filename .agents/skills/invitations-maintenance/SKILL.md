---
name: invitations-maintenance
description: >
  Debug, test, edge-case guide for the per-student unique Invitation
  feature: convite/show.blade.php finalize form, RedeemStudentInvitationAction
  lockForUpdate transaction, pending credential lifecycle, clipboard
  fallback, mandatory PHPUnit/Dusk test files. Use when
  StudentInvitationHttpTest, EnrollmentManagementTest,
  RedeemStudentInvitationActionTest, StudentInvitationFinalizeDuskTest or
  PublicFlowsHostScopeTest fails; finalize form rejects a valid password;
  copy/renew button does nothing; or a redemption unexpectedly 404s.
license: MIT
metadata:
  feature: invitations
  role: maintenance
---

# Invitations Maintenance

## Mandatory Test Coverage for This Module

These tests guard this module's contract, must stay green (PHPUnit, no Pest):

- `tests/Unit/Actions/RedeemStudentInvitationActionTest.php` —
  transaction-level coverage of `RedeemStudentInvitationAction`:
  `pending` account activation with the chosen password, single-use
  enforcement (second redemption reads as `REASON_USED`), unknown token,
  wrong-host token reads as not-found, revoked/expired invitations,
  Gestor-deactivated (`inactive`) credential rejection without touching
  the row, missing credential recreated, `active` account password
  replacement (re-issued invite = password reset), staff-promoted person
  rejected as not-found.
- `tests/Feature/StudentInvitationHttpTest.php` — HTTP-level coverage of
  the public `/convite/{token}` routes (show renders identity readonly
  from the token, per-reason 404 copy, finalize logs in + activates +
  marks `used_at`, forged `email` field ignored, password/consent
  validation, inactive account rejected) **and** the Gestor endpoints
  (issue get-or-create idempotency, regenerate rotation, destroy,
  cross-org 403, ALUNO 403, user deletion cascades invitations).
- `tests/Feature/Tenancy/PublicFlowsHostScopeTest.php` — host tenancy of
  the public flow: `/convite/{token}` 200 on the invitation org's host
  and 404 (indistinguishable from unknown token) on another org's host.
- `tests/Feature/EnrollmentManagementTest.php` — Gestor enrollment panel:
  manual enroll, revoke (`status = 'cancelled'`), reactivating cancelled
  enrollment, double-active-enrollment 422, org-scoped 404/403; plus
  `storeStudent` issuing the pending credential + the flashed
  `invitation_url`.
- `tests/Browser/StudentInvitationFinalizeDuskTest.php` — E2E journeys,
  **one browser session per method** (an idle Selenium session is killed
  by inactivity timeout, so never hold two `Browser` arguments where one
  is used much later): Gestor sees "Convite pendente" + copy toast,
  Aluno finalize (identity readonly → password → lands on `/meus-cursos`
  with credential `active`), used-link screen with no
  `@invitation-form`, renew modal rotating the token with the flash
  banner pointing at the new one.

Run narrowest of these first after touching this module:

```bash
vendor/bin/sail artisan test --filter=RedeemStudentInvitationActionTest
vendor/bin/sail artisan test --filter=StudentInvitationHttpTest
vendor/bin/sail artisan test --filter=PublicFlowsHostScopeTest
vendor/bin/sail artisan test --filter=EnrollmentManagementTest
vendor/bin/sail dusk --filter=StudentInvitationFinalizeDuskTest
```

Every Dusk run in this module needs a fresh `vendor/bin/sail npm run
build` first if any `resources/js/` file changed: a stale `public/build`
makes screens look broken while the source is already correct.

## Diagnosing "Redemption Unexpectedly 404s"

The typed reason is in the rendered page (or the JSON `message`); work
backwards from it:

- `Este convite não foi encontrado.` on a token you KNOW exists → host
  mismatch (`org_id` vs `OrgContext`) reads as not-found by design, or
  the fixture's `org_id` doesn't match the host the request hit. Feature
  tests hitting the public routes must `$this->onHost($org->host)` — on
  the default `localhost` state-zero host `EnsureTenantAccess` redirects
  (302) before the controller ever runs.
- `Este convite já foi utilizado.` when you expected a fresh form → the
  fixture (or a previous test in the same file) already redeemed it;
  factory states `used()`/`revoked()`/`expired()` exist for a reason.
- `Esta conta está inativa...` → the credential was deactivated by a
  Gestor, not pending. `pending` redeems, `inactive` does not — that
  distinction is the feature, do not "fix" it by treating them equal.
- 404 on a link whose person has a staff role → correct; the Action
  refuses to reset staff passwords through tokens.

## Diagnosing "Copy/Renew Button Does Nothing"

- The copy handlers bind on `DOMContentLoaded` via
  `[data-issue-invitation]` (fetch + clipboard) and `[data-copy-link]`
  (flash banners). If a toast never appears, check `browser-logs` first:
  a 403 there is almost always the `{user}` implicit-binding trap (see
  `invitations-conventions` — the controller parameter MUST be named
  `$user` to match the `{user}` placeholder, otherwise the Policy gets an
  empty `User` and 403s).
- Clipboard uses `navigator.clipboard` only in secure contexts; on HTTP
  hosts the `copyInvitationText()` fallback (`execCommand('copy')`) runs
  instead. If the success toast shows but the clipboard is empty on
  HTTP, that is the browser refusing programmatic copy without user
  gesture/permission — not a code bug.
- "Renovar" is a declarative `x-ui.confirm-modal` (not `window.confirm`
  — this Dusk version has no dialog API); the modal form POSTs
  `gestor.students.invitations.regenerate` and redirects back, so the
  new link arrives in the `invitation_url` flash banner
  (`@invitation-flash`), never as JSON.

## Diagnosing a Failing `StudentInvitationFinalizeDuskTest`

- Inherits `DatabaseTruncation` from `Tests\DuskTestCase`, declares no
  DB trait of its own. `RefreshDatabase` forbidden (Dusk runs a browser
  session against a **separate HTTP process** — see
  `laravel-dusk`/`testing-architecture`).
- `@student-row-{id}` never appears on the students index → the fixture
  Aluno has no live `course_user` row: `GestorStudentController::index`
  lists only Alunos enrolled in an own-org course. Attach them to a
  course in the fixture.
- `assertAttribute('@invitation-email', 'readonly', 'true')` — this Dusk
  has no `assertReadonly()`; assert the raw attribute (W3C returns
  `'true'` for boolean attributes).
- The finalize `press('Finalizar cadastro')` → `waitForLocation('/meus-cursos')`
  assumes redemption succeeded; if it times out, POST the token via a
  Feature test first to surface the validation error (consent unchecked
  and password mismatch are the usual suspects).

## `Course::factory()` Defaults To `is_published: false` — Still Relevant For Enrollment Fixtures

`CourseFactory::definition()` sets `is_published => false` by default
(see `courses-conventions`). Course availability no longer gates
invitation redemption (the token finalizes an account, it does not
enroll), but classroom access is still enrollment-gated, so any Dusk
journey that ends on `/meus-cursos` and asserts the course title must
create the course `published()`.

## Auto-Update Protocol

Any change to `InvitationController`/`StudentInvitationController`/
`EnrollmentController`, `RedeemStudentInvitationAction`,
`StudentInvitation::unusableReason()`/`isUsable()`,
`App\Exceptions\InvitationInvalidException` (or its `bootstrap/app.php`
render hook), `FinalizeStudentInvitationRequest`, the `convite*`/
`gestor.students.invitations*`/`courses.enrollments*` routes, Blade views
under `resources/views/convite/`+
`resources/views/gestor/students/`+
`resources/views/courses/enrollments/`, or the clipboard/copy scripts in
those views **must** update all three invitations skills
(`invitations-architecture`, `invitations-conventions`,
`invitations-maintenance`) in same change, before task counts as done.
Also re-check:

- `.agents/agents/code-reviewer.md` — if change affects what reviewer
  must check for this module.
- Run `vendor/bin/sail artisan harness:check-skills` — fails build if any
  of three `invitations-*` skills is missing.

## Related

- `courses-maintenance` — analogous module this one mirrors (enrollment
  panel + pivot revocation semantics).
- `tenancy-maintenance` — underlying `OrgScope` contract this module
  builds on.

---

## E2E Coverage Lives in Lifecycle Chains, Not in a Per-Module File

Browser tests in `tests/Browser/` grouped by **user journey (lifecycle
chain)** — one method drives create, edit, state change, delete,
consequence — **not** by module or feature. Consequences when
maintaining this module:

- **Finding coverage**: Dusk scenarios listed above may be asserted as
  numbered steps inside chain method, possibly in file named after another
  module when journey crosses module boundaries. Locate them with `grep -rn
  "<route name|dusk selector>" tests/Browser/`, not by file name. Missing
  per-module file is **not** coverage gap.
- **Adding coverage**: extend existing chain for that journey with new
  numbered step carrying its own UI **and** DB assertion. New method only
  for independent negatives (403, cross-tenant, other actor); new file only
  for genuinely new journey.
- **Debugging a failure**: stack trace points at step, not whole scenario —
  match line to its `// N.` comment. Late failure usually means earlier step
  did not persist what it should.
- **Database**: no DB trait declared in `tests/Browser/*`;
  `DatabaseTruncation` inherited from `Tests\DuskTestCase`. Re-adding
  `DatabaseMigrations` is suite-wide performance regression. Files, cache
  and session **not** reset between methods.

Full rule: `testing-conventions`. Chain debugging: `testing-maintenance`.
