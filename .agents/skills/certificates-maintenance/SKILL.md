---
name: certificates-maintenance
description: >
  Debugging, testing, and edge-case guide for the Certificates & Public
  Verification feature (SPEC-09): the mandatory PHPUnit/Dusk test files,
  common eligibility/idempotency/revocation failure modes, the
  never-404-a-revoked-hash contract, cross-org PDF/organization resolution
  gotchas, and the open QR-code dependency question. Use when
  `CertificateEligibilityTest`, `CertificateRevocationTest`, or
  `PublicVerificationTest` is failing; a certificate isn't issued after a
  course completes; the public page 404s when it shouldn't (or vice
  versa); or the PDF/QR pipeline needs finishing.
license: MIT
metadata:
  feature: certificates
  role: maintenance
  specs:
    - spec/specs/09-certificates-and-public-verification.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Certificates Maintenance

## Mandatory Test Coverage for This Module

These tests guard the SPEC-09 contract and must stay green (PHPUnit, no
Pest):

- `tests/Feature/CertificateEligibilityTest.php` (Bucket A) — the 3
  `rule_type`s individually, the multi-rule AND case, idempotency
  (no re-issue on a re-fired `CourseCompletedByStudent`), no-reissue-after
  -revoke, and the no-rules-defined edge case (no-op, no certificate).
- `tests/Feature/CertificateRevocationTest.php` (Bucket A) — Gestor
  own-org success, Gestor other-org 403, Admin any-org success,
  `revoke_reason` `min:10` validation, the revoked row is never hard/soft
  deleted.
- `tests/Feature/PublicVerificationTest.php` (Bucket B) — valid
  certificate shows student/course/org/workload/issued_at + "Válido",
  revoked certificate returns `200` with the revoked banner + reason
  (never 404), unknown hash 404s, cross-org access works with zero
  auth/tenant scoping required.
- `tests/Browser/CertificateVerificationTest.php` (this bucket, Dusk E2E)
  — visits `/validar-certificado/{hash}` for both a valid and a revoked
  certificate and asserts the visible banner/data; a genuinely-unknown
  hash renders a 404 page.
- `tests/Browser/CertificateRevocationTest.php` (this bucket, Dusk E2E) —
  a Gestor revokes a certificate via `courses/{course}/certificates`'s UI,
  the reason textarea's client-side `min:10` gate, and the resulting
  public page reflecting the revoked state; a Gestor from another Org
  gets a 403 on the list itself.

Run the narrowest of these first after touching this module:

```bash
vendor/bin/sail artisan test --filter=CertificateEligibilityTest
vendor/bin/sail artisan test --filter=CertificateRevocationTest
vendor/bin/sail artisan test --filter=PublicVerificationTest
vendor/bin/sail dusk --filter=CertificateVerificationTest
vendor/bin/sail dusk --filter=CertificateRevocationTest
```

Dusk tests use `DatabaseMigrations`, never `RefreshDatabase` (Dusk runs in
a separate HTTP process against the same DB connection) — see
`laravel-dusk`. They also seed `Certificate` rows directly with
`Certificate::create([...])` rather than a `CertificateFactory` state
helper where the exact `validation_hash` needs to match the formula in
`certificates-conventions` byte-for-byte (a factory's `afterMaking` hook
computing the hash from a *different* field-write order would silently
desync from production code — recompute inline in the test instead).

## Common Failure Modes

- **Certificate never issued after a course completes.** Check (in
  order): does the course have *any* `course_completion_rules` rows at
  all (zero rows = intentional no-op, not a bug)? Is `min_quiz_score`'s
  `target_id` actually pointing at the Quiz the student attempted (a
  copy-paste'd wrong `target_id` fails silently, treated as "not
  satisfied", not an error)? Is `QUEUE_CONNECTION=sync` in this
  environment (an async queue would defer the Listener past the test's
  assertion point)?
- **Duplicate-key `QueryException` on re-completion.** `IssueCertificateAction`
  must guard with `firstOrCreate`/an existence check, not a bare
  `Certificate::create()` — see `certificates-architecture`'s idempotency
  section. If this ever surfaces as an uncaught exception in production
  logs, the guard regressed.
- **Public page 404s for a revoked certificate (or vice versa: a
  never-existed hash renders the certificate view).** These are two
  distinct branches in `PublicCertificateController::show()` — a bad merge
  can accidentally collapse "row not found" and "row found but revoked"
  into the same 404, or route-model-bind `{hash}` onto `Certificate`
  directly (which 404s on revoked rows too, since implicit binding has no
  concept of a soft "still show it" state). Always look up
  `validation_hash` explicitly and branch on `is null` vs.
  `->isRevoked()`.
- **PDF/public page shows the wrong Organization (or throws) for a
  Gestor viewing from a different `active_org_id` session, or a fully
  anonymous visitor.** `Course` has `OrgScope`; both
  `CertificatePdfService` and `PublicCertificateController` must resolve
  `$certificate->course` (and its `organization`) with
  `Course::withoutGlobalScopes()`/an equivalent unscoped query — a bare
  `$certificate->course` access through Eloquent's normal query builder
  will silently apply the *viewer's* org scope and can return `null`/wrong
  Course. See `tenancy-architecture` for the general pattern.
- **Revoke button still shows (or 500s) on an already-revoked
  certificate.** `certificates/index.blade.php` wraps the revoke trigger
  and its per-row modal in `@unless($certificate->isRevoked())` — if a
  new state gets added to the row later, keep that same guard rather than
  relying on the controller to reject a second revoke (it should still
  reject it too, defense in depth, but the UI shouldn't offer the action
  at all).

## Open Question: QR-Code Composer Package

No QR-code generation package is installed — only
`barryvdh/laravel-dompdf`. `certificates/pdf.blade.php` currently degrades
to a text-only verification URL + hash when `$qrCodeDataUri` is `null`
(see `certificates-conventions`). This is **not** spec-compliant on its
own — SPEC-09 §2 requires an actual scannable QR image. Adding a package
(`endroid/qr-code`, `simple-qrcode`, or `bacon/bacon-qr-code` are the
usual Laravel-ecosystem choices) requires explicit user approval per this
project's "no dependency changes without approval" rule (project
`CLAUDE.md`) — confirm which package before wiring
`CertificatePdfService` to actually populate `$qrCodeDataUri`.
