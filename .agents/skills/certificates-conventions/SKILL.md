---
name: certificates-conventions
description: >
  Concrete code patterns, snippets, and guardrails for the Certificates
  & Public Verification feature (SPEC-09): the exact SHA-256 hash formula
  (fixed Carbon format string), route names/contracts between the
  Domain/HTTP/View buckets, `CertificatePolicy` cascade-authorize
  conventions, `CertificatePdfService`'s dompdf-safe Blade template rules,
  the public-vs-staff view split, and the revoke-modal wiring pattern.
  Use whenever writing a controller, Policy, Form Request, Blade view, or
  JS that touches `Certificate`/`CourseCompletionRule` records, the PDF
  template, or the public verification page.
license: MIT
metadata:
  feature: certificates
  role: conventions
  specs:
    - spec/specs/09-certificates-and-public-verification.md
---

# Certificates Conventions

## The Validation Hash Formula Is Fixed — Never Recompute It Differently

SPEC-09's `sha256(user_id + course_id + formatted_issued_at + APP_KEY)`
does not pin an exact Carbon format string. The formula is fixed here so
it is computed identically everywhere it's ever needed (issuance,
verification, tests, future backfills):

```php
$hash = hash('sha256', $userId.$courseId.$issuedAt->format('Y-m-d H:i:s').config('app.key'));
```

- Concatenation order is `user_id`, then `course_id`, then
  `issued_at` formatted `Y-m-d H:i:s` (second-precision, no timezone
  suffix, no microseconds), then the raw `config('app.key')` string
  (including its `base64:` prefix if present — never decode it first).
- `$issuedAt` is the exact `Carbon` instance persisted to
  `certificates.issued_at`, not a freshly-called `now()` at verification
  time — the hash must be reproducible only from the row's own stored
  data plus `APP_KEY`, never from wall-clock time.
- If you ever need to *verify* a hash against a candidate row rather than
  looking it up by `validation_hash` directly (`certificates.verify`
  looks up by the column, so this rarely matters), recompute with this
  exact same call — do not introduce a second formula.

## Route Contract Between Buckets

```php
route('courses.certificates.index', $course);          // GET  courses/{course}/certificates    — role:admin|gestor
route('certificates.revoke', $certificate);             // PUT  certificates/{certificate}/revoke — role:admin|gestor
route('certificates.download', $certificate);           // GET  certificates/{certificate}/download — role:admin|gestor
route('certificates.verify', $certificate->validation_hash); // GET validar-certificado/{hash} — NO middleware at all
```

`certificates.verify`'s route parameter is the raw hash **string**, not a
route-model-bound `Certificate` — a bad/unknown hash must fall through to
`PublicCertificateController::show()`'s own explicit
`Certificate::where('validation_hash', $hash)->first()` (404 if `null`)
rather than relying on implicit binding's automatic 404, so the
found-but-revoked branch stays reachable in the same method.

## `CertificatePolicy` Cascade-Authorizes Through `Course`, Bypassing `OrgScope`

Mirrors `CoursePolicy`/`QuizPolicy`'s pattern one hop further, but must
explicitly re-load the Course unscoped since `Certificate` has no scope of
its own to lean on:

```php
public function revoke(User $user, Certificate $certificate): bool
{
    if ($user->hasRole(RolesEnum::ADMIN->value)) {
        return true;
    }

    if (! $user->hasRole(RolesEnum::GESTOR->value)) {
        return false;
    }

    $course = $certificate->course()->withoutGlobalScopes()->firstOrFail();

    return $course->org_id === $user->org_id;
}
```

`RevokeCertificateRequest::authorize()` calls
`Gate::allows('revoke', $this->route('certificate'))` — never re-implement
the org check inline in the Request or Controller.

## `revoke_reason`: Validate at Both the HTTP and Action Layers

`RevokeCertificateRequest` enforces `required|string|min:10|max:500`, but
`RevokeCertificateAction::execute()` must **defensively re-check** the
same min:10 rule (throwing an `InvalidArgumentException` or similar) since
it is a plain Action class callable from anywhere (a future Artisan
command, a queued job) that bypasses the HTTP validation layer entirely.
Never rely solely on the Form Request.

## Views: Staff (`layouts.app`) vs. Fully Public (Standalone Document)

- `certificates/index.blade.php` — `@extends('layouts.app')`, mirrors
  `courses/index.blade.php`'s `<x-ui.table>` + per-row `dusk="..."`
  convention. One `<x-ui.modal id="revoke-modal-{{ $certificate->id }}">`
  per **active** (non-revoked) certificate row — never a single shared
  modal re-populated by JS, matching `quizzes/edit.blade.php`'s
  per-question-modal pattern. Revoked rows show no revoke trigger.
- `public/certificates/show.blade.php` — **not** `layouts.app` (no
  session) and **not** `layouts.guest` either (that layout's left panel is
  themed around login copy, wrong fit for an audit page) — a standalone
  `<!DOCTYPE html>` document that still pulls in
  `@vite(['resources/css/app.css', 'resources/js/app.js'])` for the shared
  design tokens/CSS variables. Always renders both the "Válido"/"Revogado"
  banner **and** the full student/course/org/workload/issued_at block —
  the revoked state never hides the original data (SPEC-09 §2).
- `certificates/pdf.blade.php` — rendered by dompdf, which only supports a
  restricted CSS subset: no CSS custom properties (`var(--color-*)`), no
  modern flexbox/grid. Use plain hex colors and `<table>`-based layout,
  never `@vite`/the app's compiled stylesheet.

## Revoke Modal Wiring: Inline `@push('scripts')`, Not a New Vite Entry

`vite.config.js` only declares `resources/js/app.js` as a build input —
adding a `resources/js/certificates.js` Vite entry would require editing
`vite.config.js` (a dependency/build-config change outside this feature's
scope). The reason textarea/submit wiring is instead a plain inline
`<script>` pushed onto `@stack('scripts')` (declared in `layouts.app`)
from `certificates/index.blade.php` itself, scoped by a `data-revoke-form`
attribute per modal (there can be many, one per row):

```js
document.querySelectorAll('[data-revoke-form]').forEach((form) => {
    const textarea = form.querySelector('[data-revoke-reason]');
    const submit = form.querySelector('[data-revoke-submit]');
    const toggle = () => { submit.disabled = textarea.value.trim().length < 10; };
    textarea.addEventListener('input', toggle);
    toggle();
});
```

This is UX-only — `RevokeCertificateRequest`'s `min:10` server-side rule
is the actual authority. Modal open/close itself reuses the existing
`data-modal-target="revoke-modal-{{ $certificate->id }}"` /
`data-modal-dismiss="true"` attributes that `window.ModalManager`
(registered once in `app.js`) already binds globally — do not write a
second modal-open/close implementation.

## The QR Code Is a Pending Dependency Decision

No QR-code composer package is installed (`barryvdh/laravel-dompdf` only).
`certificates/pdf.blade.php` accepts a nullable `$qrCodeDataUri`
(`data:image/png;base64,...`) and degrades to printing the verification
URL + hash as plain text when it is `null` — this is a **temporary**
placeholder, not spec-compliant on its own (SPEC-09 §2 requires a real
scannable QR code), pending approval to add a package (see
`certificates-maintenance`'s open-questions note). Once a package is
approved, `CertificatePdfService::generate()` should populate
`$qrCodeDataUri` and this degraded branch becomes dead code you can
delete — do not build further features around the text-only fallback.
