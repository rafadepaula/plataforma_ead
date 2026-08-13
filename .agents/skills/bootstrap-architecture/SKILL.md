---
name: bootstrap-architecture
description: >
  Explains the Bootstrap 5.3 frontend architecture of the Plataforma EAD
  after the inline-style migration: the 5-layer model (SCSS token layer →
  Bootstrap core → project component layer → Blade wrapper components →
  screens), why Bootstrap SCSS variables (`$primary`, `$border-radius`,
  `$font-family-base`) replace the parallel `--color-*` custom property set
  as the single source of truth, the Bootstrap-JS-over-hand-rolled-JS
  principle that retires ModalManager/NotificationService/dropdown code,
  the componentization mandate (no raw Bootstrap markup in a screen), and
  the decision record of what was replaced by what. Use whenever designing
  or reviewing a screen, layout, Blade component or JS module that renders
  UI, before introducing a new CSS class or a new `<x-ui.*>` component, or
  when deciding where a given style rule belongs in the layer stack.
license: MIT
metadata:
  feature: bootstrap
  role: architecture
  specs:
    - spec/front_migration/01-current-state.md
    - spec/front_migration/06-skills-and-agents.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Bootstrap Architecture

## Overview

O frontend da Plataforma EAD é **Bootstrap 5.3 completo** (SCSS + JS), com o
**Modernist Design System** expresso como uma **customização do Bootstrap**, e não
como um sistema paralelo. Não existe mais "CSS do projeto que imita o Bootstrap":
existe Bootstrap configurado com os tokens da marca.

O princípio-mestre é: **uma decisão visual tem exatamente um lugar onde mora.**
Se um botão tem canto reto, isso está em `$border-radius: 0` — não em 634
atributos `style="border-radius: 0px"` espalhados por views.

---

## As 5 camadas

```
┌─ 5. Telas (resources/views/**/*.blade.php)
│     Só composição. Zero style=. Zero CSS novo. Só <x-ui.*>, <x-layout.*> e utilities.
├─ 4. Componentes Blade wrapper (resources/views/components/{ui,layout}/)
│     Encapsulam markup Bootstrap + ARIA + data-bs-*. Única camada que conhece
│     a estrutura interna do Bootstrap (.modal-dialog, .card-body, .form-control).
├─ 3. Camada de componentes do projeto (resources/scss/components/_*.scss)
│     Só o que o Bootstrap NÃO resolve: .sidebar, .grayscale, .stat-card,
│     .lesson-player, .topbar. Nunca reimplementa .btn/.card/.modal.
├─ 2. Bootstrap core (node_modules/bootstrap/scss/bootstrap.scss)
│     Importado por inteiro, já compilado com as variáveis da camada 1.
└─ 1. Camada de tokens SCSS (resources/scss/_variables.scss)
      A ÚNICA fonte de verdade do design. Sobrescreve as variáveis !default
      do Bootstrap ANTES do import do core.
```

Arquivo de entrada (`resources/scss/app.scss`), nesta ordem obrigatória:

```scss
// 1) Funções do Bootstrap (necessárias para tint/shade nas nossas variáveis)
@import "bootstrap/scss/functions";

// 2) Nossos tokens — sobrescrevem os !default do Bootstrap
@import "variables";

// 3) Bootstrap completo (variables, mixins, reboot, grid, componentes, utilities, helpers)
@import "bootstrap/scss/bootstrap";

// 4) Utilities extras derivadas dos nossos tokens (API de utilities do Bootstrap)
@import "utilities";

// 5) Camada de componentes do projeto — só o que o Bootstrap não cobre
@import "components/sidebar";
@import "components/topbar";
@import "components/stat-card";
@import "components/lesson-player";
@import "components/grayscale";

// 6) Fontes self-hosted (Archivo)
@import "fonts";
```

`vite.config.js` passa a apontar `resources/scss/app.scss` no lugar de
`resources/css/app.css`. `resources/css/app.css` é **removido** ao final da
migração (não fica como fallback: dois arquivos de estilo é exatamente o problema
que a migração elimina).

---

## Por que as variáveis SCSS do Bootstrap são a fonte de verdade

O estado pré-migração tinha **dois** sistemas de tokens vivendo lado a lado:

- `:root { --color-accent: #ec3013; --radius-sm: 0px; ... }` — consumido por 634
  atributos `style=` inline;
- as variáveis internas do Bootstrap (`$primary`, `$border-radius`,
  `$font-family-base`), que ninguém tinha configurado, porque só se importava o
  CSS já compilado de `bootstrap/dist/`.

