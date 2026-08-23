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
    - spec/front_redesign/01-direcao-visual-e-tokens.md
    - spec/front_redesign/02-camada-de-tema-e-build.md
    - spec/front_redesign/14-contrato-dusk-e-testes.md
    - spec/front_redesign/15-plano-de-fases.md
---

# Bootstrap Architecture

> **Redesign em andamento (`spec/front_redesign/`, Fase 0 concluída).** O
> **Modernist Design System** (accent vermelho `#ec3013`, canto reto
> sistêmico, fonte Archivo) descrito abaixo está sendo **substituído** pelo
> novo **Material Bootstrap** de `spec/front_redesign/01-direcao-visual-e-tokens.md`
> — paleta pastel azul/menta/céu (**vermelho, laranja e amarelo proibidos**),
> cantos suaves (`$border-radius: 14px`, não 0), fonte **Nunito Sans**. A
> Fase 0 já trocou a camada de tokens e o pipeline de build (ver seção
> "Camada de Tokens" abaixo). **Fase 1 (shell autenticado + shell
> público) também concluída**: `layouts/app.blade.php`,
> `layouts/guest.blade.php`, `components/layout/{topbar,sidebar,
> page-header,footer,alerts}.blade.php`, `components/layout/guest-panel.blade.php`
> (novo), `components/notifications-bell.blade.php` e
> `components/help-button.blade.php` já rodam sobre o Material Bootstrap.
> **Fase 2 (biblioteca de componentes `<x-ui.*>`) também concluída**: os 21
> componentes de `resources/views/components/ui/` foram reescritos e 5 novos
> entraram (`chip`, `avatar`, `fab`, `switch`, `tabs`) — ver
> `bootstrap-conventions` §4 para o mapeamento atualizado. Os 9 stubs de
> `resources/scss/components/` (`_stat-card`, `_empty-state`, `_fab`, `_chip`,
> `_tabs`, `_floating-label`, `_state-layer`, `_pastel-wash`, `_reorder-list`)
> estão preenchidos. A classe fantasma `grayscale` (última sobrevivente em
> `components/ui/card.blade.php`) foi removida do projeto inteiro, substituída
> por `.ds-pastel-wash`. **Fase 3 (telas de listagem: `dashboard/index`,
> `courses/index`, `courses/modules/_list`, `modules/lessons/index`,
> `courses/enrollments/index`, `courses/invitation-links/index`,
> `courses/completion-rules/index`, `certificates/index`,
> `organizations/index`, `users/index`, `admin/users/index`,
> `admin/users/show`, `audit-logs/index` e o modal `audit-logs/partials/
> _diff-modal`) também concluída.** **Fase 4 (todas as telas de formulário
> `create`/`edit`/`_form`/`import` dos docs 05 e 09 — cursos, módulos,
> lições, links de convite, organizações, usuários, admin/usuários,
> configurações, perfil) também concluída**: `<x-ui.field-stack>` e
> `<x-ui.form-actions>` são os componentes centrais de toda tela de
> formulário migrada; a classe `.org-logo` deixou de ser fantasma/exceção
> nesta fase, ganhando regra real em
> `resources/scss/components/_organizations.scss`. **Fase 7 (públicas e
> acesso, doc `10`) também concluída**: as 7 telas do doc (`auth/login`,
> `auth/forgot-password`, `auth/reset-password`, `convite/show`, o novo
> `convite/invalid`, `landing/show`, `public/certificates/show`) rodam sobre
> o Material Bootstrap, mais os layouts `layouts/guest.blade.php` e o novo
> `layouts/print.blade.php` (exceção isolada para `certificates/pdf.blade.php`
> — ver `bootstrap-maintenance` §3.1 sobre a exceção). Partial novo:
> `resources/scss/components/_public-pages.scss` (`.hero-panel`,
> `.numbers-band`, `.max-w-reading`, `.icon-circle` +
> `.icon-circle-success|critical|primary`) — ver seção "Camada de Tokens"
> abaixo para a lista de partials atualizada. Telas de Aluno/quiz/fórum
> seguem o trabalho das Fases 5–6 do plano. **Fase 8 (mobile, acessibilidade
> e polimento, docs 11 e 13 — última fase do redesign) também concluída**:
> `prefers-reduced-motion` passa a existir (`_reduced-motion.scss`, escopo
> **só** modal e drawer — nada mais some animação de entrada). Tabela usa
> componente canônico `<x-ui.table>`; `<x-ui.data-table>` = alias compatível.
> `_table.scss` entrega anatomia `.ds-table-wrap`/`.ds-table-toolbar`/
> `.ds-table-scroll`/`.ds-table`, reflow abaixo de `md` por markup único e
> `data-label` no `<td>`. Cursos, organizações, usuários, admin/usuários e
> audit logs já eliminaram marcação desktop/mobile duplicada. `<x-ui.fab>` soma `env(safe-area-inset-
> bottom)`, modal abaixo de `sm` vira full-bleed com raio só no topo
> (`_modal.scss`), `.max-w-reading` cede para 100% com padding 20px abaixo de
> `md` (`_public-pages.scss`), `.app-main` cai para `padding: var(--space-5)`
> (20px) abaixo de `lg`, alvo de toque mínimo 48px auditado em
> `_utilities.scss` (`--touch-min`), e a app bar (`<x-layout.topbar>`) some o
> rótulo em texto do cluster direito abaixo de `sm` (ícone + `.visually-
> hidden`, nunca ícone mudo) para o cluster inteiro caber em 320px — esse
> ajuste também removeu o último resíduo real de `.grayscale` no círculo de
> iniciais do topbar (trocado por `.ds-avatar`), que tinha sobrevivido à
> remoção "definitiva" registrada na Fase 2. Ao trabalhar em qualquer tela,
> confira se ela já foi migrada para o novo sistema antes de seguir as
> convenções "Modernist" (vermelho, `border-radius: 0`) descritas neste
> arquivo como histórico.

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
│     Só o que o Bootstrap NÃO resolve: .sidebar, .stat-card, .appbar,
│     .ds-fab, .ds-chip, .ds-table (reflow em card). Nunca reimplementa
│     .btn/.card/.modal.
├─ 2. Bootstrap core (node_modules/bootstrap/scss/bootstrap.scss)
│     Importado por inteiro, compilado com as variáveis da camada 1.
└─ 1. Camada de tokens (_ds/.../tokens/*.css + resources/scss/_bridge.scss)
      Token CSS é a fonte de verdade de design; `_bridge.scss` é a de
      implementação e sobrescreve as variáveis !default do Bootstrap ANTES
      do import do core. Não existe `resources/scss/_variables.scss`.
```

**[ATUALIZADO — Fase 0 do `front_redesign`]** Entrada real (`resources/scss/app.scss`), 4 imports, ordem obrigatória:

```scss
// 1) Tokens do design system (custom properties, disponíveis em runtime)
@import "../../_ds/plataforma-ead-design-system/styles.css";

// 2) Ponte: valores literais alimentam as variáveis Sass do Bootstrap
@import "bridge";

// 3) Bootstrap completo, já temático
@import "bootstrap/scss/bootstrap";

// 4) Componentes que o Bootstrap não entrega
@import "components/index";
```

A camada de tokens deixou de ser um único `_variables.scss` com hex à mão.
Agora é **duas peças**:

- `_ds/plataforma-ead-design-system/tokens/*.css` — fonte de verdade de
  **design** (custom properties `--*`, publicadas fora de `resources/`,
  consumidas também em runtime/JS via `getComputedStyle`);
- `resources/scss/_bridge.scss` — fonte de verdade de **implementação**:
  copia o valor literal de cada token relevante para a variável Sass do
  Bootstrap correspondente (`$primary`, `$border-radius`, `$font-family-base`,
  `$box-shadow*`, `$btn-*`, `$input-*`, `$card-*`, `$modal-*`, `$table-*`,
  `$dropdown-*`). Sass não resolve `var(--x)` em tempo de build — divergência
  entre `_bridge.scss` e os tokens de `_ds/` é bug de build, não estilo.
  `$warning` mapeia para `--attention` (nunca amarelo), `$danger` para
  `--critical` (nunca vermelho); `$red`/`$orange`/`$yellow` também são
  remapeados para os mesmos tokens pastel, para que nada vermelho/amarelo
  vaze de volta via `$reds`/`$yellows` do Bootstrap.

`components/index.scss` importa os partials específicos do novo sistema —
lista cresce por fase, não é fechada. Estado atual (Fase 8 concluída, redesign
inteiro fechado):
`_appbar`, `_drawer`, `_page-header`, `_stat-card`, `_empty-state`, `_fab`,
`_chip`, `_tabs`, `_avatar`, `_brand-mark`, `_pastel-wash`, `_state-layer`,
`_floating-label`, `_reorder-list`, `_guest-panel`, `_card`, `_organizations`,
`_public-pages`, `_utilities`, `_modal`, `_table`, `_reduced-motion` —
substitui a lista antiga (`sidebar`/`topbar`/`stat-card`/`lesson-player`/
`grayscale`) conforme as telas migraram nas Fases 1–8. `_guest-panel` foi
acrescentado na Fase 1 para a coluna de formulário de 440px (`.guest-form`,
`var(--form-max)`) do `layouts/guest.blade.php`; `_card` e `_organizations`
na Fase 3/4; `_public-pages` na Fase 7, para os blocos de layout de
`landing/show` e `public/certificates/show` sem equivalente pronto no
Bootstrap (`.hero-panel`, `.numbers-band`, `.max-w-reading`, `.icon-circle*`
— ver `bootstrap-conventions` §3 item 4 sobre quando um bloco novo vira
partial em vez de utility); e os três últimos na Fase 8: `_modal` (dialog
full-bleed com raio só no topo abaixo de `sm`), `_table` (superfície tabular
canônica + reflow para cards abaixo de `md` via `data-label`, markup único;
importado por `components/_index.scss`) e `_reduced-motion` (`@media
(prefers-reduced-motion: reduce)`, escopo fechado em `.modal.fade
.modal-dialog` e `.offcanvas.fade`, nada mais). Nenhum desses partials
consome hex literal: só `var(--*)` ou variável Sass já alimentada pela
ponte — `_fab.scss` é a única exceção documentada a "só `var(--*)`", porque
soma `env(safe-area-inset-bottom, 0px)` ao offset do FAB (não há token de
design para inset de notch de dispositivo).

`vite.config.js` aponta `resources/scss/app.scss` no lugar de `resources/css/app.css`. `resources/css/app.css` é **removido** no fim da migração — dois arquivos de estilo é exatamente o problema que a migração mata.

---

## Por Que as Variáveis SCSS São a Fonte de Verdade

Pré-migração tinha **dois** sistemas de token lado a lado:

- `:root { --color-accent: #ec3013; --radius-sm: 0px; ... }` — consumido por 634 `style=` inline;
- variáveis internas do Bootstrap (`$primary`, `$border-radius`, `$font-family-base`), nunca configuradas, porque só se importava CSS já compilado de `bootstrap/dist/`.

Resultado: nenhum utility ou componente do Bootstrap conhecia a marca. Botão vermelho exigia escrever o vermelho à mão, em cada botão.

Depois, fluxo único:

```scss
// resources/scss/_bridge.scss  (camada 1)
$primary:   #4c6fe7;   // --blue-600
$body-bg:   #f6f8fc;   // --surface-body
$body-color:#1b2437;   // --text-primary
$border-radius: 14px;  // --radius-md
$font-family-base: "Nunito Sans", system-ui, -apple-system, "Segoe UI", sans-serif;
```

A partir daí `.btn-primary`, `.bg-primary`, `.text-primary`, `.border-primary`, `.badge.text-bg-primary`, `:focus-visible`, `.form-control:focus` e a CSS var `--bs-primary` saem na cor certa de graça, sem CSS do projeto.

O set paralelo `--color-*` escrito à mão **não existe mais**: as custom properties vivem em `_ds/.../tokens/*.css` e são a fonte de verdade de *design*, consumidas também em runtime/JS via `getComputedStyle`. `_bridge.scss` copia o valor literal de cada token para a variável Sass correspondente, porque Sass não resolve `var(--x)` em tempo de compilação.

Regra: quem muda a marca edita o token em `_ds/`, e reflete o mesmo valor em `_bridge.scss`. Divergência entre os dois é bug de build. PR que cria um `:root { --color-* }` novo em `resources/scss/` está errada por construção.

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

Módulos que **ficam** carregam lógica de domínio, não de widget: `HttpClient`, `CsvImporter`, `LessonPlayer`, `ModuleReorder`, `SmartInvitationForm`, `QuizBuilder`, `QuizTimer`, `ForumPolling`, `NotificationBell`, `ForumReportModal`, `AuditLogDiffModal`, `PasswordToggle`. Os que abrem diálogo **param de instanciar `ModalManager`** e usam `bootstrap.Modal.getOrCreateInstance(el)`. API pública de cada módulo (`init()`, construtor com dependências injetadas, registro em `window.*`) fica intacta — é contrato de `app.js` e das views.

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
| `:root { --color-*; --radius-*; --shadow-* }` escrito à mão em `resources/scss/` | tokens em `_ds/.../tokens/*.css` + `resources/scss/_bridge.scss` como ponte para o Sass | ver "fonte de verdade" |
| 634 atributos `style="..."` | classes utilitárias + classes de componente | zero `style=` é regra de lint (`bootstrap-conventions`) |
| Classes fantasma `.btn`, `.btn-primary`, `.btn-ghost`, `.card`, `.dialog`, `.tag-accent`, `.tag-outline`, `.input`, `.field`, `.elev-sm/md/lg` — **nunca existiram em CSS algum** | `.btn .btn-primary`, `.btn .btn-outline-secondary`, `.btn .btn-link`, `.card`, `.modal`, `.badge text-bg-*`, `.form-control`, `.shadow-sm/.shadow/.shadow-lg` | classes antigas eram decorativas; visual real vinha do inline style |
| `.dialog` / `.dialog-backdrop` + diretivas Alpine órfãs | `.modal` / `.modal-backdrop` | Alpine nunca esteve instalado; modal era inerte |
| `ModalManager.js` | `bootstrap.Modal` | módulo removido |
| `NotificationService.js` | `bootstrap.Toast` + `<x-ui.toast>` | fachada `window.NotificationService.success()/error()` mantida sobre `bootstrap.Toast`, para não quebrar os 6 módulos que a injetam |
| Tailwind 4 (`@tailwindcss/vite`, `tailwindcss`) instalado e não usado | removido de `package.json` e `vite.config.js` | dependência morta; remoção exige aprovação (Parte C, T0) |
| `bunny('Instrument Sans')` no `vite.config.js` | removido — fonte real é Archivo self-hosted | fonte baixada e nunca aplicada |
| `<x-ui.select>` com chevron SVG absoluto + `appearance:none` | `.form-select` | |
| `<x-ui.table>` com styles inline / `<x-ui.data-table>` independente | `<x-ui.table>` canônico: `.ds-table-wrap` + toolbar opcional + `.ds-table-scroll` + `.ds-table`; `<x-ui.data-table>` delega como alias | props e slots idênticos; atributos chegam ao `<table>` |
| erros de validação renderizados à mão | `.is-invalid` + `.invalid-feedback` | ver `bootstrap-conventions` |

---

## Superfície Tabular Canônica

Fluxo único:

```text
<x-ui.data-table> (alias)
        │
        └─ <x-ui.table>
              └─ .ds-table-wrap
                   ├─ .ds-table-toolbar (slot opcional)
                   └─ .ds-table-scroll[.table-responsive]
                        └─ table.ds-table
                             ├─ thead
                             ├─ tbody
                             └─ tfoot (slot opcional)
```

- Props: `headers`, `striped`, `hover`, `hoverable`, `responsive`, `size`.
- `hover` e `hoverable` = aliases; ambos ativam `.ds-table-hover`.
- `size="sm"` ativa `.ds-table-sm`; `responsive=false` remove só
  `.table-responsive`, mantendo `.ds-table-scroll`.
- Slots: `toolbar`, `header`, default (`tbody`), `footer`.
- `$attributes` chega ao `<table>`: `aria-label`, `dusk`, classes e demais
  atributos semânticos nunca ficam no wrapper.
- `_table.scss` consome tokens publicados; zero literal visual.
- Mobile responsivo mantém `thead` no DOM e árvore de acessibilidade. CSS o
  oculta visualmente com técnica de recorte; nunca `display:none` nem
  `aria-hidden`. `tbody` vira grid de cards; cada `<td data-label>` expõe
  rótulo visível em `::before`.
- Cinco listagens antes duplicadas agora usam este fluxo: cursos,
  organizações, usuários, admin/usuários e audit logs. Um registro = um `<tr>`
  = um seletor `dusk`.

---

## Contrato com o Resto do Sistema

- **Dusk é intocável.** Os **400** `dusk="..."` em `resources/views/` (baseline atual em `tests/fixtures/dusk-selectors-snapshot.json`) são contrato de teste. Migrar markup **nunca** renomeia, move para outro elemento semântico, nem remove um `dusk=`. Se o elemento sumir, o atributo migra para o equivalente mais próximo — e isso precisa de justificativa no receipt da migração.
- **PDF é território separado.** `resources/views/certificates/pdf.blade.php` roda em `barryvdh/laravel-dompdf`, que **não** entende CSS do Bootstrap 5 (custom properties, `color-mix()`, flexbox moderno, grid). A view de PDF mantém CSS próprio em `<style>` — **única** exceção à regra de zero CSS ad-hoc. Ver `bootstrap-maintenance`.
- **Multi-tenant/roles não mudam.** Sidebar continua montando itens por `role:admin|gestor|aluno` (Spatie). Migração é só camada de apresentação.
- **`<x-help-button>`** (SPEC-11) segue em 100% das telas. Passa a abrir `.modal` do Bootstrap, mantendo `dusk="help-button-{key}"` e `dusk="help-article-content-{key}"`.

## Pagination and Course Catalog Surfaces

Shared `<x-ui.pagination>` owns one `nav` landmark, Portuguese result counter,
and internal links-only views. Length-aware paginator uses numbered desktop
branch plus compact previous/next mobile branch. Simple paginator uses
previous/next only. `.ds-pagination .page-link` = 40x40 pill.

Course catalog adds `_courses.scss` to component-layer inventory. Partial owns
fixed desktop column widths/alignment only; canonical `_table.scss` still owns
single-markup mobile card reflow. `_courses.scss` resets fixed widths below
`md`, preventing horizontal overflow.
