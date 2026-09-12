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
route('certificates.revoke', $certificate);            // POST certificates/{certificate}/revoke — role:admin|gestor
route('certificates.restore', $certificate);           // POST certificates/{certificate}/restore — role:admin|gestor (undo of revocation)
route('certificates.download', $certificate);           // GET  certificates/{certificate}/download — plain `auth` + staff-or-owner (`CertificateController::authorizeDownloadAccess`: owner-Aluno always, else role:admin|gestor with Gestor same-org check)
route('certificates.verify', $certificate->validation_hash); // GET validar-certificado/{hash?} — NO middleware at all; host-scoped (see below)
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

The verification itself is **host-scoped**: the controller loads Course
unscoped, then `abort(404)` when
`(int) $certificate->course->org_id !== (int) OrgContext::current()->orgId()`.
Wrong-host and never-issued hashes are indistinguishable 404s (see
`certificates-architecture`, and
`tests/Feature/Tenancy/PublicFlowsHostScopeTest.php`). Any new surface
linking to `certificates.verify` from queued mail must build the URL with
`OrgUrl::route($certificate->course->org_id, ...)` — `route()` alone
lands on `APP_URL`.

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

    return (int) OrgContext::current()->orgId() === (int) $course->org_id;
}
```

Comparison is against the **request host's** org (`OrgContext`), never a
`users.org_id` — that column no longer exists; a Gestor's org comes from
the host they are serving. `RevokeCertificateRequest::authorize()` calls
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
  `courses/index.blade.php`'s `<x-ui.data-table>` + per-row `dusk="..."`
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
  `<x-layout.public :title="...">` (shared standalone shell, also used by
  the tenant landing blades `resources/views/tenants/{landing_view}/landing.blade.php`
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
  the project's single documented exception to the zero-`style=` rule (9
  inline `style=` attributes) — the inline-style regression test excludes it
  on purpose, so never "fix" it.

## The Gestor's Students-Directory "Certificados" Panel Reuses the Same Routes

`gestor/students/index.blade.php` renders, per Aluno row, a "Certificados"
trigger (`dusk="student-certificates-{userId}"`) opening one
`x-ui.modal` per student (`dusk="certificates-modal-{userId}"`) listing
that student's certificates on own-org Courses only — `GestorStudentController::index()`
eager-loads `certificates` filtered by `whereHas('course', org_id)` with
`with('course')` (N+1-free; cascade-inherited tenancy means the filter is
explicit, never an `OrgScope`). Rows show curso, data de emissão, estado
(Válido/Revogado badge) and one action per state:

- Valid (non-revoked): "Invalidar" opens a reason-textarea modal
  (NOT `x-ui.confirm-modal` — revocation requires `revoke_reason`
  min:10, which the confirm-modal's footer form cannot carry), same
  inline `[data-revoke-form]` submit-toggle pattern as the per-course
  listing; POSTs to `certificates.revoke`.
- Revoked: "Validar" opens an `x-ui.confirm-modal` POSTing to
  `certificates.restore` (no reason involved).

Both modals live OUTSIDE the data table (responsive-wrapper clipping),
same as the directory's delete/renew modals. Flash copy from the shared
controller: "Certificado invalidado com sucesso." / "Certificado validado
com sucesso."; both actions `redirect()->back()` with a fallback to
`courses.certificates.index`, so the write path serves both screens
unchanged.

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

## The PDF Footer Uses a QR Code, Not a Raw Link

`chillerlan/php-qrcode` (v6) is installed. `CertificatePdfService::generate()`
builds the QR with `qrCodeDataUri()`: an SVG data URI
(`QRMarkupSVG::class`, `outputBase64` on — v6's `render()` already returns
the full `data:image/svg+xml;base64,...` string; ECC `L`, quiet zone on,
`scale` 10). The URL encoded is `OrgUrl::route($course->organization,
'certificates.verify', $hash)` — org host, APP_URL scheme/port — because
the PDF may be generated under a non-org request host and the verification
route is host-scoped. `certificates/pdf.blade.php` consumes it as a 30mm
`<img>` bottom-left with a tiny caption (`Valide a autenticidade` +
`$verificationHost`); the raw full URL is intentionally no longer printed.
Dompdf renders data-URI images reliably but distorts inline SVG — keep the
data-URI form. The 30mm footer raises the template's fixed-height bound:
`CertificatePresentationBuilder::BODY_FIXED_MM` is 101.0 (was 86.0); if
the footer changes size again, that constant must follow or the page
overflows to a second page.
