# Frontend Legacy ("Modernist Design System", pre-migration — do not use)

> Historical reference only. The three former `frontend-*` skills described the
> pre-Bootstrap-5.3 "Modernist Design System", which **no longer exists in the
> code**. Do not follow any rule below in new code. Current frontend layers,
> tokens and `<x-ui.*>` components: `resource/architecture.md`. Current code
> rules: `resource/conventions.md`. UI troubleshooting: `resource/maintenance.md`.

What existed and where it went:

- `border-radius: 0px` mandatory — replaced by soft corners
  (`$border-radius: 14px`, pill buttons).
- `.grayscale` on every photo — class removed from the whole project in
  redesign Phase 2; media bands use `.ds-pastel-wash`.
- `<x-ui.badge variant="accent-2">` resolving to red (`text-bg-danger`) —
  now goes through `.ds-tone-critical` (`--critical-container`/
  `--on-critical-container`).
- `.dialog`/`.dialog-backdrop` + `ModalManager` — replaced by native
  bootstrap `.modal`/`.modal-backdrop` via `bootstrap.Modal`.
- `window.ModalManager`/hand-rolled `NotificationService` — replaced by
  `bootstrap.Modal`/`bootstrap.Toast`. (`NotificationService.success()/error()`
  survives only as a thin facade over `bootstrap.Toast`; it does not count as
  removed.)
- Phantom classes `.tag-*`, `.field`/`.input`, `.elev-*`, `.btn-ghost`,
  `.card` — never existed in a stylesheet or discontinued.

The complete old-to-new mapping lives in the Decision Record section of
`resource/architecture.md`.
