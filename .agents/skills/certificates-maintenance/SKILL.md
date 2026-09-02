---
name: certificates-maintenance
description: >
  Debug, test, edge-case guide for Certificates & Public Verification:
  mandatory PHPUnit/Dusk test files, common
  eligibility/idempotency/revocation failure modes,
  never-404-a-revoked-hash contract, cross-org PDF/organization
  resolution gotchas, open QR-code dependency question. Use when
  `CertificateEligibilityTest`, `CertificateRevocationTest`, or
  `PublicVerificationTest` fail; certificate not issued after course
  complete; public page 404 when it should not (or reverse); or PDF/QR
  pipeline need finishing.
license: MIT
metadata:
  feature: certificates
  role: maintenance
---

# Certificates Maintenance

## Mandatory Test Coverage for This Module

Tests guard this module's contract. Must stay green (PHPUnit, no Pest):

- `tests/Feature/CertificateEligibilityTest.php` — 3
  `rule_type`s individually, multi-rule AND case, idempotency (no
  re-issue on re-fired `CourseCompletedByStudent`), no-reissue-after
  -revoke, no-rules-defined edge case (no-op, no certificate).
- `tests/Feature/CertificateRevocationTest.php` — Gestor
  own-org success, Gestor other-org 403, Admin any-org success,
  `revoke_reason` `min:10` validation, revoked row never hard/soft
  deleted.
- `tests/Feature/PublicVerificationTest.php` — valid
  certificate show student/course/org/workload/issued_at + "Válido",
  revoked certificate return `200` with revoked banner + reason (never
  404), unknown hash 404, cross-org access work with zero auth/tenant
  scoping required, plus the hash-less entry point: `/validar-certificado`
  render the lookup form, `?hash=` (valid / revoked / unknown / blank)
  behave exactly like the path segment.
- `tests/Browser/CertificateVerificationTest.php` (this bucket, Dusk E2E)
  — visit `/validar-certificado/{hash}` for both valid and revoked
  certificate, assert visible banner/data; genuinely-unknown hash render
  404 page. Also the lookup journey: Landing Page footer → `Validar
  certificado` → `/validar-certificado` form → typed hash → verification
  page (and a wrong typed hash → 404). That test is the only driver of the
  three `certificate-lookup-*` snapshot selectors — do not delete them.
- `tests/Browser/CertificateRevocationTest.php` (this bucket, Dusk E2E) —
  Gestor revoke certificate via `courses/{course}/certificates` UI,
  reason textarea client-side `min:10` gate, resulting public page
  reflect revoked state; Gestor from another Org get 403 on list itself.

Run narrowest first after touch module:

```bash
vendor/bin/sail artisan test --filter=CertificateEligibilityTest
vendor/bin/sail artisan test --filter=CertificateRevocationTest
vendor/bin/sail artisan test --filter=PublicVerificationTest
vendor/bin/sail dusk --filter=CertificateVerificationTest
vendor/bin/sail dusk --filter=CertificateRevocationTest
```

Dusk classes declare no DB trait — `DatabaseTruncation` inherited from
`Tests\DuskTestCase`; `RefreshDatabase` forbidden (Dusk run in separate
HTTP process); `DatabaseMigrations` retired (per-method `migrate:fresh`)
— see `laravel-dusk`/`testing-conventions`. They also seed `Certificate`
rows directly with `Certificate::create([...])` rather than
`CertificateFactory` state helper where exact `validation_hash` must
match formula in `certificates-conventions` byte-for-byte (factory
`afterMaking` hook computing hash from *different* field-write order
would silently desync from production code — recompute inline in test
instead).

## Common Failure Modes

- **Certificate never issued after course complete.** Check, in order:
  does course have *any* `course_completion_rules` rows at all (zero rows
  = intentional no-op, not bug)? Is `min_quiz_score` `target_id` actually
  pointing at Quiz student attempted (copy-paste'd wrong `target_id` fail
  silently, treated as "not satisfied", not error)? Is
  `QUEUE_CONNECTION=sync` in this environment (async queue would defer
  Listener past test assertion point)?
