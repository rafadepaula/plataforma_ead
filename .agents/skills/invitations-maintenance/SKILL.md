---
name: invitations-maintenance
description: >
  Debug, test, edge-case guide for Smart Invitation & Enrollment feature:
  convite/show.blade.php adaptive form, SmartInvitationForm.js
  module (blur+debounced input, checkedEmail/sequence state, .d-none-only
  visibility), mandatory PHPUnit/Dusk test files. Use when SmartInvitationTest,
  EnrollmentManagementTest, ProcessSmartInvitationActionTest,
  SmartInvitationAdaptiveDuskTest, InvitationHttpTest or
  MultiOrgEnrollmentTest fails; adaptive form does not collapse to
  password-only; or multi-org Dusk assertion cannot see "other"
  Organization data.
license: MIT
metadata:
  feature: invitations
  role: maintenance
---

# Invitations Maintenance

## Mandatory Test Coverage for This Module

These tests guard this module's contract, must stay green (PHPUnit, no Pest):

- `tests/Unit/Actions/ProcessSmartInvitationActionTest.php` —
  transaction-level coverage of `ProcessSmartInvitationAction`:
  new/existing-account branches (existing = password checked against the
  host org's `credentials` row; person from another portal gets a new
  credential here), inactive-credential block after the password check,
  wrong password,
  expired/exhausted/revoked/unknown-token/unpublished/soft-deleted-course
  link states, wrong-host token reads as not-found, staff-account
  (gestor/admin) rejection, no second `users` row / no cross-org
  credential overwrite, reactivating
  `cancelled` enrollment, `lockForUpdate` over-consumption guard.
- `tests/Feature/Tenancy/PublicFlowsHostScopeTest.php` — host tenancy of
  the public flows: `/convite/{token}` 200 on the link org's host and 404
  (indistinguishable from unknown token) on another org's host,
  `check-email` answering `exists` from the host org's credential only.
- `tests/Feature/SmartInvitationTest.php` — HTTP-level coverage of public
  `/convite/{token}` + `/convite/check-email` routes: link-state guards
  surfacing as 404s (including unpublished/soft-deleted linked Course),
  check-email JSON contract, `store()` validation branching (new e-mail
  requires name/CPF/password-confirmation, existing e-mail requires only
  matching password; staff e-mail rejected on `errors.email` regardless of
  password).
- `tests/Feature/EnrollmentManagementTest.php` — Gestor enrollment panel: manual
  enroll, revoke (`status = 'cancelled'`), reactivating cancelled
  enrollment, double-active-enrollment 422, org-scoped 404/403 for Gestor
  outside Course Organization.
- `tests/Feature/InvitationHttpTest.php` — HTTP-level guards on the public
  routes not covered above, including the consent rejection wording
  (`É necessário concordar para concluir a matrícula.`).
- `tests/Browser/SmartInvitationAdaptiveDuskTest.php` — **DOM contract** of the
  adaptive form: new-account flow (a partial e-mail never toggles anything),
  existing-account collapse (verbatim hint text, `.d-none` on
  `[data-invitation-field="new-account"]`, `required` dropped from
  name/CPF/confirmation, no second `users` row), incremental typing flipping
  existing → new with `required` restored, consent blocked on both client
  (native `required`) and server (attribute stripped via `script()`), and the
  unusable-link screen with no `@invitation-form`.
- `tests/Browser/MultiOrgEnrollmentTest.php` — E2E **tenancy** journey (kept
  deliberately distinct from the DOM-contract suite above): existing multi-org
  user e-mail collapses form to password-only, and after submit they land
  enrolled in both Organizations' courses with one `users` row and one
  `credentials` row per org (`credentialFor($orgA)`/`credentialFor($orgB)`
  both non-null). Its
  invalid-link assertions target the per-reason copy
  (`Este convite expirou.` / `Este convite foi cancelado.` /
  `Limite de vagas atingido.`), not one catch-all sentence.

Run narrowest of these first after touching this module:

```bash
vendor/bin/sail artisan test --filter=ProcessSmartInvitationActionTest
vendor/bin/sail artisan test --filter=PublicFlowsHostScopeTest
vendor/bin/sail artisan test --filter=SmartInvitationTest
vendor/bin/sail artisan test --filter=EnrollmentManagementTest
vendor/bin/sail artisan test --filter=InvitationHttpTest
vendor/bin/sail dusk --filter=SmartInvitationAdaptiveDuskTest
vendor/bin/sail dusk --filter=MultiOrgEnrollmentTest
```

Every Dusk run in this module needs a fresh `vendor/bin/sail npm run build`
first: `SmartInvitationForm.js` is bundled, and a stale `public/build` makes the
form look broken while the source is already correct.