Consequência: nenhum utility ou componente do Bootstrap "sabia" da marca. Para
ter um botão vermelho era preciso escrever o vermelho à mão, em cada botão.

Depois da migração, o fluxo é **um só**:

```scss
// resources/scss/_variables.scss  (camada 1)
$primary:   #ec3013;   // accent Modernist
$body-bg:   #f3f2f2;
$body-color:#201e1d;
$border-radius: 0;
$font-family-base: "Archivo", system-ui, sans-serif;
```

…e a partir daí `.btn-primary`, `.bg-primary`, `.text-primary`, `.border-primary`,
`.badge.text-bg-primary`, o foco `:focus-visible`, o `.form-control:focus` e o CSS
var `--bs-primary` — **todos** — já saem na cor certa, de graça, sem uma linha de
CSS do projeto.

As custom properties `--color-*` **não desaparecem**: elas passam a ser *derivadas*
(a camada 1 as reemite a partir das variáveis SCSS, para código legado e para o JS
que porventura leia `getComputedStyle`). Mas **elas deixam de ser a fonte**: quem
edita a marca edita `_variables.scss`, nunca o bloco `:root`.

Regra: **`--color-*` é output, `$variável` é input.** Uma PR que muda cor mexendo
em `:root` está errada por construção.

---

## Princípio: JS do Bootstrap acima de JS artesanal

Pré-migração havia 12 módulos JS em `resources/js/modules/`, três dos quais
reimplementavam à mão comportamentos que o Bootstrap já entrega:

| Módulo artesanal | Comportamento | Substituto Bootstrap |
| :--- | :--- | :--- |
| `ModalManager.js` | abrir/fechar, backdrop, `Escape`, foco, `data-modal-target` | `bootstrap.Modal` + `data-bs-toggle="modal"` / `data-bs-dismiss="modal"` |
| `NotificationService.js` | toasts, container dinâmico, auto-dismiss | `bootstrap.Toast` + `.toast-container` |
| dropdown do `<x-layout.topbar>` (inline) | abrir/fechar menu de perfil e de notificações | `bootstrap.Dropdown` (+ Popper, que vem no bundle) |
| diretivas Alpine órfãs em `<x-ui.modal>` | `x-data`/`x-show`/`@click.outside` **sem Alpine instalado** — inertes | `bootstrap.Modal` |

O princípio: **se o Bootstrap tem a API, o projeto não escreve a sua.** Motivos
concretos, não estéticos:

1. **Acessibilidade grátis** — focus trap, `aria-modal`, `aria-expanded`,
   restauração de foco e inertização do fundo já são corretos e testados no
   Bootstrap; a implementação artesanal cobria só parte disso.
2. **Empilhamento e z-index corretos** — backdrops múltiplos, `.modal-open` no
   `<body>`, compensação de scrollbar. `ModalManager` não tratava empilhamento.
3. **Superfície de manutenção** — ~10 KB de JS do projeto deixam de existir.
4. **Contrato estável para Dusk** — `data-bs-toggle` + classe `.show` são um
   contrato público e documentado, melhor alvo de `waitFor` do que estado interno
   de um objeto do projeto.

Os módulos que **permanecem** são os que carregam lógica de domínio, não de
widget: `HttpClient`, `CsvImporter`, `LessonPlayer`, `ModuleReorder`,
`SmartInvitationForm`, `QuizBuilder`, `QuizTimer`, `ForumPolling`,
`NotificationBell`, `ForumEditHistory`, `ForumReportModal`, `AuditLogDiffModal`.
Destes, os quatro últimos **deixam de instanciar `ModalManager`** e passam a usar
`bootstrap.Modal.getOrCreateInstance(el)` — a API pública de cada módulo
(`init()`, construtor com dependências injetadas, registro em `window.*`) é
preservada intacta, porque é contrato de `app.js` e das views.

Bundle: `resources/js/app.js` passa a começar com

```js
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;
```

Isto traz Popper embutido (o build `bootstrap.bundle` do npm resolve o Popper via
dependência), habilitando dropdowns, tooltips e popovers.

---

## Mandato de componentização

**Nenhuma tela escreve markup cru do Bootstrap.** Uma tela nunca contém
`<div class="modal fade" tabindex="-1">…</div>` nem `<nav class="navbar">…`.
Ela contém `<x-ui.modal>` e `<x-layout.topbar>`.

Por quê:

- **Um lugar para corrigir.** Quando o Bootstrap 5.4 mudar a estrutura interna do
  modal, muda-se `components/ui/modal.blade.php`, não 40 telas.