- **Duplicate-key `QueryException` on re-completion.**
  `IssueCertificateAction` must guard with `firstOrCreate`/existence
  check, not bare `Certificate::create()` — see
  `certificates-architecture` idempotency section. If this surface as
  uncaught exception in production logs, guard regressed.
- **Public page 404 for revoked certificate (or reverse: never-existed
  hash render certificate view).** Two distinct branches in
  `PublicCertificateController::show()` — bad merge can accidentally
  collapse "row not found" and "row found but revoked" into same 404, or
  route-model-bind `{hash?}` onto `Certificate` directly (which 404 on
  revoked rows too, since implicit binding have no concept of soft "still
  show it" state). Always look up `validation_hash` explicitly and branch
  on `is null` vs `->isRevoked()`. Third branch to keep intact: **no**
  hash at all (path segment omitted *or* blank `?hash=`) render
  `public.certificates.lookup`, never 404 — otherwise the Landing Page
  footer's only public-validation entry point dies.
- **PDF/public page show wrong Organization (or throw) for Gestor viewing
  from different `active_org_id` session, or fully anonymous visitor.**
  `Course` have `OrgScope`; both `CertificatePdfService` and
  `PublicCertificateController` must resolve `$certificate->course` (and
  its `organization`) with `Course::withoutGlobalScopes()`/equivalent
  unscoped query — bare `$certificate->course` access through Eloquent
  normal query builder silently apply *viewer* org scope, can return
  `null`/wrong Course. See `tenancy-architecture` for general pattern.
- **Revoke button still show (or 500) on already-revoked certificate.**
  `certificates/index.blade.php` wrap revoke trigger and its per-row
  modal in `@unless($certificate->isRevoked())` — if new state added to
  row later, keep same guard rather than rely on controller to reject
  second revoke (it should still reject too, defense in depth, but UI
  must not offer action at all).

## Open Question: QR-Code Composer Package

No QR-code generation package installed — only
`barryvdh/laravel-dompdf`. `certificates/pdf.blade.php` currently degrade
to text-only verification URL + hash when `$qrCodeDataUri` is `null` (see
`certificates-conventions`). This is **not** the intended end state on its own —
the screen contract requires an actual scannable QR image. Adding package
(`endroid/qr-code`, `simple-qrcode`, or `bacon/bacon-qr-code` are usual
Laravel-ecosystem choices) require explicit user approval per this
project "no dependency changes without approval" rule (project
`CLAUDE.md`) — confirm which package before wiring
`CertificatePdfService` to actually populate `$qrCodeDataUri`.

---

## E2E Coverage Lives in Lifecycle Chains, Not in a Per-Module File

Browser tests in `tests/Browser/` grouped by **user journey (lifecycle
chain)** — one method drive create → edit → state change → delete →
consequence — **not** by module or feature. Consequences when
maintain this module:

- **Finding coverage**: Dusk scenarios listed above may be asserted as
  numbered steps inside chain method, possibly in file named after
  another module when journey cross module boundaries. Locate with
  `grep -rn "<route name|dusk selector>" tests/Browser/`, not by file
  name. Missing per-module file is **not** coverage gap.
- **Adding coverage**: extend existing chain for that journey with new
  numbered step carrying own UI **and** DB assertion. New method only for
  independent negatives (403, cross-tenant, other actor); new file only
  for genuinely new journey.
- **Debugging failure**: stack trace point at step, not whole scenario —
  match line to its `// N.` comment. Late failure usually mean earlier
  step not persist what it should.
- **Database**: no DB trait declared in `tests/Browser/*`;
  `DatabaseTruncation` inherited from `Tests\DuskTestCase`. Re-adding
  `DatabaseMigrations` is suite-wide performance regression. Files, cache
  and session **not** reset between methods.

Full rule: `testing-conventions`. Chain debugging: `testing-maintenance`.
