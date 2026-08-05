---
name: frontend-architecture
description: Visão Geral, Schemas, Estrutura de Componentes Blade e Módulos JavaScript SOLID do Frontend da Plataforma EAD.
---

# Frontend Architecture (`frontend-architecture`)

## Overview

O frontend da Plataforma EAD adota uma **Arquitetura em Camadas com Micro-Componentes Blade** combinada com **Bootstrap 5.3 (grid/utilities apenas)** e o **Modernist Design System** (Claude Design). O comportamento dinâmico e a comunicação remota são desacoplados através de **Módulos JavaScript SOLID** em `resources/js/modules/`.

---

## Componentes Arquiteturais

### 1. Camada de Master Layouts (`resources/views/layouts/`)
- `app.blade.php`: Layout master para áreas autenticadas (Aluno, Admin, Gestor).
- `guest.blade.php`: Layout mestre split-screen (42%/58%) para páginas públicas, autenticação e aceite de convites.

### 2. Submódulos Estruturais (`resources/views/components/layout/`)
- `<x-layout.topbar>`: Barra superior com perfil do usuário, chaveamento de tenant/impersonate, busca e notificações.
- `<x-layout.sidebar>`: Menu lateral dinâmico escuro (`--color-neutral-900`), configurado por permissões Spatie Roles (`role:admin`, `role:gestor`, `role:aluno`).
- `<x-layout.footer>`: Rodapé institucional.
- `<x-layout.alerts>`: Contêiner de mensagens flash do Laravel e toasts dinâmicos.

### 3. Micro-Componentes Blade UI (`resources/views/components/ui/`)
- `<x-ui.button>`: Botões com labels alinhados à esquerda e suporte a ícones inline SVG (`.btn-primary`, `.btn-secondary`, `.btn-ghost`).
- `<x-ui.card>`: Container modular `.card` com slot de imagem grayscale, kicker, título, meta e elevações (`.elev-sm`, `.elev-md`, `.elev-lg`).
- `<x-ui.modal>`: Diálogo acessível (`.dialog`) com backdrop (`.dialog-backdrop`), suporte `aria-modal="true"` e fechamento por backdrop/Esc.
- `<x-ui.badge>`: Tags de status semânticas (`.tag-accent`, `.tag-outline`, `.tag-neutral`, `.tag-accent-2`) substitutas do badge Bootstrap.
- `<x-ui.input>`: Campos de formulário estilizados (`.field > label + .input`).
- `<x-ui.select>`: Select nativo customizado com seta chevron SVG posicionada absolutamente (`appearance: none`).
- `<x-ui.table>`: Tabelas responsivas estilizadas (`.table`) com headers uppercase e hover tint.
- `<x-ui.stat-card>`: Cards de métricas do dashboard admin com deltas percentuais.
- `<x-ui.icon>`: Ícones Lucide SVG inline incorporados diretamente (`@include`).

### 4. Módulos JavaScript SOLID (`resources/js/modules/`)
- `HttpClient.js`: Singleton wrapper para `fetch` API com injeção automática do token CSRF (`X-CSRF-TOKEN`), tratamento de headers JSON (`Accept`/`Content-Type`) e parsing padronizado de erros HTTP.
- `ModalManager.js`: Gerenciador de modais orientado a objetos com controle de backdrop, navegação por teclado (`Escape`), foco automático e binding por atributos `data-modal-target` / `data-modal-dismiss`.
- `NotificationService.js`: Gerenciador de notificações toast com injeção dinâmica de contêiner `#notification-container`, temporizador de autodescarte e animações de transição.

---

## Compilação e Build de Assets

```
resources/css/app.css  -\
                        +--> Vite (npm run build) --> public/build/
resources/js/app.js   -/
```

- **Bootstrap 5.3**: Importado no topo do CSS incluindo **apenas** módulos `@import 'bootstrap/scss/grid'` e `@import 'bootstrap/scss/utilities'`.
- **Modernist Tokens**: Declarados em CSS custom properties (`:root`), sobrescrevendo qualquer estilo base.
