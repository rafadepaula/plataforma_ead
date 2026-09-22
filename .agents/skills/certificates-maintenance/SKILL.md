---
name: certificates-maintenance
description: >
  Certificates & Public Verification: `certificates`/`course_completion_rules` schema,
  cascade-inherited tenancy, `IssueCertificateAction` eligibility engine (AND across
  all 3 `rule_type`s), SHA-256 validation-hash formula, logical terminal revocation,
  SHA-256 route/Policy/PDF/QR conventions, never-404-a-revoked-hash contract. Use when
  designing or reviewing features touching `Certificate`/`CourseCompletionRule` data,
  before adding a new `rule_type`, when writing controller/Policy/Form Request/Blade
  view touching certificates or the public verification page, or when
  `CertificateEligibilityTest`, `CertificateRevocationTest` or `PublicVerificationTest`
  fail, a certificate is not issued after course completion, or the PDF/QR pipeline
  needs finishing.
license: MIT
metadata:
  feature: certificates
  roles: [architecture, conventions, maintenance]
---

# Certificates & Public Verification (`certificates-maintenance`)

Certificates & Public Verification: `certificates`/`course_completion_rules` schema, cascade-inherited (no `OrgScope`) tenancy, `IssueCertificateAction` eligibility engine, SHA-256 validation-hash formula, logical terminal revocation, PDF/QR pipeline and the public verification page.

The detailed knowledge for this module lives in the reference files below
— read only the one the task needs:

| Reference | Read when |
| --- | --- |
| `resource/architecture.md` | Designing or reviewing features touching `Certificate`/`CourseCompletionRule` data, before adding a new `rule_type`, or when deciding how the public verification route or PDF/QR pipeline gets scoped. |
| `resource/conventions.md` | Writing controller, Policy, Form Request, Blade view, or JS touching `Certificate`/`CourseCompletionRule` records, the PDF template, or the public verification page. |
| `resource/maintenance.md` | `CertificateEligibilityTest`, `CertificateRevocationTest`, or `PublicVerificationTest` fails; certificate not issued after course complete; public page 404 when it should not (or the reverse); PDF/QR pipeline needs finishing. |
