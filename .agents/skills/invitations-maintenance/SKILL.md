---
name: invitations-maintenance
description: >
  Debug, test, edge-case guide for Smart Invitation & Enrollment feature
  (SPEC-06): convite/show.blade.php adaptive form, SmartInvitationForm.js
  module, mandatory PHPUnit/Dusk test files. Use when SmartInvitationTest,
  EnrollmentManagementTest, ProcessSmartInvitationActionTest, or
  MultiOrgEnrollmentTest fails; adaptive form does not collapse to
  password-only; or multi-org Dusk assertion cannot see "other"
  Organization data.
license: MIT
metadata:
  feature: invitations
  role: maintenance
  specs:
    - spec/specs/06-smart-invitation-and-enrollment-system.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Invitations Maintenance

## Mandatory Test Coverage for This Module

These tests guard SPEC-06 contract, must stay green (PHPUnit, no Pest):

- `tests/Unit/Actions/ProcessSmartInvitationActionTest.php` —
  transaction-level coverage of `ProcessSmartInvitationAction`:
  new/existing-account branches, wrong password,
  expired/exhausted/revoked/unknown-token/unpublished/soft-deleted-course
  link states, staff-account (gestor/admin) rejection, RN09
  no-duplicate-account/no-`org_id`-overwrite guarantee, reactivating
  `cancelled` enrollment, `lockForUpdate` over-consumption guard.
- `tests/Feature/SmartInvitationTest.php` — HTTP-level coverage of public
  `/convite/{token}` + `/convite/check-email` routes: link-state guards
  surfacing as 404s (including unpublished/soft-deleted linked Course),
  check-email JSON contract, `store()` validation branching (new e-mail
  requires name/CPF/password-confirmation, existing e-mail requires only
  matching password; staff e-mail rejected on `errors.email` regardless of
  password).
- `tests/Feature/EnrollmentManagementTest.php` — RF21 Gestor panel: manual
  enroll, revoke (`status = 'cancelled'`), reactivating cancelled
  enrollment, double-active-enrollment 422, org-scoped 404/403 for Gestor
  outside Course Organization.
- `tests/Browser/MultiOrgEnrollmentTest.php` — E2E: existing multi-org user
  e-mail collapses form to password-only, and after submit they land
  enrolled in both Organizations' courses with single `users` row.

Run narrowest of these first after touching this module:

```bash
vendor/bin/sail artisan test --filter=ProcessSmartInvitationActionTest
vendor/bin/sail artisan test --filter=SmartInvitationTest
vendor/bin/sail artisan test --filter=EnrollmentManagementTest
vendor/bin/sail dusk --filter=MultiOrgEnrollmentTest
```

## `SmartInvitationForm.js` — Contract With `convite/show.blade.php`

Module binds any `[data-check-email-url]` `<form>`, listens for
`blur`/debounced `input` on `[data-invitation-email]` field inside it,
POSTs `{ email }` to that URL via shared `HttpClient`, then toggles every
`[data-invitation-field="new-account"]` wrapper visibility (and inner input
`required`-ness) from `{ exists }` JSON response:

```js
async checkEmail(form, emailField) {
    const response = await this.httpClient.post(url, { email });
    const exists = Boolean(response.data && response.data.exists);
    this.toggleFields(form, exists);
}
```

Add new registration-only field to `convite/show.blade.php`? Wrap it in
`<div data-invitation-field="new-account">` exactly like
`name`/`cpf`/`password_confirmation` already are. `toggleFields()` finds
inner `<input>`/`<select>`/`<textarea>` via `field.querySelector(...)`, it
does not target specific `name=` attributes. `password` field itself sits
intentionally **outside** any `new-account` wrapper: always visible, both
branches (new and existing account) need it.

Registered in `resources/js/app.js` same way `ModuleReorder`/`CsvImporter`
are:

```js
window.SmartInvitationForm = new SmartInvitationForm(HttpClient, NotificationService);
document.addEventListener('DOMContentLoaded', () => window.SmartInvitationForm.init());
```

## Diagnosing "Form Never Collapses to Password-Only"

- Confirm `<form>` carries `data-check-email-url`. Set server-side in
  `convite/show.blade.php` to `url('/convite/check-email')`. Missing/empty,
  `checkEmail()` silently no-ops (`if (!url || !email) {
  this.toggleFields(form, false); return; }`).
- Confirm e-mail `<input>` carries bare `data-invitation-email` attribute.
  `bindForm()` looks it up with `form.querySelector(
  '[data-invitation-email]')` and does nothing at all if absent.
