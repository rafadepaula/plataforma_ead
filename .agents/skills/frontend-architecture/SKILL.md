---
name: frontend-architecture
description: Visão geral, schemas, componentes Blade e módulos JavaScript SOLID do frontend da Plataforma EAD.
---

# Frontend Architecture (`frontend-architecture`)

## Visão Geral

Frontend = camadas com micro-componentes Blade + **Bootstrap 5.3 (só grid/utilities)** + **Modernist Design System** (Claude Design). Comportamento dinâmico e chamada remota ficam desacoplados em **módulos JavaScript SOLID** em `resources/js/modules/`.

---

## Componentes

### 1. Master layouts (`resources/views/layouts/`)
- `app.blade.php`: área autenticada (Aluno, Admin, Gestor).
- `guest.blade.php`: split-screen 42%/58% para páginas públicas, auth e aceite de convite.

### 2. Submódulos estruturais (`resources/views/components/layout/`)
- `<x-layout.topbar>`: perfil, troca de tenant/impersonate, busca, notificações.
- `<x-layout.sidebar>`: menu lateral escuro (`--color-neutral-900`), filtrado por Spatie Roles (`role:admin`, `role:gestor`, `role:aluno`).
- `<x-layout.footer>`: rodapé institucional.
- `<x-layout.alerts>`: flash messages do Laravel + toasts.

### 3. Micro-componentes UI (`resources/views/components/ui/`)
- `<x-ui.button>`: label alinhado à esquerda, ícone SVG inline (`.btn-primary`, `.btn-secondary`, `.btn-ghost`).
- `<x-ui.card>`: `.card` com slot de imagem grayscale, kicker, título, meta, elevação (`.elev-sm`, `.elev-md`, `.elev-lg`).
- `<x-ui.modal>`: `.dialog` + `.dialog-backdrop`, `aria-modal="true"`, fecha por backdrop/Esc.
- `<x-ui.badge>`: status semântico (`.tag-accent`, `.tag-outline`, `.tag-neutral`, `.tag-accent-2`). Substitui badge do Bootstrap.
- `<x-ui.input>`: `.field > label + .input`.
- `<x-ui.select>`: select nativo, chevron SVG absoluto, `appearance: none`.
- `<x-ui.table>`: `.table` responsiva, header uppercase, hover tint.
- `<x-ui.stat-card>`: métrica do dashboard admin com delta percentual.
- `<x-ui.icon>`: ícone Lucide SVG inline via `@include`.

### 4. Módulos JavaScript SOLID (`resources/js/modules/`)
- `HttpClient.js`: singleton sobre `fetch`. Injeta `X-CSRF-TOKEN`, headers JSON (`Accept`/`Content-Type`), parsing padronizado de erro HTTP.
- `ModalManager.js`: modais orientado a objeto. Backdrop, `Escape`, foco automático, binding por `data-modal-target` / `data-modal-dismiss`.
- `NotificationService.js`: toasts. Injeta `#notification-container`, timer de autodescarte, animação.

---

## Build de Assets

```
resources/css/app.css  -\
                        +--> Vite (npm run build) --> public/build/
resources/js/app.js   -/
```

- **Bootstrap 5.3**: importado no topo do CSS. **Só** `@import 'bootstrap/scss/grid'` e `@import 'bootstrap/scss/utilities'`.
- **Tokens Modernist**: custom properties em `:root`. Sobrescrevem qualquer base.
