---
name: invitations-maintenance
description: >
  Per-student unique Invitation & enrollment: `student_invitations` schema, public
  unauthenticated `/convite/{token}` finalize flow, host-scoped redemption, identity
  bound to the token, pending→active credential lifecycle, typed
  InvitationInvalidException contract, `RedeemStudentInvitationAction` lockForUpdate
  transaction, get-or-create/rotate conventions. Use when designing or reviewing
  features touching `StudentInvitation` or `course_user` data, before adding a new
  enrollment/invitation endpoint, when writing controller/Form Request/Policy/Action
  for invitations, or when `StudentInvitationHttpTest`, `EnrollmentManagementTest`,
  `RedeemStudentInvitationActionTest` or `StudentInvitationFinalizeDuskTest` fails,
  the finalize form rejects a valid password, or a redemption unexpectedly 404s.
license: MIT
metadata:
  feature: invitations
  roles: [architecture, conventions, maintenance]
---

# Student Invitations & Enrollment (`invitations-maintenance`)

Per-student unique Invitation: `student_invitations` schema, public `/convite/{token}` finalize flow, host-scoped redemption (wrong host reads as 404), identity bound to the token, pending→active credential lifecycle, manual enrollment panel over `course_user`.

The detailed knowledge for this module lives in the reference files below
— read only the one the task needs:

| Reference | Read when |
| --- | --- |
| `resource/architecture.md` | Designing or reviewing features touching `StudentInvitation` or `course_user` data, before adding a new enrollment/invitation endpoint, or when deciding how the finalize-registration form behaves. |
| `resource/conventions.md` | Writing controller, Form Request, Policy, or Action managing `StudentInvitation` or `course_user` records, or wiring `/convite/{token}` endpoints. |
| `resource/maintenance.md` | `StudentInvitationHttpTest`, `EnrollmentManagementTest`, `RedeemStudentInvitationActionTest`, `StudentInvitationFinalizeDuskTest`, or `PublicFlowsHostScopeTest` fails; finalize form rejects a valid password; copy/renew button does nothing; a redemption unexpectedly 404s. |