- `toggleFields()` sets `field.style.display`, adds/removes no CSS class.
  Inspecting via devtools, check inline `style` attribute, not stylesheet
  rule.
- Dusk `waitFor('@invitation-existing-account-hint')` (see
  `MultiOrgEnrollmentTest`) waits for hint element to become *displayed*,
  not merely present. If AJAX request 422s or 500s (example:
  `CheckInvitationEmailRequest` validation failing on malformed e-mail),
  hint never appears and test times out on this line, not on later
  `press()`. Check request in `browser-logs`/network tab first, not JS
  toggle logic, when this specific wait times out.

## Diagnosing a Failing `MultiOrgEnrollmentTest`

- Inherits `DatabaseTruncation` from `Tests\DuskTestCase`, declares no DB
  trait of its own. `RefreshDatabase` forbidden here (Dusk runs browser
  session against **separate HTTP process** — see
  `laravel-dusk`/`testing-architecture`), so cross-Org assertions after
  browser block closes query real shared test database directly. No
  `withoutGlobalScopes()` trick needed there: PHPUnit assertion code has no
  authenticated-user session of its own applying `OrgScope` at all (only
  app request handling inside browser does).
- "Single `users` row" assertion fails (duplicate account created instead
  of authenticating into existing one)? Check e-mail typed via
  `->type('@invitation-email', ...)` matches seeded `User` `email`
  **exactly**. Trailing space or case mismatch makes `check-email`
  correctly report `exists: false`. Test not wrong, fixture data wrong.
- `org_id` comes back overwritten instead of pinned to original Org? Bug is
  in `ProcessSmartInvitationAction` existing-account branch — must never
  assign `org_id` on that path (see `invitations-architecture` RN09
  section). New-account branch legitimately does.

## Diagnosing `EnrollmentController::destroy()` Not Revoking Anything

Revoke request 200s/redirects but `course_user` row `status` never changes
to `cancelled`? Check route: `courses.enrollments.destroy` **must** be
explicit two-segment `courses/{course}/enrollments/{user}` route (see
`invitations-conventions`), not `shallow()`-resource single-segment
`{enrollment}` route. Latter silently fails to bind either `Course $course`
or `User $user` by name (no route parameter literally named `course` or
`user`), so controller mutates query built from empty unsaved model instead
of throwing, and `update`-existing-pivot call quietly matches zero rows.

## `Course::factory()` Defaults To `is_published: false` — Invitation Test Fixtures Must Override It

`CourseFactory::definition()` sets `is_published => false` by default (see
`courses-conventions`). `InvitationLink::isUsable()` `courseIsAvailable()`
check now rejects link whose Course is not published, so any test building
`InvitationLink` off plain `Course::factory()->create(['org_id' =>
$org->id])` must add `'is_published' => true` or link 404s as if expired.
This bit every pre-existing invitations test fixture day guard was added,
not just new ones. Not bug in guard. It is `CourseFactory` deliberate "admin
must explicitly publish" default surfacing in new place.

## Auto-Update Protocol (SPEC-03)

Per `spec/specs/03-agentic-harness-and-self-updating-skills.md`: any change
to `InvitationController`/`InvitationLinkController`/`EnrollmentController`,
`ProcessSmartInvitationAction`, the `convite*`/`courses.invitation-links*`/
`courses.enrollments*` routes, Blade views under
`resources/views/convite/`+`resources/views/courses/invitation-links/`+
`resources/views/courses/enrollments/`, or `SmartInvitationForm.js` **must**
update all three invitations skills (`invitations-architecture`,
`invitations-conventions`, `invitations-maintenance`) in same change, before
task counts as done. Also re-check:

- `.agents/agents/code-reviewer.md` — if change affects what reviewer must
  check for this module.
- Run `vendor/bin/sail artisan harness:check-skills` — fails build if any of
  three `invitations-*` skills is missing.

## Related Specs

- `spec/specs/06-smart-invitation-and-enrollment-system.md` — RF03, RF21,
  RN09.
- `courses-maintenance` — analogous module this one mirrors (AJAX reorder
  vs this module AJAX check-email).
- `tenancy-maintenance` — underlying `OrgScope` contract this module builds
  on.

---

## E2E Coverage Lives in Lifecycle Chains, Not in a Per-Module File

Browser tests in `tests/Browser/` grouped by **user journey (lifecycle
chain)** — one method drives create, edit, state change, delete,
consequence — **not** by module, spec, or use case. Consequences when
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
