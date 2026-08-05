---
name: invitations-maintenance
description: >
  Debugging, testing, and edge-case guide for the Smart Invitation &
  Enrollment feature (SPEC-06): the convite/show.blade.php adaptive form,
  the SmartInvitationForm.js module, and the mandatory PHPUnit/Dusk test
  files. Use when SmartInvitationTest, EnrollmentManagementTest,
  ProcessSmartInvitationActionTest, or MultiOrgEnrollmentTest is failing;
  the adaptive form isn't collapsing to password-only; or a multi-org Dusk
  assertion can't see the "other" Organization's data.
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

These tests guard the SPEC-06 contract and must stay green (PHPUnit, no
Pest):

- `tests/Unit/Actions/ProcessSmartInvitationActionTest.php` — transaction
  -level coverage of `ProcessSmartInvitationAction`: new/existing-account
  branches, wrong password, expired/exhausted/revoked/unknown-token/
  unpublished/soft-deleted-course link states, staff-account
  (gestor/admin) rejection, the RN09 no-duplicate-account/no-`org_id`
  -overwrite guarantee, reactivating a `cancelled` enrollment, and the
  `lockForUpdate` over-consumption guard.
- `tests/Feature/SmartInvitationTest.php` — HTTP-level coverage of the
  public `/convite/{token}` + `/convite/check-email` routes: link-state
  guards surfacing as 404s (including an unpublished/soft-deleted linked
  Course), the check-email JSON contract, and `store()`'s validation
  branching (new e-mail requires name/CPF/password-confirmation, existing
  e-mail requires only a matching password; a staff e-mail is rejected on
  `errors.email` regardless of password).
- `tests/Feature/EnrollmentManagementTest.php` — RF21's Gestor panel:
  manual enroll, revoke (`status = 'cancelled'`), reactivating a cancelled
  enrollment, the double-active-enrollment 422, and org-scoped 404/403 for
  a Gestor outside the Course's Organization.
- `tests/Browser/MultiOrgEnrollmentTest.php` — E2E: an existing multi-org
  user's e-mail collapses the form to password-only, and after submitting
  they land enrolled in both Organizations' courses with a single `users`
  row.

Run the narrowest of these first after touching this module:

```bash
vendor/bin/sail artisan test --filter=ProcessSmartInvitationActionTest
vendor/bin/sail artisan test --filter=SmartInvitationTest
vendor/bin/sail artisan test --filter=EnrollmentManagementTest
vendor/bin/sail dusk --filter=MultiOrgEnrollmentTest
```

## `SmartInvitationForm.js` — Contract With `convite/show.blade.php`

The module binds any `[data-check-email-url]` `<form>`, listening for
`blur`/debounced `input` on the `[data-invitation-email]` field inside it,
POSTing `{ email }` to that URL via the shared `HttpClient`, then toggling
every `[data-invitation-field="new-account"]` wrapper's visibility (and its
inner input's `required`-ness) from the `{ exists }` JSON response:

```js
async checkEmail(form, emailField) {
    const response = await this.httpClient.post(url, { email });
    const exists = Boolean(response.data && response.data.exists);
    this.toggleFields(form, exists);
}
```

If you add a new registration-only field to `convite/show.blade.php`, wrap
it in a `<div data-invitation-field="new-account">` exactly like
`name`/`cpf`/`password_confirmation` already are — `toggleFields()` finds
its inner `<input>`/`<select>`/`<textarea>` via `field.querySelector(...)`,
it does not target specific `name=` attributes. The `password` field
itself is intentionally **outside** any `new-account` wrapper: it's always
visible, since both branches (new and existing account) need it.

Registered in `resources/js/app.js` the same way `ModuleReorder`/
`CsvImporter` are:

```js
window.SmartInvitationForm = new SmartInvitationForm(HttpClient, NotificationService);
document.addEventListener('DOMContentLoaded', () => window.SmartInvitationForm.init());
```

## Diagnosing "Form Never Collapses to Password-Only"

- Confirm the `<form>` actually carries `data-check-email-url` — it's set
  server-side in `convite/show.blade.php` to `url('/convite/check-email')`.
  If it's missing/empty, `checkEmail()` silently no-ops
  (`if (!url || !email) { this.toggleFields(form, false); return; }`).
- Confirm the e-mail `<input>` carries the bare `data-invitation-email`
  attribute — `bindForm()` looks it up with `form.querySelector(
  '[data-invitation-email]')` and does nothing at all if it's absent.
