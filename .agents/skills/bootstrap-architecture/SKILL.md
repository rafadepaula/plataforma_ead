---
name: bootstrap-architecture
description: >
  Bootstrap 5.3 frontend architecture após a migração de inline styles:
  modelo de 5 camadas (tokens SCSS, Bootstrap core, componentes do projeto,
  wrappers Blade, telas), por que variáveis SCSS do Bootstrap (`$primary`,
  `$border-radius`, `$font-family-base`) substituem o set paralelo
  `--color-*` como fonte de verdade, princípio JS-do-Bootstrap-acima-de-JS-
  artesanal que aposenta ModalManager/NotificationService/dropdown, mandato
  de componentização (sem markup Bootstrap cru em tela), decision record.
  Use ao desenhar ou revisar tela, layout, componente Blade ou módulo JS,
  antes de criar classe CSS ou componente `<x-ui.*>`, ou ao decidir em que
  camada uma regra de estilo mora.
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

## Visão Geral

Frontend = **Bootstrap 5.3 completo** (SCSS + JS). **Modernist Design System** vira **customização do Bootstrap**, não sistema paralelo. Não existe mais "CSS do projeto que imita Bootstrap" — existe Bootstrap configurado com tokens da marca.

Princípio-mestre: **uma decisão visual mora em exatamente um lugar.** Botão de canto reto = `$border-radius: 0`, não 634 atributos `style="border-radius: 0px"` espalhados.

---

## As 5 Camadas

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
│     Importado por inteiro, compilado com as variáveis da camada 1.
└─ 1. Camada de tokens SCSS (resources/scss/_variables.scss)
      A ÚNICA fonte de verdade do design. Sobrescreve as variáveis !default
      do Bootstrap ANTES do import do core.
