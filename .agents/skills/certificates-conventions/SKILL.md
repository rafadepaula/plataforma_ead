---
name: certificates-conventions
description: >
  Concrete code patterns, snippets, guardrails for Certificates
  & Public Verification feature: exact SHA-256 hash formula
  (fixed Carbon format string), route names/contracts between
  Domain/HTTP/View buckets, `CertificatePolicy` cascade-authorize
  conventions, `CertificatePdfService` dompdf-safe Blade template rules,
  public-vs-staff view split, revoke-modal wiring pattern.
  Use whenever write controller, Policy, Form Request, Blade view, or
  JS touching `Certificate`/`CourseCompletionRule` records, PDF
  template, or public verification page.
license: MIT
metadata:
  feature: certificates
  role: conventions
---

# Certificates Conventions

## The Validation Hash Formula Is Fixed — Never Recompute It Differently

The `sha256(user_id + course_id + formatted_issued_at + APP_KEY)` formula
does not pin an exact Carbon format string. Formula fixed here so it computes
identically everywhere needed (issuance, verification, tests, future
backfills):

```php
$hash = hash('sha256', $userId.$courseId.$issuedAt->format('Y-m-d H:i:s').config('app.key'));
```

- Concatenation order: `user_id`, then `course_id`, then `issued_at`
  formatted `Y-m-d H:i:s` (second-precision, no timezone suffix, no
  microseconds), then raw `config('app.key')` string (including its
  `base64:` prefix if present — never decode first).
- `$issuedAt` is exact `Carbon` instance persisted to
  `certificates.issued_at`, not fresh `now()` at verification time. Hash
  must be reproducible only from row's own stored data plus `APP_KEY`,
  never from wall-clock time.
- If you ever need to *verify* hash against candidate row instead of
  looking it up by `validation_hash` directly (`certificates.verify` looks
  up by column, so this rarely matters), recompute with this exact same
  call. Do not introduce second formula.

## Route Contract Between Buckets

```php
route('courses.certificates.index', $course);          // GET  courses/{course}/certificates    — role:admin|gestor
route('certificates.revoke', $certificate);             // PUT  certificates/{certificate}/revoke — role:admin|gestor
route('certificates.download', $certificate);           // GET  certificates/{certificate}/download — role:admin|gestor
route('certificates.verify', $certificate->validation_hash); // GET validar-certificado/{hash?} — NO middleware at all
route('certificates.verify');                           // GET validar-certificado — same route, hash omitted: the public lookup form
```