- **ARIA por construção.** Os atributos de acessibilidade vivem no wrapper; a tela
  não pode esquecê-los.
- **Contrato de teste.** O wrapper garante que todo modal tem
  `dusk="modal-{id}"` e todo botão de submit tem o `dusk` que a tela passou.
- **Detecção de deriva.** Se uma tela precisa de markup que nenhum componente
  expõe, isso é sinal de componente faltando — e a resposta certa é criar/estender
  o componente (via `bootstrap-component-author`), nunca inlinear o markup.

Exceção única e explícita: **utilities de layout** (`row`, `col-*`, `d-flex`,
`gap-*`, `mb-*`, `text-*`) são escritas diretamente na tela. Utility é vocabulário,
não estrutura.

---

## Decision record — o que substituiu o quê

| Antes (pré-migração) | Depois | Nota |
| :--- | :--- | :--- |
| `@import "bootstrap/dist/css/bootstrap-grid.min.css"` + `bootstrap-utilities.min.css` | `@import "bootstrap/scss/bootstrap"` compilado com nossos tokens | grid e utilities continuam existindo; ganha-se reboot, componentes e a Utility API |
| `:root { --color-*; --radius-*; --shadow-* }` como fonte | `resources/scss/_variables.scss` como fonte; `:root` reemitido como *output* | ver "fonte de verdade" |
| 634 atributos `style="..."` | classes utilitárias + classes de componente | zero `style=` é regra de lint (ver `bootstrap-conventions`) |
| Classes fantasma `.btn`, `.btn-primary`, `.btn-ghost`, `.card`, `.dialog`, `.tag-accent`, `.tag-outline`, `.input`, `.field`, `.elev-sm/md/lg` — **nunca existiram em CSS algum** | `.btn .btn-primary`, `.btn .btn-outline-secondary`, `.btn .btn-link`, `.card`, `.modal`, `.badge text-bg-*`, `.form-control`, `.shadow-sm/.shadow/.shadow-lg` do Bootstrap | as classes antigas eram decorativas; o visual real vinha do inline style |
| `.dialog` / `.dialog-backdrop` + diretivas Alpine órfãs | `.modal` / `.modal-backdrop` do Bootstrap | Alpine nunca esteve instalado; o modal era inerte |
| `ModalManager.js` | `bootstrap.Modal` | módulo removido |
| `NotificationService.js` (toast artesanal) | `bootstrap.Toast` + `<x-ui.toast>` | a *fachada* `window.NotificationService.success()/error()` é mantida, reimplementada sobre `bootstrap.Toast`, para não quebrar os 6 módulos que a injetam |
| Tailwind 4 (`@tailwindcss/vite`, `tailwindcss`) instalado e não usado | removido do `package.json` e do `vite.config.js` | dependência morta; remoção exige aprovação (ver Parte C, T0) |
| `bunny('Instrument Sans')` no `vite.config.js` | removido — a fonte real é Archivo self-hosted | fonte baixada e nunca aplicada |
| `<x-ui.select>` com chevron SVG absoluto + `appearance:none` | `.form-select` do Bootstrap | |
| `<x-ui.table>` com styles inline | `.table` + wrapper `.table-responsive` | |
| erros de validação renderizados à mão | `.is-invalid` + `.invalid-feedback` | ver `bootstrap-conventions` |

---

## Contrato com o resto do sistema

- **Dusk é intocável.** Os 316 atributos `dusk="..."` em `resources/views/` são
  contrato de teste. Migrar markup **nunca** renomeia, move para outro elemento
  semântico, nem remove um `dusk=`. Se o elemento que carregava o `dusk` deixa de
  existir, o atributo migra para o elemento equivalente mais próximo — e isso é
  uma mudança que precisa ser justificada no receipt da migração.
- **PDF é território separado.** `resources/views/certificates/pdf.blade.php` é
  renderizado por `barryvdh/laravel-dompdf`, que **não** entende o CSS do
  Bootstrap 5 (custom properties, `color-mix()`, flexbox moderno, grid). A view de
  PDF mantém CSS próprio embutido em `<style>` e é a **única** exceção permitida à
  regra de zero CSS ad-hoc. Ver `bootstrap-maintenance`.
- **Multi-tenant/roles não mudam.** A sidebar continua montando itens por
  `role:admin|gestor|aluno` (Spatie); a migração é puramente de camada de
  apresentação.
- **`<x-help-button>`** (SPEC-11) continua montado em 100% das telas; ele passa a
  abrir um `.modal` do Bootstrap, mas mantém `dusk="help-button-{key}"` e
  `dusk="help-article-content-{key}"`.