## `SmartInvitationForm.js` — Contract With `convite/show.blade.php`

Module binds any `[data-check-email-url]` `<form>`, listens for
`blur`/debounced `input` on `[data-invitation-email]` field inside it,
POSTs `{ email }` to that URL via shared `HttpClient`, then toggles every
`[data-invitation-field="new-account"]` wrapper visibility (and inner input
`required`-ness) from `{ exists }` JSON response:

```js
// per-form state, kept in a WeakMap: { checkedEmail, sequence }
if (state.checkedEmail === email) return;   // same address is never re-queried
state.checkedEmail = email;
const sequence = ++state.sequence;
const response = await this.httpClient.post(url, { email });
if (sequence !== state.sequence) return;    // stale response, discarded
this.toggleFields(form, Boolean(response.data && response.data.exists));
```

Three invariants of that state machine, each closing a bug that was real:

- **Both triggers stay.** `blur` fires immediately, `input` is debounced 400ms.
  The original design sketch said "blur only"; the shipped contract requires
  both — the
  `input` trigger is what makes the verdict follow incremental typing.
- **`checkedEmail` short-circuit.** Without it a pending debounced `input` could
  re-run `toggleFields` *after* the `blur` check had already collapsed the form,
  restoring `required` on a now-hidden field and silently blocking submit. This
  is the race the old `MultiOrgEnrollmentTest` papered over with `pause(700)`;
  both pauses are gone — do not reintroduce a pause instead of fixing state.
- **`sequence` guard.** Out-of-order responses are discarded, so the last
  address typed always wins.

An empty, partial or malformed e-mail (regex `/^[^\s@]+@[^\s@]+\.[^\s@]+$/`)
never reaches the server and never collapses anything: the form stays in the
new-account state. A network failure degrades to that same state **and** resets
`checkedEmail`, so the next `blur` can retry.

Add new registration-only field to `convite/show.blade.php`? Wrap it in
`<div data-invitation-field="new-account">` exactly like
`name`/`cpf`/`password_confirmation` already are. `toggleFields()` finds
inner `<input>`/`<select>`/`<textarea>` via `field.querySelector(...)`, it
does not target specific `name=` attributes. `password` field itself sits
intentionally **outside** any `new-account` wrapper: always visible, both
branches (new and existing account) need it.

Registered in the central module registry `resources/js/modules/index.js:53`
(no per-module `window.*` assignment, no per-module `DOMContentLoaded`
listener — `app.js` exposes each registry key as `window.<key>` and calls
`.init()` itself):

```js
SmartInvitationForm: new SmartInvitationForm(httpClient, notifications),
```

## Diagnosing "Form Never Collapses to Password-Only"

- Confirm `<form>` carries `data-check-email-url`. Set server-side in
  `convite/show.blade.php` to `route('invitation.check-email')`. Missing/empty,
  `checkEmail()` silently no-ops (`if (!url || !email) {
  this.toggleFields(form, false); return; }`).
- Confirm e-mail `<input>` carries bare `data-invitation-email` attribute.
  `bindForm()` looks it up with `form.querySelector(
  '[data-invitation-email], input[name="email"]')` (`SmartInvitationForm.js:49`)
  and does nothing at all if absent.
- `toggleFields()` toggles **only the `.d-none` class** (through
  `applyVisibility()`); it never writes `style.display` and never sets the
  `hidden` attribute — showing an element even clears a stray one left by other
  code. Inspecting via devtools, check the class list, not the inline `style`.
- The field never collapses for the *same* address twice: if you are manually
  re-triggering a check on an unchanged e-mail, nothing will happen by design
  (`checkedEmail` short-circuit). Change the value, or re-bind the form.
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
- `credentialFor($orgA)` (original portal account) comes back `null`, or a
  second row appeared in the *first* portal? Bug is in
  `ProcessSmartInvitationAction` existing-account branch — it must only
  ever touch the **link org's** `Credential` (`Credential::forOrg($invitationLink->org_id)`),
  never other orgs' rows, and never a `users.org_id` (column no longer
  exists). Creating the link org's credential for a person known only to
  another portal is the legitimate branch.

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

## Auto-Update Protocol

Any change
to `InvitationController`/`InvitationLinkController`/`EnrollmentController`,
`ProcessSmartInvitationAction`, `InvitationLink::unusableReason()`/`isUsable()`,
`App\Exceptions\InvitationLinkInvalidException` (or its `bootstrap/app.php`
render hook), `ProcessInvitationRequest`, the
`convite*`/`courses.invitation-links*`/
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

## Related

- `courses-maintenance` — analogous module this one mirrors (AJAX reorder
  vs this module AJAX check-email).
- `tenancy-maintenance` — underlying `OrgScope` contract this module builds
  on.

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