The `{hash?}` parameter is **optional**: `route('certificates.verify')`
with no argument is a legitimate, first-class call site (the Landing Page
footer's `Validar certificado` link uses exactly that) and must keep
resolving. Never add a `->where('hash', ...)` constraint that would break
the hash-less form, and never link to a placeholder hash — `firstOrFail()`
404s any hash that was never issued.

`PublicCertificateController::show(Request $request, ?string $hash = null)`
resolves the hash from the path segment **or** from `?hash=` (what the
lookup form's `GET` submit produces); an empty/whitespace hash renders
`public/certificates/lookup.blade.php` instead of 404ing.

`certificates.verify` route parameter is raw hash **string**, not
route-model-bound `Certificate`. Bad/unknown hash must fall through to
`PublicCertificateController::show()`'s own explicit
`Certificate::where('validation_hash', $hash)->first()` (404 if `null`)
instead of relying on implicit binding's automatic 404, so found-but-revoked
branch stays reachable in same method.

## `CertificatePolicy` Cascade-Authorizes Through `Course`, Bypassing `OrgScope`

Mirrors `CoursePolicy`/`QuizPolicy` pattern one hop further, but must
re-load Course unscoped explicitly since `Certificate` has no scope of own
to lean on:

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
`Gate::allows('revoke', $this->route('certificate'))`. Never re-implement
org check inline in Request or Controller.

## `revoke_reason`: Validate at Both the HTTP and Action Layers

`RevokeCertificateRequest` enforces `required|string|min:10|max:500`. But
`RevokeCertificateAction::execute()` must **defensively re-check** same
min:10 rule (throwing `InvalidArgumentException` or similar), since it is
plain Action class callable from anywhere (future Artisan command, queued
job) that bypasses HTTP validation layer entirely. Never rely on Form
Request alone.

## Views: Staff (`layouts.app`) vs. Fully Public (Standalone Document)

- `certificates/index.blade.php` — `@extends('layouts.app')`, mirrors
  `courses/index.blade.php`'s `<x-ui.table>` + per-row `dusk="..."`
  convention. One `<x-ui.modal id="revoke-modal-{{ $certificate->id }}">`
  per **active** (non-revoked) certificate row — never single shared modal
  re-populated by JS, matching `quizzes/edit.blade.php`'s
  per-question-modal pattern. Revoked rows show no revoke trigger.
- `public/certificates/lookup.blade.php` — the hash-less branch of the
  same route: a `<x-layout.public>` + `.max-w-reading` page holding one
  `<x-ui.card>` with a plain `GET` form back to
  `route('certificates.verify')` (`dusk="certificate-lookup-form"`,
  `dusk="certificate-lookup-hash"`, `dusk="certificate-lookup-submit"` —
  all three frozen in `tests/fixtures/dusk-selectors-snapshot.json`). Like
  every other screen it mounts `<x-help-button key="certificates.verify" />`
  (the 100%-coverage rule; see `help-conventions`). Any change to
  `show.blade.php`'s public shell — layout wrapper, reading width, help key
  — must be mirrored here, and vice versa.
- `public/certificates/show.blade.php` — **not** `layouts.app` (no
  session) and **not** `layouts.guest` either (that layout's left panel is
  themed around login copy, wrong fit for audit page). Uses
  `<x-layout.public :title="...">` (shared standalone shell added in the
  Fase 7 public-pages redesign pass, also used by `landing/show.blade.php`
  — see `bootstrap-conventions` §2), wrapped further in `.max-w-reading`
  (760px column). The verdict is one `<x-ui.card>` holding an
  `.icon-circle-success`/`.icon-circle-critical` (from
  `resources/scss/components/_public-pages.scss`) instead of two separate
  `<x-ui.alert>` blocks — the three `dusk="certificate-valid-banner"` /
  `certificate-revoked-banner"` / `"certificate-revoke-reason"` attributes
  and their visible copy stayed put on the new markup. Always renders both
  "Válido"/"Revogado" state **and** full student/course/org/workload/issued_at
  block. Revoked state never hides original data. "Baixar PDF"
  only renders `@auth` — `certificates.download` stays `auth`-middleware +
  staff-or-owner gated server-side regardless (view-level `@auth` is UX only,
  never the authorization boundary).
- `certificates/pdf.blade.php` — `@extends('layouts.print')`, with its own
  literal-value `<style>` block in `@section('styles')`. Rendered by dompdf,
  which supports only a restricted CSS subset: no CSS custom properties
  (`var(--*)`), no modern flexbox/grid. Use plain hex colors and
  `<table>`-based layout, never `@vite`/app's compiled stylesheet. This is
  the project's single documented exception to the zero-`style=` rule (7
  inline `style=` attributes) — the inline-style regression test excludes it
  on purpose, so never "fix" it.

## Revoke Modal Wiring: Inline `@push('scripts')`, Not a New Vite Entry

`vite.config.js` declares only `resources/js/app.js` as build input.
Adding `resources/js/certificates.js` Vite entry would require editing
`vite.config.js` (dependency/build-config change outside this feature's
scope). Reason textarea/submit wiring is instead plain inline `<script>`
pushed onto `@stack('scripts')` (declared in `layouts.app`) from
`certificates/index.blade.php` itself, scoped by `data-revoke-form`
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

UX-only — `RevokeCertificateRequest`'s `min:10` server-side rule is actual
authority. Modal open/close itself is fully declarative since the
Bootstrap 5.3 migration: `data-bs-toggle="modal"` +
`data-bs-target="#revoke-modal-{{ $certificate->id }}"` on the trigger,
`data-bs-dismiss="modal"` to close. `ModalManager` no longer exists — do
not write second modal-open/close implementation.

## The QR Code Is a Pending Dependency Decision

No QR-code composer package installed (`barryvdh/laravel-dompdf` only).
`certificates/pdf.blade.php` accepts nullable `$qrCodeDataUri`
(`data:image/png;base64,...`) and degrades to printing verification URL +
hash as plain text when `null`. This is **temporary** placeholder, not
the final design (the intent is a real scannable QR code), pending
approval to add package (see `certificates-maintenance`
open-questions note). Once package approved,
`CertificatePdfService::generate()` should populate `$qrCodeDataUri` and
this degraded branch becomes dead code you can delete. Do not build further
features around text-only fallback.