- `toggleFields()` sets `field.style.display`, it does not add/remove any
  CSS class — if you're inspecting via devtools, check the inline `style`
  attribute, not a stylesheet rule.
- Dusk's `waitFor('@invitation-existing-account-hint')` (see
  `MultiOrgEnrollmentTest`) waits for the hint element to become
  *displayed*, not merely present — if the AJAX request 422s or 500s (e.g.
  `CheckInvitationEmailRequest`'s validation failing on a malformed
  e-mail), the hint never appears and the test times out on this line, not
  on the later `press()`. Check the request in `browser-logs`/network tab
  first, not the JS toggle logic, when this specific wait times out.

## Diagnosing a Failing `MultiOrgEnrollmentTest`

- Uses `DatabaseMigrations`, not `RefreshDatabase` (Dusk runs the browser
  session against a **separate HTTP process** — see `laravel-dusk`/
  `testing-architecture`), so cross-Org assertions after the browser block
  closes query the real, shared test database directly; no
  `withoutGlobalScopes()` trick is needed there since PHPUnit's assertion
  code has no authenticated-user session of its own applying `OrgScope` at
  all (only the app's own request handling inside the browser does).
- If the "single `users` row" assertion fails (a duplicate account was
  created instead of authenticating into the existing one), check the
  e-mail typed via `->type('@invitation-email', ...)` matches the seeded
  `User`'s `email` **exactly** — a trailing space or case mismatch makes
  `check-email` correctly report `exists: false`, and the test isn't
  wrong, the fixture data is.
- If `org_id` comes back overwritten instead of staying pinned to the
  original Org, the bug is in `ProcessSmartInvitationAction`'s
  existing-account branch — it must never assign `org_id` on that path
  (see `invitations-architecture`'s RN09 section); the new-account branch
  legitimately does.

## Diagnosing `EnrollmentController::destroy()` Not Revoking Anything

If a revoke request 200s/redirects but the `course_user` row's `status`
never actually changes to `cancelled`, check the route: `courses.
enrollments.destroy` **must** be the explicit two-segment
`courses/{course}/enrollments/{user}` route (see `invitations-conventions`),
not a `shallow()`-resource single-segment `{enrollment}` route — the
latter silently fails to bind either `Course $course` or `User $user` by
name (no route parameter is literally named `course` or `user`), so the
controller ends up mutating a query built from an empty, unsaved model
instead of throwing, and the `update`-existing-pivot call quietly matches
zero rows.

## `Course::factory()` Defaults To `is_published: false` — Invitation Test Fixtures Must Override It

`CourseFactory::definition()` sets `is_published => false` by default (see
`courses-conventions`). Since `InvitationLink::isUsable()`'s
`courseIsAvailable()` check now rejects a link whose Course isn't
published, any test building an `InvitationLink` off a plain
`Course::factory()->create(['org_id' => $org->id])` must add
`'is_published' => true` or the link will 404 as if it were expired —
this bit every pre-existing invitations test fixture the day this guard
was added, not just new ones. This is not a bug in the guard; it is
`CourseFactory`'s deliberate "an admin must explicitly publish" default
surfacing in a new place.

## Auto-Update Protocol (SPEC-03)

Per `spec/specs/03-agentic-harness-and-self-updating-skills.md`: any change
to `InvitationController`/`InvitationLinkController`/`EnrollmentController`,
`ProcessSmartInvitationAction`, the `convite*`/`courses.invitation-links*`/
`courses.enrollments*` routes, the Blade views under
`resources/views/convite/`+`resources/views/courses/invitation-links/`+
`resources/views/courses/enrollments/`, or `SmartInvitationForm.js` **must**
update all three invitations skills (`invitations-architecture`,
`invitations-conventions`, `invitations-maintenance`) in the same change,
before the task is considered done. Also re-check:

- `.agents/agents/code-reviewer.md` — if the change affects what a
  reviewer must check for this module.
- Run `vendor/bin/sail artisan harness:check-skills` — it fails the build
  if any of the three `invitations-*` skills is missing.

## Related Specs

- `spec/specs/06-smart-invitation-and-enrollment-system.md` — RF03, RF21,
  RN09.
- `courses-maintenance` — the analogous module this one mirrors (AJAX
  reorder vs. this module's AJAX check-email).
- `tenancy-maintenance` — the underlying `OrgScope` contract this module
  builds on.