```

Entrada (`resources/scss/app.scss`), ordem obrigatória:

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

`vite.config.js` aponta `resources/scss/app.scss` no lugar de `resources/css/app.css`. `resources/css/app.css` é **removido** no fim da migração — dois arquivos de estilo é exatamente o problema que a migração mata.

---

## Por Que as Variáveis SCSS São a Fonte de Verdade

Pré-migração tinha **dois** sistemas de token lado a lado:

- `:root { --color-accent: #ec3013; --radius-sm: 0px; ... }` — consumido por 634 `style=` inline;
- variáveis internas do Bootstrap (`$primary`, `$border-radius`, `$font-family-base`), nunca configuradas, porque só se importava CSS já compilado de `bootstrap/dist/`.

Resultado: nenhum utility ou componente do Bootstrap conhecia a marca. Botão vermelho exigia escrever o vermelho à mão, em cada botão.

Depois, fluxo único:

```scss
// resources/scss/_variables.scss  (camada 1)
$primary:   #ec3013;   // accent Modernist
$body-bg:   #f3f2f2;
$body-color:#201e1d;
$border-radius: 0;
$font-family-base: "Archivo", system-ui, sans-serif;
```

A partir daí `.btn-primary`, `.bg-primary`, `.text-primary`, `.border-primary`, `.badge.text-bg-primary`, `:focus-visible`, `.form-control:focus` e a CSS var `--bs-primary` saem na cor certa de graça, sem CSS do projeto.

As custom properties `--color-*` **continuam existindo**, mas *derivadas* — a camada 1 as reemite a partir das variáveis SCSS, para código legado e JS que leia `getComputedStyle`. Elas **não são mais a fonte**: quem muda a marca edita `_variables.scss`, nunca o bloco `:root`.

Regra: **`--color-*` é output, `$variável` é input.** PR que muda cor mexendo em `:root` está errada por construção.

---

## JS do Bootstrap Acima de JS Artesanal

Pré-migração: 12 módulos JS em `resources/js/modules/`, três reimplementando à mão o que o Bootstrap já entrega:

| Módulo artesanal | Comportamento | Substituto Bootstrap |
| :--- | :--- | :--- |
| `ModalManager.js` | abrir/fechar, backdrop, `Escape`, foco, `data-modal-target` | `bootstrap.Modal` + `data-bs-toggle="modal"` / `data-bs-dismiss="modal"` |
| `NotificationService.js` | toasts, container dinâmico, auto-dismiss | `bootstrap.Toast` + `.toast-container` |
| dropdown inline do `<x-layout.topbar>` | menu de perfil e notificações | `bootstrap.Dropdown` (+ Popper, vem no bundle) |
| diretivas Alpine órfãs em `<x-ui.modal>` | `x-data`/`x-show`/`@click.outside` **sem Alpine instalado** — inertes | `bootstrap.Modal` |

Princípio: **Bootstrap tem a API, o projeto não escreve a sua.** Motivos concretos:

1. **Acessibilidade grátis** — focus trap, `aria-modal`, `aria-expanded`, restauração de foco, inertização do fundo. Tudo testado no Bootstrap; a versão artesanal cobria parte.
2. **Empilhamento e z-index corretos** — backdrops múltiplos, `.modal-open` no `<body>`, compensação de scrollbar. `ModalManager` não tratava empilhamento.
3. **Menos manutenção** — ~10 KB de JS do projeto somem.
4. **Contrato estável para Dusk** — `data-bs-toggle` + classe `.show` são contrato público documentado, alvo melhor de `waitFor` que estado interno de objeto do projeto.

Módulos que **ficam** carregam lógica de domínio, não de widget: `HttpClient`, `CsvImporter`, `LessonPlayer`, `ModuleReorder`, `SmartInvitationForm`, `QuizBuilder`, `QuizTimer`, `ForumPolling`, `NotificationBell`, `ForumEditHistory`, `ForumReportModal`, `AuditLogDiffModal`. Os quatro últimos **param de instanciar `ModalManager`** e usam `bootstrap.Modal.getOrCreateInstance(el)`. API pública de cada módulo (`init()`, construtor com dependências injetadas, registro em `window.*`) fica intacta — é contrato de `app.js` e das views.

Bundle: `resources/js/app.js` começa com

```js
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;
```

Traz Popper embutido (build `bootstrap.bundle` do npm resolve Popper por dependência), habilitando dropdown, tooltip, popover.

---

## Mandato de Componentização

**Nenhuma tela escreve markup cru do Bootstrap.** Tela nunca contém `<div class="modal fade" tabindex="-1">…</div>` nem `<nav class="navbar">…`. Contém `<x-ui.modal>` e `<x-layout.topbar>`.

- **Um lugar para corrigir.** Bootstrap 5.4 mudando estrutura interna do modal = mexer em `components/ui/modal.blade.php`, não em 40 telas.
- **ARIA por construção.** Atributos de acessibilidade moram no wrapper; tela não esquece.
- **Contrato de teste.** Wrapper garante `dusk="modal-{id}"` em todo modal e o `dusk` que a tela passou em todo submit.
- **Detecção de deriva.** Tela precisando de markup que nenhum componente expõe = componente faltando. Resposta certa: criar/estender componente (via `bootstrap-component-author`), nunca inlinear markup.

Exceção única: **utilities de layout** (`row`, `col-*`, `d-flex`, `gap-*`, `mb-*`, `text-*`) vão direto na tela. Utility é vocabulário, não estrutura.

---

## Decision Record

| Antes | Depois | Nota |
| :--- | :--- | :--- |
| `@import "bootstrap/dist/css/bootstrap-grid.min.css"` + `bootstrap-utilities.min.css` | `@import "bootstrap/scss/bootstrap"` compilado com nossos tokens | grid e utilities continuam; ganha reboot, componentes e Utility API |
| `:root { --color-*; --radius-*; --shadow-* }` como fonte | `resources/scss/_variables.scss` como fonte; `:root` reemitido como *output* | ver "fonte de verdade" |
| 634 atributos `style="..."` | classes utilitárias + classes de componente | zero `style=` é regra de lint (`bootstrap-conventions`) |
| Classes fantasma `.btn`, `.btn-primary`, `.btn-ghost`, `.card`, `.dialog`, `.tag-accent`, `.tag-outline`, `.input`, `.field`, `.elev-sm/md/lg` — **nunca existiram em CSS algum** | `.btn .btn-primary`, `.btn .btn-outline-secondary`, `.btn .btn-link`, `.card`, `.modal`, `.badge text-bg-*`, `.form-control`, `.shadow-sm/.shadow/.shadow-lg` | classes antigas eram decorativas; visual real vinha do inline style |
| `.dialog` / `.dialog-backdrop` + diretivas Alpine órfãs | `.modal` / `.modal-backdrop` | Alpine nunca esteve instalado; modal era inerte |
| `ModalManager.js` | `bootstrap.Modal` | módulo removido |
| `NotificationService.js` | `bootstrap.Toast` + `<x-ui.toast>` | fachada `window.NotificationService.success()/error()` mantida sobre `bootstrap.Toast`, para não quebrar os 6 módulos que a injetam |
| Tailwind 4 (`@tailwindcss/vite`, `tailwindcss`) instalado e não usado | removido de `package.json` e `vite.config.js` | dependência morta; remoção exige aprovação (Parte C, T0) |
| `bunny('Instrument Sans')` no `vite.config.js` | removido — fonte real é Archivo self-hosted | fonte baixada e nunca aplicada |
| `<x-ui.select>` com chevron SVG absoluto + `appearance:none` | `.form-select` | |
| `<x-ui.table>` com styles inline | `.table` + wrapper `.table-responsive` | |
| erros de validação renderizados à mão | `.is-invalid` + `.invalid-feedback` | ver `bootstrap-conventions` |

---

## Contrato com o Resto do Sistema

- **Dusk é intocável.** Os 316 `dusk="..."` em `resources/views/` são contrato de teste. Migrar markup **nunca** renomeia, move para outro elemento semântico, nem remove um `dusk=`. Se o elemento sumir, o atributo migra para o equivalente mais próximo — e isso precisa de justificativa no receipt da migração.
- **PDF é território separado.** `resources/views/certificates/pdf.blade.php` roda em `barryvdh/laravel-dompdf`, que **não** entende CSS do Bootstrap 5 (custom properties, `color-mix()`, flexbox moderno, grid). A view de PDF mantém CSS próprio em `<style>` — **única** exceção à regra de zero CSS ad-hoc. Ver `bootstrap-maintenance`.
- **Multi-tenant/roles não mudam.** Sidebar continua montando itens por `role:admin|gestor|aluno` (Spatie). Migração é só camada de apresentação.
- **`<x-help-button>`** (SPEC-11) segue em 100% das telas. Passa a abrir `.modal` do Bootstrap, mantendo `dusk="help-button-{key}"` e `dusk="help-article-content-{key}"`.
