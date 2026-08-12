# 03 — Inventário de Telas e Composição Bootstrap Alvo

> **Escopo deste documento**: a camada de TELAS — tudo em `resources/views/**` **exceto**
> `resources/views/components/**` (16 arquivos) e `resources/views/layouts/**` (2 arquivos).
> São **60 arquivos Blade** de tela (78 views totais − 18 de componente/layout), somando
> **3.710 linhas** e **~500 atributos `style="` inline** (dos 634 do projeto inteiro; os
> ~134 restantes estão na camada de componentes/layout, coberta pelo documento de fundação).
>
> Este é um **documento de instrução de build** para agentes implementadores. Cada seção de
> tela é autossuficiente: caminho, rotas, layout, papéis, padrões atuais, contagem de inline
> styles, dependência de JS, composição Bootstrap 5.3 alvo, seletores `dusk` imutáveis e o
> comando de verificação Dusk.

---

## 0. Convenções globais desta migração

### 0.1 Stack alvo

| Item | Estado atual | Estado alvo |
|---|---|---|
| CSS | `resources/css/app.css` importa **apenas** `bootstrap-grid.min.css` + `bootstrap-utilities.min.css` | Bootstrap 5.3 **completo** (`bootstrap.min.css` ou SCSS customizado com os tokens do Modernist Design System) |
| JS Bootstrap | **ausente** | `bootstrap.bundle.min.js` importado em `resources/js/app.js` (necessário para Modal, Dropdown, Collapse, Tooltip) |
| Tailwind | instalado, **morto** (nenhuma classe utilizada) | **remover** de `package.json`/`vite.config.js`/`postcss` |
| Estilização real | 634 atributos `style="..."` inline | classes utilitárias Bootstrap + componentes Blade reutilizáveis |
| Classes órfãs | `.btn`, `.btn-primary`, `.btn-secondary`, `.btn-ghost`, `.btn-sm`, `.card`, `.field`, `.input`, `.table`, `.nav`, `.tag-*`, `.dialog-backdrop`, `.dialog-body`, `.dialog-actions`, `.text-muted`, `.admin-dashboard-container`, `.quiz-option-row` — **sem definição CSS** | `.btn`/`.btn-*`/`.card`/`.table`/`.nav`/`.text-muted` passam a existir de verdade (Bootstrap). `.btn-ghost` → `.btn-link` ou `.btn-outline-secondary`. `.field` → `.mb-3`. `.dialog-*` → `.modal`/`.modal-body`/`.modal-footer`. `.tag-*` → `.badge`. `.quiz-option-row` → `.list-group-item` |

### 0.2 Regra de máxima componentização

**Proibido** escrever markup Bootstrap cru repetido em mais de uma tela. Todo padrão que
aparece 2+ vezes vira componente. A biblioteca alvo é:

**Componentes já existentes (a serem reescritos na fundação, Wave A — NÃO recriar):**
`<x-ui.alert>`, `<x-ui.badge>`, `<x-ui.button>`, `<x-ui.card>`, `<x-ui.icon>`,
`<x-ui.input>`, `<x-ui.modal>`, `<x-ui.select>`, `<x-ui.stat-card>`, `<x-ui.table>`,
`<x-help-button>`, `<x-notifications-bell>`, `<x-layout.alerts>`, `<x-layout.footer>`,
`<x-layout.sidebar>`, `<x-layout.topbar>`.

**Componentes NOVOS obrigatórios (criados na Wave A / B, consumidos pelas telas):**

| Componente | Bootstrap interno | Substitui hoje |
|---|---|---|
| `<x-layout.page-header kicker title>` + slot `actions` | `d-flex justify-content-between align-items-center mb-4` + `.text-uppercase.small.fw-bold.text-accent` + `h1.h4` | o bloco `<div style="display:flex;...">` + `<span>kicker</span>` + `<h1>` repetido em **19 telas** |
| `<x-layout.section-header title>` + slot `actions` | `d-flex justify-content-between align-items-center mb-3` + `h2.h5` | cabeçalhos de subseção (`quizzes/edit`, `dashboard`) |
| `<x-layout.public>` (shell standalone) | `<!doctype html>` + `@vite` + `.container` | o HTML duplicado em `landing/show` e `public/certificates/show` |
| `<x-layout.print>` | **nenhum Bootstrap** — CSS embutido dompdf-safe | `certificates/pdf.blade.php` (ver §0.5) |
| `<x-ui.data-table :headers striped hover responsive>` | `.table.table-striped.table-hover` dentro de `.table-responsive` | `<x-ui.table>` + `<tr style="border-bottom:...">` + `<td style="padding:12px 16px">` em **12 telas** |
| `<x-ui.empty-state :colspan message>` | `<tr><td colspan><div class="text-center text-body-secondary py-4">` | as 12 variações de `@empty`/`<td colspan=... style="text-align:center">` |
| `<x-ui.pagination :paginator>` | `{{ $p->links() }}` com `paginator: bootstrap-5` + wrapper `.d-flex.justify-content-center.mt-4` | `<div style="margin-top:20px">{{ $x->links() }}</div>` em **8 telas** |
| `<x-ui.filter-bar :action>` + slot | `<form class="row g-2 align-items-end p-3 bg-body-tertiary border rounded-0">` | o form de filtros de `audit-logs/index` |
| `<x-ui.form-actions>` | `.d-flex.gap-2.mt-4` | `<div style="display:flex; gap:12px; margin-top:24px;">` em **14 telas** |
| `<x-ui.field-stack>` | `.d-flex.flex-column.gap-3` (ou `.vstack.gap-3`) | `<div style="display:flex; flex-direction:column; gap:20px; max-width:560px">` em **12 telas** |
| `<x-ui.checkbox name label :checked>` | `.form-check` + `.form-check-input` + `.form-check-label` | os `<input type="checkbox">` + `<label>` crus de `courses/_form`, `modules/lessons/_form`, `quizzes/create`, `quizzes/edit`, `auth/login` |
| `<x-ui.radio-group name :options>` | `.form-check.form-check-inline` | `quizzes/attempts/show` (Correta/Incorreta) |
| `<x-ui.file-input name label :current>` | `.form-control[type=file]` + `.form-text` + `.invalid-feedback` | `organizations/_form`, `modules/lessons/_form` (×2), `settings/edit`, `users/import` |
| `<x-ui.textarea name label rows>` | `.form-control` + `.form-label` + `.invalid-feedback` | os `<textarea style="width:100%;box-sizing:border-box;...">` crus de `certificates/index`, `forum/index`, `forum/show` (×2) |
| `<x-ui.progress :value>` | `.progress` + `.progress-bar` | as 4 barras de progresso feitas à mão (`classroom/show`, `student/courses/index`, `users/import`) |
| `<x-ui.sortable-list :url>` + `<x-ui.sortable-item :id>` | `.list-group` + `.list-group-item.d-flex.justify-content-between.align-items-center` + handle `⠿` | `courses/modules/_list`, `modules/lessons/index`, `quizzes/partials/_question-list` |
| `<x-ui.confirm-modal id title :action method>` | `.modal.fade` + `.modal-dialog` + form embutido | os modais de revogação (`certificates/index`) e o padrão de confirmação hoje inexistente nos deletes |
| `<x-ui.delete-button :action label>` | `<form>` + `.btn.btn-link.text-danger` + `data-bs-toggle="modal"` | os **11** blocos `<form method="POST">@method('DELETE')<button class="btn btn-ghost">` |
| `<x-ui.kv-table :rows>` | `.table.table-borderless` chave/valor | a `<table>` manual de `public/certificates/show` |
| `<x-ui.quiz-choice type name :option>` | `.list-group-item` clicável com `.form-check-input` | os `<label style="display:flex;...border:1px solid...">` de `student/quizzes/show` |
| `<x-ui.option-row :index :option>` | `.input-group` + `.input-group-text` (checkbox) + `.form-control` + `.btn.btn-outline-danger` | as linhas de opção de `quizzes/partials/_question-form` |
| `<x-ui.breadcrumb :items>` | `<nav><ol class="breadcrumb">` | os kickers concatenados `Curso / Módulo / Lição` (5 telas) |

### 0.3 Contrato de seletores `dusk` — RESTRIÇÃO DURA

O projeto tem **28 arquivos de teste Dusk**. Todo `dusk="..."` listado neste documento
**deve sobreviver literalmente** (mesmo elemento semântico, mesmo texto). É permitido
trocar a tag/classe do elemento, **nunca** o valor de `dusk`, nem mover o atributo para um
ancestral/descendente diferente sem checar o teste correspondente (vários testes usam
`assertSeeIn`, `click`, `type`, `value`, `assertVisible` — todos sensíveis ao elemento exato).

**Atenção especial**: `<x-ui.badge>` hoje embute `display:inline-flex` no `style` próprio e
por isso o estado oculto é expresso como `style="display:none;"` explícito
(`classroom/partials/_video|_pdf|_text-image`). Ao migrar para `.badge`, trocar por
`d-none` **quebra** `LessonPlayer.reflectCompletion()`, que faz
`badge.style.display = 'inline-flex'`. **Migrar Blade e `LessonPlayer.js` no mesmo commit.**

### 0.4 Contrato de atributos `data-*` — RESTRIÇÃO DURA

Os módulos JS ligam-se a `data-*`, não a classes. Todos devem sobreviver literalmente:

| Módulo JS | Caminho | Atributos/seletores que ele exige |
|---|---|---|
| `ModalManager` | `resources/js/modules/ModalManager.js` | `.dialog-backdrop`, `data-modal-target`, `data-modal-dismiss` |
| `ModuleReorder` | `resources/js/modules/ModuleReorder.js` | `[data-reorder-url]`, `[data-id]` |
| `LessonPlayer` | `resources/js/modules/LessonPlayer.js` | `[data-youtube-player]`, `data-lesson-id`, `data-video-id`, `data-progress-url`, `[data-mark-complete-url]`, `[data-completion-badge]` |
| `SmartInvitationForm` | `resources/js/modules/SmartInvitationForm.js` | `[data-check-email-url]`, `[data-invitation-email]`, `[data-invitation-field="new-account"]`, `[data-invitation-field="existing-account-hint"]` |
| `CsvImporter` | `resources/js/modules/CsvImporter.js` | `data-chunk-url` + os 6 `dusk="csv-*"` (liga por `[dusk=...]`!) |
| `ForumPolling` | `resources/js/modules/ForumPolling.js` | `[data-forum-polling]`, `data-fetch-url`, `data-last-id`, `[data-reply-id]` |
| `ForumReportModal` | `resources/js/modules/ForumReportModal.js` | `[data-forum-report-button]`, `data-postable-type`, `data-postable-id`, `[data-forum-report-form]`, `[data-forum-report-postable-type]`, `[data-forum-report-postable-id]` |
| `ForumEditHistory` | `resources/js/modules/ForumEditHistory.js` | `[data-edit-history-trigger]`, `[id^="edit-history-"]` |
| `AuditLogDiffModal` | `resources/js/modules/AuditLogDiffModal.js` | `[data-audit-diff-trigger]`, `data-event`, `data-old-values`, `data-new-values` + `[dusk="audit-diff-event|old|new"]` |
| `NotificationBell` | `resources/js/modules/NotificationBell.js` | `[data-notifications-bell|toggle|dropdown|badge|item|mark-all]` (camada de componente) |
| `QuizBuilder` | **`resources/js/quiz-builder.js`** (raiz, não `modules/`) | `[data-question-form]`, `[data-question-type-select]`, `[data-options-container]`, `[data-options-list]`, `[data-option-row]`, `[data-correct-checkbox]`, `[data-add-option-btn]`, `[data-remove-option-btn]`, `[data-option-template]`, `[data-essay-hint]` |
| `QuizTimer` | **`resources/js/quiz-timer.js`** (raiz) | `[data-quiz-timer]`, `data-started-at`, `data-time-limit-minutes` |

> ⚠️ **`CsvImporter.js` liga-se por `[dusk="..."]`, não por `data-*`.** Renomear qualquer
> `dusk="csv-*"` em `users/import.blade.php` quebra o importador em produção, não só o teste.

> ⚠️ **`ForumPolling.appendReply()` REPLICA o markup de `forum/partials/_reply.blade.php` em
> JavaScript, com `el.style.cssText = 'padding: 14px 16px; border: 1px solid var(--color-divider); ...'`.**
> Migrar `_reply.blade.php` para classes Bootstrap **sem** atualizar `ForumPolling.js` produz
> respostas novas visualmente divergentes das renderizadas pelo servidor. **Os dois arquivos
> formam um conjunto de conflito indivisível.**

> ⚠️ **`ModalManager` é um modal caseiro (`.dialog-backdrop`/`.dialog-body`/`.dialog-actions`),
> não o Modal do Bootstrap.** A decisão de fundação (Wave A) é: reescrever `<x-ui.modal>`
> sobre `bootstrap.Modal` **mantendo os atributos `data-modal-target`/`data-modal-dismiss`
> como aliases** (ponte em `ModalManager.js` que chama `bootstrap.Modal.getOrCreateInstance`),
> para não tocar em 5 módulos JS e ~15 telas de uma vez. Só depois, opcionalmente, migrar
> call-sites para `data-bs-toggle`/`data-bs-dismiss`.

### 0.5 🚨 dompdf — `certificates/pdf.blade.php` NÃO recebe Bootstrap

**LEIA ANTES DE TOCAR NESSE ARQUIVO.**

`resources/views/certificates/pdf.blade.php` é renderizado por
`App\Services\CertificatePdfService::generate()` através de `barryvdh/laravel-dompdf`.
O dompdf suporta apenas um subconjunto restrito de CSS 2.1:

- ❌ **não** entende CSS custom properties (`var(--color-accent)`)
- ❌ **não** entende flexbox nem grid
- ❌ **não** entende `@media`, `:root`, `color-mix()`, `rem` confiável
- ❌ **não** carrega folhas de estilo externas do build Vite
- ✅ entende `<table>`, `float`, `width` em `%`/`px`, `font-family` de fontes registradas
  (`DejaVu Sans`), cores hex literais, `@page`

**Regras obrigatórias para esse arquivo:**

1. **Nenhuma classe Bootstrap** (`container`, `row`, `col-*`, `table`, `text-center`,
   `d-flex`, `badge`, `card` …). Nenhuma. O dompdf ignora silenciosamente e o certificado sai
   desalinhado em produção sem erro.
2. **Nenhum `@vite`**, nenhum `<link rel=stylesheet>`.
3. Manter o `<style>` embutido no `<head>` com **cores hex literais** e layout **baseado em
   `<table>`**, exatamente como está hoje.
4. A única mudança permitida nesta migração é extrair o shell para `<x-layout.print>`
   — que também **não pode** conter Bootstrap.
5. Se o design system mudar as cores, **duplicar os valores hex manualmente** nesse arquivo
   (hoje o `.kicker` usa `#7a5cff`, divergente do `--color-accent: #ec3013` — divergência
   pré-existente, corrigir para `#ec3013` como parte da migração visual, **sem `var()`**).
6. Verificação: `vendor/bin/sail artisan test --compact --filter=CertificatePdf` e inspeção
   visual do PDF gerado. Não há teste Dusk (o arquivo não tem nenhum `dusk=`).

### 0.6 Legenda das tabelas por tela

- **Rotas**: nome(s) de rota em `routes/web.php` / `routes/auth.php` que renderizam a view.
- **Papéis**: `admin` / `gestor` / `aluno` / `guest` / `público` (sem middleware algum).
- **Inline `style=`**: contagem literal de ocorrências de `style="` no arquivo.
- **Teste Dusk**: arquivo(s) em `tests/Browser/` que visitam a rota. Comando padrão:
  `vendor/bin/sail artisan dusk --filter=<NomeDoTeste>`.

---

## 1. Módulo `auth/` — Autenticação (3 telas)

Todas em `layouts.guest` (split 42%/58%), middleware `guest`, papel **guest**.
Padrão comum: kicker + `<h1>` + `<form style="display:flex;flex-direction:column;gap:16px">`
+ `<x-ui.input>` + `<x-ui.button block>` + link auxiliar.

### 1.1 `resources/views/auth/login.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `login` (GET/POST `/login`) |
| Layout | `layouts.guest` |
| Papéis | guest |
| Padrões UI | formulário simples, checkbox "lembrar-me", link auxiliar |
| Inline `style=` | 6 |
| JS | nenhum (só `HttpClient` global) |
| Teste Dusk | `tests/Browser/Auth/LoginTest.php` → `vendor/bin/sail artisan dusk --filter=LoginTest` |

**Composição alvo:**
`layouts.guest` → `<x-layout.auth-heading kicker="Acesso" title="Entrar na plataforma">`
+ `<form class="vstack gap-3" dusk="login-form">`
+ `<x-ui.input>` ×2 (`.form-floating` opcional; obrigatório `.form-label` + `.form-control` + `.invalid-feedback` com `is-invalid` derivado de `@error`)
+ **`<x-ui.checkbox name="remember" label="Lembrar-me" dusk="login-remember">`** (`.form-check`)
+ `<x-ui.button type="submit" block>` → `.btn.btn-primary.w-100`
+ link "Esqueceu sua senha?" → `.link-accent.small.text-center.fw-semibold.text-decoration-none`
+ `<x-layout.alerts>` do layout deve renderizar `.alert.alert-danger` para o erro de credenciais.

**Dusk imutáveis:** `login-form`, `login-email`, `login-password`, `login-remember`, `login-submit`, `forgot-password-link`.

### 1.2 `resources/views/auth/forgot-password.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `password.request` (GET), posta em `password.email` |
| Layout | `layouts.guest` |
| Papéis | guest |
| Padrões UI | formulário de 1 campo + parágrafo explicativo |
| Inline `style=` | 6 |
| JS | nenhum |
| Teste Dusk | `tests/Browser/Auth/LoginTest.php` (visita `/forgot-password`) |

**Composição alvo:** `<x-layout.auth-heading kicker title description>` + `<form class="vstack gap-3">`
+ `<x-ui.input type="email">` + `<x-ui.button block>` + link `.link-accent.small.text-center`.
Descrição vira `.text-body-secondary.small.lh-base`.

**Dusk imutáveis:** `forgot-password-form`, `forgot-password-email`, `forgot-password-submit`, `back-to-login-link`.

### 1.3 `resources/views/auth/reset-password.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `password.reset` (GET `/reset-password/{token}`), posta em `password.store` |
| Layout | `layouts.guest` |
| Papéis | guest |
| Padrões UI | formulário 3 campos + `<input type="hidden" name="token">` |
| Inline `style=` | 4 |
| JS | nenhum |
| Teste Dusk | `tests/Browser/Auth/LoginTest.php` (`visit(route('password.reset', ...))`) |

**Composição alvo:** `<x-layout.auth-heading>` + `<form class="vstack gap-3">` + hidden token
+ `<x-ui.input>` ×3 + `<x-ui.button block>`.

**Dusk imutáveis:** `reset-password-form`, `reset-password-email`, `reset-password-password`, `reset-password-password-confirmation`, `reset-password-submit`.

---

## 2. Módulo `landing/` — Página pública (1 tela)

### 2.1 `resources/views/landing/show.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `landing.show` (GET `/`) |
| Layout | **nenhum** — documento HTML standalone com `@vite` próprio |
| Papéis | público (sem middleware) |
| Padrões UI | navbar, hero, grid de 3 features (`repeat(auto-fit, minmax(240px,1fr))`), CTA, footer |
| Inline `style=` | 23 (a maior densidade fora de `audit-logs`) |
| JS | nenhum (mas carrega `app.js` inteiro por causa de `<x-help-button>`/`ModalManager`) |
| Teste Dusk | `tests/Browser/BladeComponentsTest.php`, `tests/Browser/LayoutRenderingTest.php`, `tests/Browser/ExampleSmokeTest.php`, `tests/Browser/HelpCenterDuskTest.php` (indireto) |

**Composição alvo:**
- Extrair o shell para **`<x-layout.public title>`** (compartilhado com `public/certificates/show`), que emite `<!doctype html>`, `<meta csrf-token>`, `@vite`, e `<body class="bg-body text-body">`.
- Navbar → **`<nav class="navbar navbar-expand-lg border-bottom bg-body-tertiary">`** com `.container-fluid`, `.navbar-brand`, e à direita `<x-help-button key="landing">` + `<a class="btn btn-primary btn-sm">`.
- Hero → `<section class="container py-6 text-center" style="max-width:960px">` substituído por `.container` + `.py-5` + `.text-center`; kicker → `<span class="text-uppercase small fw-bold text-accent letter-spacing-1">`; `<h1 class="display-5 fw-bold">`; lead → `.lead.text-body-secondary.mx-auto`.
- Faixa de features → `.bg-body-tertiary.border-top.border-bottom.py-5` + `.container` + **`.row.row-cols-1.row-cols-md-3.g-4`** + `<x-ui.card>` por feature (3 cards) — **substituir o grid CSS manual**.
- CTA "Recebeu um convite?" → `.container.py-5.text-center` + `<x-ui.card>`.
- Footer → `<x-layout.public-footer>` (`.border-top.py-4.text-center.small.text-body-secondary`).

**Dusk imutáveis:** `landing-headline`, `landing-login-link`, `landing-cta-login`.

---

## 3. Módulo `convite/` — Convite público (1 tela)

### 3.1 `resources/views/convite/show.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `invitation.show` (GET `/convite/{token}`), posta na URL literal `/convite/{token}` (`invitation.store`); consulta `invitation.check-email` via AJAX |
| Layout | `layouts.guest` |
| Papéis | guest |
| Padrões UI | formulário adaptativo (campos aparecem/desaparecem conforme o e-mail já existir), `<x-help-button>` |
| Inline `style=` | 6 |
| JS | **`SmartInvitationForm.js`** — `data-check-email-url`, `data-invitation-email`, `data-invitation-field="new-account"` (×3 wrappers), `data-invitation-field="existing-account-hint"` |
| Teste Dusk | `tests/Browser/MultiOrgEnrollmentTest.php` → `vendor/bin/sail artisan dusk --filter=MultiOrgEnrollmentTest` |

**Composição alvo:**
`<x-layout.auth-heading kicker="Convite" :title="'Matrícula em '.$invitationLink->course->title" description>`
com slot `aside` contendo `<x-help-button key="invitation.show">` →
`.d-flex.justify-content-between.align-items-start.gap-2.mb-4`.
Form → `<form class="vstack gap-3" dusk="invitation-form" data-check-email-url=...>`.
Cada `<div data-invitation-field="new-account">` **permanece exatamente como está** (o JS
alterna `style.display`); envolver com `<x-ui.input>` dentro. A dica de conta existente
(`data-invitation-field="existing-account-hint"`) vira `.form-text.small.text-body-secondary`
**mantendo `style="display:none;"` inline** — `SmartInvitationForm.js` alterna
`element.style.display`, então **não trocar por `d-none`** sem alterar o módulo JS.
Botão → `.btn.btn-primary.w-100`.

**Dusk imutáveis:** `invitation-form`, `invitation-email`, `invitation-existing-account-hint`, `invitation-name`, `invitation-cpf`, `invitation-password`, `invitation-password-confirmation`, `invitation-submit`.

---

## 4. Módulo `dashboard/` — Dashboard administrativo (1 tela)

### 4.1 `resources/views/dashboard/index.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `admin.dashboard` (GET `/admin/dashboard`) |
| Layout | `layouts.app` |
| Papéis | admin, gestor (`role:admin\|gestor`) |
| Padrões UI | grid de 4 stat-cards, barra de exportação (2 links CSV), tabela de matrículas recentes |
| Inline `style=` | 9 |
| JS | nenhum |
| Teste Dusk | `tests/Browser/DashboardDuskTest.php`, `NavigationMenuDuskTest.php`, `NotificationBellTest.php`, `MultiTenantStudentImportTest.php` → `vendor/bin/sail artisan dusk --filter=DashboardDuskTest` |

**Composição alvo:**
- `<x-layout.page-header title="Dashboard">`
- Stat cards: `<div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3 mb-4">` + `<div class="col">` + `<x-ui.stat-card>` (interno: `.card.h-100` + `.card-body` + `.text-uppercase.small.text-body-secondary` + `.display-6.fw-bold` + `<x-ui.badge variant="success">` para o delta). **Substituir o `grid-template-columns: repeat(4, 1fr)` fixo** (hoje quebra em mobile).
- `<x-layout.section-header title="Matrículas recentes">` com slot `actions` = `<div class="btn-group">` contendo 2 `<x-ui.button variant="secondary" href icon="download">` → `.btn.btn-outline-secondary` (remover os `style=` de 190 caracteres duplicados nos dois `<a>`).
- `<x-ui.data-table striped hover responsive :headers="['Nome','Curso','Status']" dusk="recent-enrollments-table">` + `<x-ui.badge>` na coluna Status + `<x-ui.empty-state colspan="3">`.
- Remover a classe órfã `.admin-dashboard-container` (manter só o `dusk="admin-dashboard"` no wrapper).

**Dusk imutáveis:** `admin-dashboard`, `stat-active-students`, `stat-certificates-issued`, `stat-completion-rate`, `stat-courses-count`, `export-enrollments-csv`, `export-certificates-csv`, `recent-enrollments-table`, `enrollment-row`.

---

## 5. Módulo `organizations/` — CRUD de Organizações (4 telas)

Todas `layouts.app`, `role:admin`.

### 5.1 `organizations/index.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `organizations.index` |
| Papéis | admin |
| Padrões UI | page-header + CTA, alerta de impersonação, tabela + badges + 3 ações por linha, paginação |
| Inline `style=` | 13 |
| JS | nenhum (o alerta usa `<x-ui.alert dismissable>` — ver BUG-004) |
| Teste Dusk | `OrganizationCrudTest.php`, `ImpersonateOrgTest.php`, `Auth/LoginTest.php` |

**Composição alvo:**
`<x-layout.page-header kicker="Administração" title="Organizações">` + slot `actions` = `<x-ui.button href dusk="new-organization">`
· `<x-ui.alert variant="warning" dismissable>` → `.alert.alert-warning.alert-dismissible.fade.show` + `<button class="btn-close" data-bs-dismiss="alert">` (**isto corrige BUG-004**, cujo botão de dismiss é hoje inerte por falta do JS do Bootstrap); o form "Sair do contexto" embutido vira `<x-ui.delete-button>` com `.btn.btn-link.p-0.text-decoration-underline`
· `<x-ui.data-table striped hover responsive :headers="['Nome','Slug','CNPJ','Status','Ações']">`
· coluna Ações → `<div class="btn-group btn-group-sm">` com o form de impersonate (`.btn.btn-outline-secondary`), `<x-ui.button size="sm">` Editar e `<x-ui.delete-button>` Remover (com `<x-ui.confirm-modal>`)
· `<x-ui.empty-state colspan="5">` · `<x-ui.pagination :paginator="$organizations">`.

**Dusk imutáveis:** `new-organization`, `exit-impersonation-form`, `exit-impersonation`, `organization-row-{id}`, `impersonate-form-{id}`, `impersonate-{id}`, `edit-organization-{id}`, `delete-form-{id}`, `delete-organization-{id}`.

### 5.2 `organizations/_form.blade.php` — **PARTIAL COMPARTILHADO (conflito)**

| Campo | Valor |
|---|---|
| Incluído por | `organizations/create.blade.php`, `organizations/edit.blade.php` |
| Inline `style=` | 6 · **Dusk: nenhum** |
| Padrões UI | 3 inputs, 1 select, 1 file input com preview do arquivo atual |

**Composição alvo:** `<x-ui.field-stack>` (`.vstack.gap-3` + `.col-lg-7`) + `<x-ui.input>` ×3
+ `<x-ui.select>` + **`<x-ui.file-input name="logo" label="Logo" :current="$organization->logo_path">`**
(`.form-control` + `.form-text` + `.invalid-feedback`). Remover a classe órfã `.field`.

### 5.3 `organizations/create.blade.php` / 5.4 `organizations/edit.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `organizations.create` / `organizations.edit` |
| Inline `style=` | 1 cada |
| Padrões UI | `<x-ui.card>` + `<form enctype="multipart/form-data">` + `@include('organizations._form')` + submit/cancel |
| Teste Dusk | `OrganizationCrudTest.php` |

**Composição alvo (idêntica para os dois):** `<x-ui.card title kicker>` → `.card` + `.card-header` + `.card-body`
+ `<form enctype="multipart/form-data" dusk="organization-form">`
+ `@include('organizations._form')`
+ **`<x-ui.form-actions>`** contendo `<x-ui.button type="submit">` + `<x-ui.button variant="secondary" href>`.

**Dusk imutáveis:** `organization-form`, `organization-submit` (ambos os arquivos).

---

## 6. Módulo `users/` — Alunos & Gestores (4 telas)

Todas `layouts.app`, `role:admin|gestor`.

### 6.1 `users/index.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `users.index` |
| Inline `style=` | 13 |
| JS | nenhum |
| Teste Dusk | `UserManagementTest.php`, `NavigationMenuDuskTest.php` → `--filter=UserManagementTest` |
| ⚠️ | Ver `spec/bugs/BUG-005-users-index-unreachable-for-admin-without-impersonation.md` — problema de rota/scoping, **não** de UI; não tentar resolver na migração |

**Composição alvo:** `<x-layout.page-header kicker="Organização" title="Alunos & Gestores">`
+ slot `actions` = `<div class="btn-group">` (Importar CSV `.btn-outline-secondary` + Novo Usuário `.btn-primary`)
· `<x-ui.data-table striped hover responsive :headers="['Nome','E-mail','CPF','Papel','Status','Ações']">`
· `<x-ui.badge>` para Papel e Status · Ações em `.btn-group.btn-group-sm` + `<x-ui.delete-button>` + `<x-ui.confirm-modal>`
· `<x-ui.empty-state colspan="6">` · `<x-ui.pagination>`.
**Recomendado (novo, ainda inexistente):** `<x-ui.filter-bar>` com `.input-group` de busca + `.form-select` de papel/status — o mockup `08-gestao-alunos` prevê filtros que a tela atual não tem; adicionar **sem** remover nenhum `dusk`.

**Dusk imutáveis:** `import-users`, `new-user`, `user-row-{id}`, `user-status-{id}`, `edit-user-{id}`, `delete-form-{id}`, `delete-user-{id}`.

### 6.2 `users/create.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `users.create` · Inline `style=`: 2 · JS: nenhum |
| Teste Dusk | `UserManagementTest.php`, `NavigationMenuDuskTest.php` |

**Composição alvo:** `<x-ui.card title="Novo Usuário" kicker>` + `<form dusk="user-form">`
+ `<x-ui.field-stack>` com `<x-ui.input>` ×3, `<x-ui.select name="role">`, `<x-ui.input type="password">` ×2
+ `<x-ui.form-actions>`. **Dusk:** `user-form`, `user-submit`.

### 6.3 `users/edit.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `users.edit` · Inline `style=`: 2 · JS: nenhum |
| Teste Dusk | `UserManagementTest.php` |

**Composição alvo:** igual a 6.2 + `<x-ui.select name="status" dusk="user-status-select">` + `<x-ui.input name="reason" dusk="user-status-reason">`.
Sugestão: agrupar Status/Motivo num `<x-ui.card>` secundário ou `.row.g-3` de 2 colunas.
**Dusk:** `user-form`, `user-status-select`, `user-status-reason`, `user-submit`.

### 6.4 `users/import.blade.php` — **JS-pesado**

| Campo | Valor |
|---|---|
| Rotas | `users.import.create`; posta em chunks para `users.import.chunk` |
| Inline `style=` | 11 |
| JS | **`CsvImporter.js`** — `data-chunk-url` + **liga por `[dusk="csv-import-form"]`, `[dusk="csv-course-select"]`, `[dusk="csv-file-input"]`, `[dusk="csv-import-progress-wrapper"]`, `[dusk="csv-import-progress-bar"]`, `[dusk="csv-import-progress-text"]`, `[dusk="csv-import-results"]`** |
| Teste Dusk | `MultiTenantStudentImportTest.php` → `vendor/bin/sail artisan dusk --filter=MultiTenantStudentImportTest` |

**Composição alvo:** `<x-ui.card title kicker>` + `.alert.alert-info` para as instruções de cabeçalho (com `<code>`)
+ `<form class="vstack gap-3" dusk="csv-import-form" data-chunk-url>`
+ `<x-ui.select dusk="csv-course-select">` + **`<x-ui.file-input dusk="csv-file-input" accept=".csv,text/csv">`**
+ **`<x-ui.progress dusk-wrapper="csv-import-progress-wrapper" dusk-bar="csv-import-progress-bar" dusk-text="csv-import-progress-text">`** → `.progress` + `.progress-bar` + `.form-text`
+ `<div dusk="csv-import-results" class="small">` (resultados) — considerar renderizar como `.alert.alert-success`/`.alert-danger` via JS
+ `<x-ui.form-actions>`.

> ⚠️ `CsvImporter.js` alterna `wrapper.style.display`/`results.style.display` e escreve
> `bar.style.width`. **Manter `style="display:none;"` inline** nos wrappers e **não** trocar
> por `d-none`, ou atualizar `CsvImporter.js` no mesmo commit para usar `classList`.

**Dusk imutáveis:** `csv-import-form`, `csv-course-select`, `csv-file-input`, `csv-import-progress-wrapper`, `csv-import-progress-bar`, `csv-import-progress-text`, `csv-import-results`, `csv-import-submit`.

---

## 7. Módulo `courses/` — Cursos, Módulos, Matrículas, Convites, Regras (14 telas)

### 7.1 `courses/index.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `courses.index` · Layout `layouts.app` · Papéis admin, gestor |
| Inline `style=` | 10 · JS: nenhum |
| Teste Dusk | `CourseManagementTest.php`, `ImpersonateOrgTest.php` |

**Composição alvo:** `<x-layout.page-header kicker="Gestão" title="Cursos">` + `actions` = `<x-ui.button dusk="new-course">`
· `<x-ui.data-table striped hover responsive :headers="['Título','Carga Horária','Status','Ações']">`
· `<x-ui.badge>` Publicado/Rascunho
· **coluna Ações tem 4 controles** — usar `<div class="btn-group btn-group-sm">` (hoje é `display:flex;gap:8px` num `<td>`, que quebra em telas estreitas); considerar `.dropdown` (`<x-ui.action-menu>`) para Módulos/Regras/Editar/Remover
· `<x-ui.delete-button>` + `<x-ui.confirm-modal>` · `<x-ui.empty-state colspan="4">` · `<x-ui.pagination>`.

**Dusk imutáveis:** `new-course`, `course-row-{id}`, `manage-modules-{id}`, `manage-completion-rules-{id}`, `edit-course-{id}`, `delete-form-{id}`, `delete-course-{id}`.

### 7.2 `courses/_form.blade.php` — **PARTIAL COMPARTILHADO (conflito serializado)**

| Campo | Valor |
|---|---|
| Incluído por | `courses/create.blade.php`, `courses/edit.blade.php` |
| Inline `style=` | 4 · **Dusk: nenhum** |
| Padrões UI | 2 inputs, 1 textarea, 1 checkbox |

**Composição alvo:** `<x-ui.field-stack>` + `<x-ui.input name="title">` + `<x-ui.textarea name="description">`
+ `<x-ui.input type="number" name="workload_hours">` + **`<x-ui.checkbox name="is_published" label="Publicado" :checked="old('is_published', $course->is_published)">`**
(`.form-check.form-switch` é preferível para um flag de publicação). Remover `.field` órfã.

### 7.3 `courses/create.blade.php` / 7.4 `courses/edit.blade.php`

Idênticas em forma: `<x-ui.card title kicker="Cursos">` + `<form dusk="course-form">` + `@include('courses._form')` + `<x-ui.form-actions>`.
Inline `style=`: 1 cada. Teste: `CourseManagementTest.php`, `ImpersonateOrgTest.php`.
**Dusk:** `course-form`, `course-submit`.

### 7.5 `courses/modules/index.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `courses.modules.index` · Papéis admin, gestor |
| Inline `style=` | 5 · JS: **`ModuleReorder.js`** (via o partial `_list`) |
| Teste Dusk | `ModuleReorderTest.php`, `CourseManagementTest.php` |

**Composição alvo:** `<x-layout.page-header :kicker="$course->title" title="Módulos">` + `actions` = `<div class="btn-group">` (Voltar + Novo Módulo)
· `.form-text` / `.alert.alert-light` para a dica de arrastar
· `<div id="module-list-container" dusk="module-list-container">` **mantido literalmente** + `@include('courses.modules._list')`.

**Dusk imutáveis:** `new-module`, `module-list-container`.

### 7.6 `courses/modules/_list.blade.php` — **PARTIAL + JS de reorder**

| Campo | Valor |
|---|---|
| Incluído por | `courses/modules/index.blade.php` (e re-renderizado como resposta parcial pelo controller) |
| Inline `style=` | 6 |
| JS | **`ModuleReorder.js`** — `data-reorder-url` no `<ul>`, `data-id` + `draggable="true"` em cada `<li>` |
| Teste Dusk | `ModuleReorderTest.php` → `vendor/bin/sail artisan dusk --filter=ModuleReorderTest` |

**Composição alvo:** **`<x-ui.sortable-list :url="route('modules.reorder', $course)" dusk="module-list">`**
→ `<ul class="list-group" data-reorder-url=... dusk="module-list">`
+ **`<x-ui.sortable-item :id="$module->id" dusk="module-row-{{ $module->id }}">`**
→ `<li class="list-group-item d-flex justify-content-between align-items-center gap-3" data-id draggable="true" style="cursor:grab">`
(`cursor:grab` pode ficar inline ou virar `.cursor-grab` utilitária custom)
+ handle `⠿` → `<span class="text-body-tertiary" aria-hidden="true">`
+ ações em `.btn-group.btn-group-sm` + `<x-ui.delete-button>`
+ `@empty` → `<li class="list-group-item text-center text-body-secondary border-dashed">`.

**Dusk imutáveis:** `module-list`, `module-row-{id}`, `manage-lessons-{id}`, `edit-module-{id}`, `delete-module-form-{id}`, `delete-module-{id}`.

### 7.7 `courses/modules/_form.blade.php` — **PARTIAL COMPARTILHADO (conflito)**

Incluído por `courses/modules/create.blade.php` e `courses/modules/edit.blade.php`.
1 inline style · nenhum `dusk`.
**Composição alvo:** `<x-ui.field-stack>` + `<x-ui.input name="title">` + `<x-ui.textarea name="description">`.

### 7.8 `courses/modules/create.blade.php` / 7.9 `courses/modules/edit.blade.php`

`<x-ui.card title kicker>` + `<form dusk="module-form">` + `@include('courses.modules._form')` + `<x-ui.form-actions>`.
1 inline style cada. Teste: `CourseManagementTest.php`. **Dusk:** `module-form`, `module-submit`.

### 7.10 `courses/enrollments/index.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `courses.enrollments.index` (+ `.store`, `.destroy`) · Papéis admin, gestor |
| Inline `style=` | 14 · JS: nenhum |
| Teste Dusk | `UserManagementTest.php` → `--filter=UserManagementTest` |

**Composição alvo:** `<x-layout.page-header :kicker="$course->title" title="Matrículas">` + `actions` = botão Voltar
· `<x-ui.card title="Matricular manualmente" kicker="RF21">` contendo `<form class="row g-2 align-items-end">` (inline form Bootstrap) com `<x-ui.input type="number">` em `.col-md-4` + `<x-ui.button>` em `.col-auto`
· `<x-ui.data-table striped hover responsive :headers="['Aluno','E-mail','Status','Matriculado em','Ações']">`
· `<x-ui.badge>` no Status · `<x-ui.delete-button label="Revogar">` + `<x-ui.confirm-modal>`
· `<x-ui.empty-state colspan="5">` · `<x-ui.pagination>`.

**Dusk imutáveis:** `manual-enroll-form`, `manual-enroll-user-id`, `manual-enroll-submit`, `enrollment-row-{id}`, `revoke-enrollment-form-{id}`, `revoke-enrollment-{id}`.

### 7.11 `courses/invitation-links/index.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `courses.invitation-links.index` (destroy: `invitation-links.destroy`) · Papéis admin, gestor |
| Inline `style=` | 12 · JS: nenhum |
| Teste Dusk | **nenhum teste Dusk cobre esta tela** — cobertura só em Feature tests. Verificação: `vendor/bin/sail artisan test --compact --filter=InvitationLink` + smoke manual |

**Composição alvo:** `<x-layout.page-header :kicker="$course->title" title="Links de Convite">` + `actions` = `.btn-group` (Voltar + Novo Link)
· `<x-ui.data-table striped hover responsive :headers="['Token','Usos','Expira em','Status','Ações']">`
· coluna Token → **`.input-group.input-group-sm`** com `<input readonly class="form-control font-monospace">` + `<button class="btn btn-outline-secondary" data-copy>` (melhoria: hoje é um `<code>` com `word-break:break-all`)
· `<x-ui.badge>` de 3 estados (Ativo/Revogado/Expirado) · `<x-ui.delete-button label="Revogar">`
· `<x-ui.empty-state colspan="5">` · `<x-ui.pagination>`.

**Dusk imutáveis:** `new-invitation-link`, `invitation-link-row-{id}`, `revoke-form-{id}`, `revoke-invitation-link-{id}`.

### 7.12 `courses/invitation-links/create.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `courses.invitation-links.create` · Inline `style=`: 2 · JS: nenhum · Sem teste Dusk |

**Composição alvo:** `<x-ui.card title kicker>` + `<form dusk="invitation-link-form">` + `<x-ui.field-stack>`
com `<x-ui.input type="number" name="max_uses">` + `<x-ui.input type="datetime-local" name="expires_at">`
+ `<x-ui.form-actions>`. **Dusk:** `invitation-link-form`, `invitation-link-submit`.

### 7.13 `courses/completion-rules/index.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `courses.completion-rules.index` (+ `.store`, `.destroy`) · Papéis admin, gestor |
| Inline `style=` | 15 · JS: nenhum |
| Teste Dusk | `CourseCompletionRuleTest.php` → `vendor/bin/sail artisan dusk --filter=CourseCompletionRuleTest` |

**Composição alvo:** `<x-layout.page-header :kicker="$course->title" title="Regras de Conclusão">` + `actions` = Voltar
· `<x-ui.card title="Nova regra" kicker="UC13">` + `<form class="row g-3 align-items-end">`:
  `<x-ui.select name="rule_type">` em `.col-md-4`, `<x-ui.select name="target_id">` com `<optgroup>` em `.col-md-4`
  (**manter os `<optgroup>` — `.form-select` os suporta nativamente**), `<x-ui.input type="number" name="required_percentage">` em `.col-md-2`, `<x-ui.button>` em `.col-auto`
· `<x-ui.data-table striped hover responsive :headers="['Tipo','Alvo','Percentual Exigido','Ações']">` + `<x-ui.badge variant="outline">` (→ `.badge.text-bg-light.border`)
· `<x-ui.delete-button>` · `<x-ui.empty-state colspan="4">`.

**Dusk imutáveis:** `completion-rule-form`, `completion-rule-type`, `completion-rule-target`, `completion-rule-percentage`, `completion-rule-submit`, `completion-rule-row-{id}`, `delete-completion-rule-form-{id}`, `delete-completion-rule-{id}`.

---

## 8. Módulo `modules/lessons/` — Lições (4 telas)

### 8.1 `modules/lessons/index.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `modules.lessons.index` · Papéis admin, gestor |
| Inline `style=` | 11 · JS: **`ModuleReorder.js`** (`data-reorder-url` no `<ul>`, `data-id` nos `<li>`) |
| Teste Dusk | `CourseManagementTest.php`, `LessonMultimediaTest.php` |

**Composição alvo:** `<x-layout.page-header :kicker="$module->course->title.' / '.$module->title" title="Lições">`
— preferir **`<x-ui.breadcrumb :items="[curso, módulo]">`** acima do `<h1>`
+ `actions` = `.btn-group` (Voltar + Nova Lição)
· `<x-ui.sortable-list :url="route('lessons.reorder', $module)" dusk="lesson-list">` + `<x-ui.sortable-item>` por lição (mesmo componente do §7.6)
· `<x-ui.badge variant="outline">` de tipo · `<x-ui.delete-button>`.

**Dusk imutáveis:** `new-lesson`, `lesson-list`, `lesson-row-{id}`, `edit-lesson-{id}`, `delete-lesson-form-{id}`, `delete-lesson-{id}`.

### 8.2 `modules/lessons/_form.blade.php` — **PARTIAL COMPARTILHADO + JS inline (conflito crítico)**

| Campo | Valor |
|---|---|
| Incluído por | `modules/lessons/create.blade.php`, `modules/lessons/edit.blade.php` |
| Inline `style=` | 19 (o partial mais pesado do projeto) |
| JS | **script inline em `@push('scripts')`** — seleciona por `[dusk="lesson-type-select"]`, `[data-lesson-content-fields]`, `[dusk="lesson-youtube-input"]`, `[data-youtube-preview-wrapper]`, `[data-youtube-preview-frame]`; alterna `element.style.display` |
| Teste Dusk | `LessonMultimediaTest.php`, `CourseManagementTest.php` → `--filter=LessonMultimediaTest` |

**Composição alvo:**
`<x-ui.field-stack>` + `<x-ui.input name="title">` + `<x-ui.select name="type" dusk="lesson-type-select">`
+ `.form-text` para a nota sobre Quiz
+ `<div id="lesson-content-fields" data-lesson-content-fields class="vstack gap-3">` **(atributos mantidos literalmente)** contendo:
  `<x-ui.textarea name="content_text">`, `<x-ui.file-input name="image" dusk="lesson-image-input" :current="$lesson->image_path">`,
  `<x-ui.file-input name="pdf" dusk="lesson-pdf-input" :current="$lesson->pdf_path">`,
  `<x-ui.input name="youtube_url" dusk="lesson-youtube-input">`,
  preview → `.ratio.ratio-16x9` (**substitui `aspect-ratio: 16/9` manual**) dentro de `<div data-youtube-preview-wrapper style="display:none">` com `<iframe data-youtube-preview-frame dusk="youtube-preview" class="rounded-0 border">`
+ `<x-ui.checkbox name="is_published" label="Publicado">`.

> ⚠️ O script inline faz `contentFields.style.display = 'none'|'flex'` e
> `previewWrapper.style.display = 'flex'|'none'`. Ao trocar o wrapper para `.vstack`
> (que já é `display:flex`), o script continua funcionando; **não** trocar por `d-none`.
> Idealmente, extrair esse script para `resources/js/modules/LessonForm.js` como parte da
> Wave E e trocar por `classList.toggle('d-none')` — mas então **`_form.blade.php` e o novo
> módulo JS formam um conjunto de conflito**.

**Dusk imutáveis:** `lesson-type-select`, `lesson-image-input`, `lesson-pdf-input`, `lesson-youtube-input`, `youtube-preview`.

### 8.3 `modules/lessons/create.blade.php` / 8.4 `modules/lessons/edit.blade.php`

`<x-ui.card title kicker>` + `<form enctype="multipart/form-data" dusk="lesson-form">` + `@include('modules.lessons._form')` + `<x-ui.form-actions>`.
1 inline style cada. Teste: `CourseManagementTest.php`, `LessonMultimediaTest.php`.
**Dusk:** `lesson-form`, `lesson-submit`.

---

## 9. Módulo `quizzes/` — Autoria e correção (6 telas)

### 9.1 `quizzes/create.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `quizzes.create` (GET `lessons/{lesson}/quiz/create`), posta em `quizzes.store` · Papéis admin, gestor |
| Inline `style=` | 8 · JS: nenhum |
| Teste Dusk | `EssayGradingScreenTest.php` → `--filter=EssayGradingScreenTest` |

**Composição alvo:** `<x-ui.card>` com `<x-ui.breadcrumb>` no header (curso / módulo / lição)
+ `<form dusk="quiz-form">` + `<x-ui.field-stack>`:
`<x-ui.input dusk="quiz-title-input">`, `<x-ui.textarea name="instructions">`,
**`<x-ui.checkbox name="allow_retries" dusk="quiz-allow-retries">`** (`.form-check.form-switch`),
`<x-ui.input type="number" dusk="quiz-max-attempts">`, `<x-ui.input type="number" dusk="quiz-time-limit">`,
**`<x-ui.checkbox name="show_correct_answers" dusk="quiz-show-correct-answers">`**,
`<x-ui.input type="number" dusk="quiz-min-score">`
+ `<x-ui.form-actions>`.
Sugestão de agrupamento: `.row.g-3` de 2 colunas para os 3 campos numéricos.

**Dusk imutáveis:** `quiz-form`, `quiz-title-input`, `quiz-allow-retries`, `quiz-max-attempts`, `quiz-time-limit`, `quiz-show-correct-answers`, `quiz-min-score`, `quiz-submit`.

### 9.2 `quizzes/edit.blade.php` — **JS-pesado (modais + reorder)**

| Campo | Valor |
|---|---|
| Rotas | `quizzes.edit`/`quizzes.update` + `quiz-questions.store|update|destroy|reorder` · Papéis admin, gestor |
| Inline `style=` | 12 |
| JS | `ModalManager` (`data-modal-target="question-create-modal"` e `question-edit-modal-{id}`), `QuizBuilder` e `ModuleReorder` via partials |
| Teste Dusk | `EssayGradingScreenTest.php` |

**Composição alvo:**
1. `<x-ui.card title="Editar Quiz">` + `<x-ui.breadcrumb>` + o mesmo formulário de 9.1 (metadados) + `<x-ui.form-actions>`.
2. `<x-layout.section-header title="Questões">` + `actions` = `<x-ui.button data-modal-target="question-create-modal" dusk="new-question">`.
3. `.form-text` da dica de arrastar.
4. `@include('quizzes.partials._question-list')`.
5. `<x-ui.modal id="question-create-modal" size="lg">` + `@include(..._question-form, [...])`.
6. `@foreach($questions)` `<x-ui.modal id="question-edit-modal-{id}" size="lg">` + `@include(..._question-form)`.

> ⚠️ **N modais por página.** Com Bootstrap Modal, N modais no DOM continuam funcionando,
> mas cada `<x-ui.modal>` deve virar `.modal.fade` + `.modal-dialog.modal-lg` + `.modal-content`
> e o `data-modal-target` continuar sendo traduzido pela ponte do `ModalManager` (§0.4).
> Alternativa recomendada (fora do escopo mínimo): 1 modal único preenchido por AJAX.

**Dusk imutáveis:** `quiz-form`, `quiz-title-input`, `quiz-allow-retries`, `quiz-max-attempts`, `quiz-time-limit`, `quiz-show-correct-answers`, `quiz-min-score`, `quiz-submit`, `new-question`.

### 9.3 `quizzes/partials/_question-list.blade.php` — **PARTIAL + reorder**

| Campo | Valor |
|---|---|
| Incluído por | `quizzes/edit.blade.php` · Inline `style=`: 7 |
| JS | **`ModuleReorder.js`** (`data-reorder-url` → `quiz-questions.reorder`, `data-id`) |
| Teste Dusk | `EssayGradingScreenTest.php` |

**Composição alvo:** reutilizar **`<x-ui.sortable-list>` / `<x-ui.sortable-item>`** (§7.6) —
`.list-group` + `.list-group-item` + truncamento do enunciado via `.text-truncate` (substitui
`overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:420px`)
+ `<x-ui.badge variant="outline">` de tipo + `.btn-group.btn-group-sm` (Editar abre o modal, Remover via `<x-ui.delete-button>`)
+ `@empty` → `.list-group-item.text-center.text-body-secondary`.

**Dusk imutáveis:** `question-list`, `question-row-{id}`, `edit-question-{id}`, `delete-question-form-{id}`, `delete-question-{id}`, `question-list-empty`.

### 9.4 `quizzes/partials/_question-form.blade.php` — **PARTIAL + `QuizBuilder.js` (conflito crítico)**

| Campo | Valor |
|---|---|
| Incluído por | `quizzes/edit.blade.php` **N+1 vezes** (1 create + 1 por questão), com `$formSuffix` distinto |
| Inline `style=` | 18 |
| JS | **`resources/js/quiz-builder.js`** — `[data-question-form]`, `[data-question-type-select]`, `[data-essay-hint]`, `[data-options-container]`, `[data-options-list]`, `[data-option-row]`, `[data-correct-checkbox]`, `[data-add-option-btn]`, `[data-remove-option-btn]`, `[data-option-template]` |
| Teste Dusk | `EssayGradingScreenTest.php` |

**Composição alvo:**
`<form dusk="question-form-{suffix}" data-question-form="{suffix}">`
+ `<x-ui.textarea name="question_text" dusk="question-text-{suffix}">`
+ `<x-ui.select name="type" data-question-type-select="{suffix}" dusk="question-type-{suffix}">`
+ `<p data-essay-hint="{suffix}" class="form-text" style="display:none">` (**manter `style="display:none"`** — `QuizBuilder` alterna `style.display`)
+ `<div data-options-container="{suffix}" class="vstack gap-2">` + `.form-label` "Opções"
+ `<div data-options-list="{suffix}" class="vstack gap-2">` com **`<x-ui.option-row>`** por opção →
  `<div class="input-group" data-option-row data-option-id>` contendo
  `<div class="input-group-text"><input class="form-check-input mt-0" type="checkbox" data-correct-checkbox dusk="option-correct-{suffix}-{i}"></div>`
  + `<input type="text" class="form-control" dusk="option-text-{suffix}-{i}">`
  + `<button class="btn btn-outline-danger" data-remove-option-btn dusk="remove-option-{suffix}-{i}">✕</button>`
+ `<x-ui.button variant="secondary" size="sm" data-add-option-btn="{suffix}" dusk="add-option-{suffix}">`
+ `<template data-option-template="{suffix}">` com **o mesmo markup Bootstrap** da `option-row`
+ `<x-ui.form-actions>` (submit `dusk="question-submit-{suffix}"` + Cancelar `data-modal-dismiss="true"`).

> ⚠️ **`quiz-builder.js` clona `[data-option-template]` e substitui `__INDEX__`.** Se o markup
> da `<template>` divergir do markup Bootstrap das linhas server-side, opções adicionadas
> dinamicamente ficam visualmente distintas. Extrair AMBOS para `<x-ui.option-row>` garante
> paridade. Verificar também se `quiz-builder.js` manipula `row.style.display` ou classes
> (`.quiz-option-row` é hoje uma classe órfã — ao migrar, ou defini-la, ou confirmar que o JS
> não depende dela).

### 9.5 `quizzes/attempts/pending.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `quiz-attempts.pending` · Papéis admin, gestor |
| Inline `style=` | 11 · JS: nenhum |
| Teste Dusk | `EssayGradingScreenTest.php` |

**Composição alvo:** `<x-layout.page-header kicker="SPEC-08" title="Correções Pendentes">`
(recomendado trocar o kicker técnico "SPEC-08" por "Avaliações")
· `<x-ui.data-table striped hover responsive :headers="['Aluno','Curso / Quiz','Enviado em','Ações']">`
· célula Curso/Quiz → `<div class="fw-semibold">` + `<div class="small text-body-secondary">` (substitui `<br>` + `<span style>`)
· `<x-ui.button size="sm" variant="secondary">` Corrigir
· `<x-ui.empty-state colspan="4" dusk="pending-attempts-empty">` · `<x-ui.pagination>`.

**Dusk imutáveis:** `pending-attempt-row-{id}`, `grade-attempt-{id}`, `pending-attempts-empty`.

### 9.6 `quizzes/attempts/show.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `quiz-attempts.show`, posta em `quiz-attempts.grade` · Papéis admin, gestor |
| Inline `style=` | 12 · JS: nenhum |
| Teste Dusk | `EssayGradingScreenTest.php` |

**Composição alvo:** `<x-layout.page-header :kicker="$curso" :title="'Corrigir Tentativa · '.$attempt->user->name">`
+ `actions` = `<x-ui.button variant="secondary" dusk="back-to-pending">`
· `<x-ui.card :title="$attempt->quiz->title">` + `<form dusk="grade-attempt-form">`
· por questão: `.border-bottom.pb-4.mb-4` + `<h6 class="text-body-secondary fw-bold">` + `<p class="fw-semibold">`
· resposta dissertativa → **`.bg-body-tertiary.p-3.mb-3.rounded-0` com `white-space:pre-wrap`** (manter `pre-wrap` inline ou criar utilitária `.text-prewrap`)
· veredito → **`<x-ui.radio-group name="grades[{i}][is_correct]" inline :options="['1'=>'Correta','0'=>'Incorreta']">`** → `.form-check.form-check-inline` ×2, preservando `dusk="grade-correct-{answerId}"` / `dusk="grade-incorrect-{answerId}"` nos `<input type=radio>`
· questões auto-corrigidas → `<x-ui.badge>`
· submit final `<x-ui.button type="submit" dusk="grade-attempt-submit">`.

**Dusk imutáveis:** `back-to-pending`, `grade-attempt-form`, `essay-answer-{questionId}`, `grade-correct-{answerId}`, `grade-incorrect-{answerId}`, `grade-attempt-submit`.

---

## 10. Módulo `student/` — Área do aluno (2 telas)

### 10.1 `student/courses/index.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `student.courses.index` (GET `/meus-cursos`) · Papéis **aluno** (`role:aluno`) |
| Layout | `layouts.app` |
| Inline `style=` | 10 · JS: nenhum |
| Teste Dusk | `MultiOrgStudentClassroomTest.php`, `HelpCenterDuskTest.php`, `NavigationMenuDuskTest.php`, `NotificationBellTest.php` |

**Composição alvo:** `<x-layout.page-header kicker="Área do Aluno" title="Meus Cursos">`
· por organização: `<h2 class="h5 mb-3">` (ou `<x-layout.section-header>`) dentro de `<div dusk="org-group-{orgId}">`
· grid → **`.row.row-cols-1.row-cols-md-2.row-cols-xl-3.g-3`** + `.col` + `<x-ui.card :title dusk="student-course-{id}" class="h-100">`
  (substitui `grid-template-columns: repeat(auto-fill, minmax(260px,1fr))`)
· descrição → `.card-text.text-body-secondary`
· barra → **`<x-ui.progress :value="$course->pivot->progress_percentage" dusk="progress-bar-{id}">`** → `.progress` + `.progress-bar` (**o `dusk` fica no `.progress-bar`**, como hoje está no div interno)
· `<x-ui.button size="sm">` Acessar Curso
· `@empty` → `<x-ui.empty-state dusk="no-enrollments">` na variante sem tabela (`.text-center.text-body-secondary.py-5`).

**Dusk imutáveis:** `org-group-{orgId}`, `student-course-{id}`, `progress-bar-{id}`, `open-classroom-{id}`, `no-enrollments`.

### 10.2 `student/quizzes/show.blade.php` — **A tela mais complexa do projeto (210 linhas, 13 dusk)**

| Campo | Valor |
|---|---|
| Rotas | `student.quizzes.show` (GET `lessons/{lesson}/quiz`), posta em `student.quizzes.submit` · Middleware `student.enrolled` · Papéis aluno (+ admin/gestor em preview) |
| Layout | `layouts.app` |
| Inline `style=` | 18 |
| JS | **`resources/js/quiz-timer.js`** — `[data-quiz-timer]`, `data-started-at`, `data-time-limit-minutes` |
| Teste Dusk | `StudentQuizAttemptTest.php` → `vendor/bin/sail artisan dusk --filter=StudentQuizAttemptTest` |

**Composição alvo:**
- Coluna central → `.container` + `.row.justify-content-center` + `.col-lg-8` (substitui `width:680px; max-width:100%` + wrapper flex).
- Header → `<x-layout.page-header>` com slot `actions` contendo o cronômetro:
  `<div data-quiz-timer data-started-at data-time-limit-minutes dusk="quiz-timer" class="fs-4 fw-bold font-heading">` (atributos literais).
- Alertas de estado → `<x-ui.alert>`: `quiz-best-score` (`.alert-info`), `quiz-pending-grading` (`.alert-warning`), `quiz-cannot-attempt` (`.alert-secondary`), instruções (`.alert-info`).
- Gabarito → `<x-ui.card title="Gabarito" dusk="quiz-answer-key">` + por questão `<div dusk="answer-key-question-{id}">` + `<ul class="list-group list-group-flush">` com `.list-group-item` e a correta marcada por `.fw-bold.text-accent` + `<x-ui.badge>` "resposta correta". Manter `dusk="answer-key-option-{optionId}"` no `<li>`.
- Cada questão → `<x-ui.card class="mb-4" dusk="quiz-question-{id}">` com header `.d-flex.justify-content-between` (`<h6 class="text-body-secondary fw-bold">Questão N de M` + `<x-ui.badge variant="outline">` do tipo) e `<h3 class="h5 fw-bold">` do enunciado.
- Dissertativa → `<x-ui.textarea name="answers[{id}][essay_answer]" dusk="quiz-essay-{id}">`.
- Alternativas → **`<x-ui.quiz-choice>`** por opção →
  `<label class="list-group-item d-flex align-items-center gap-3">` +
  `<input class="form-check-input m-0" type="radio|checkbox" name="answers[{qid}][selected_option_ids][]" value dusk="quiz-option-{qid}-{oid}">` + `<span>`.
  O estado selecionado passa a ser resolvido por `:checked` + `.form-check-input:checked ~` / `.list-group-item-*` em vez do `border`/`background` calculados no Blade (elimina 2 interpolações de `style=` por opção).
  Envolver em `<div class="list-group">`.
- Submit → `.d-flex.justify-content-end` + `<x-ui.button type="submit" dusk="quiz-attempt-submit">`.

**Dusk imutáveis:** `quiz-timer`, `quiz-best-score`, `quiz-pending-grading`, `quiz-answer-key`, `answer-key-question-{id}`, `answer-key-option-{id}`, `quiz-cannot-attempt`, `back-to-lesson`, `quiz-attempt-form`, `quiz-question-{id}`, `quiz-essay-{id}`, `quiz-option-{qid}-{oid}`, `quiz-attempt-submit`.

---

## 11. Módulo `classroom/` — Sala de aula e player (6 telas)

Middleware `student.enrolled` (aluno matriculado + admin sempre + gestor da mesma org).

### 11.1 `classroom/show.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `classroom.show` (GET `courses/{course}/classroom`) |
| Layout | `layouts.app` · Papéis aluno, gestor (preview), admin |
| Inline `style=` | 18 · JS: nenhum |
| Teste Dusk | `MultiOrgStudentClassroomTest.php`, `CertificateVerificationTest.php`, `StudentQuizAttemptTest.php` |

**Composição alvo:** `<x-layout.page-header kicker="Sala de Aula" :title="$course->title">` + `actions` = `<x-ui.button variant="secondary">` Meus Cursos
· progresso → `.d-flex.justify-content-between.small.text-body-secondary` (rótulo `dusk="course-progress-label"`) + **`<x-ui.progress :value="$progressPercentage" dusk="course-progress-bar">`**
· bloco certificado → `<x-ui.button dusk="download-certificate">` **ou** `<x-ui.alert variant="secondary" dusk="certificate-unavailable">`
· por módulo → `<x-ui.card class="mb-3" dusk="module-{id}">` com `.card-header` = título e `.list-group.list-group-flush` no corpo
· cada lição → `<li class="list-group-item d-flex justify-content-between align-items-center" dusk="lesson-{id}">` com `<a class="stretched-link text-body text-decoration-none d-flex align-items-center gap-2" dusk="open-lesson-{id}">` + `<x-ui.icon>` (concluída/`play`) + `<x-ui.badge variant="outline">`
· `@empty` da lição → `.list-group-item.text-center.text-body-secondary`
· `@empty` do módulo → `<p class="text-body-secondary" dusk="no-modules">`.

**Dusk imutáveis:** `course-progress-label`, `course-progress-bar`, `download-certificate`, `certificate-unavailable`, `module-{id}`, `lesson-{id}`, `open-lesson-{id}`, `lesson-completed-{id}`, `no-modules`.

### 11.2 `classroom/lesson.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `classroom.lesson` (GET `lessons/{lesson}`) · Inline `style=`: 4 · JS: nenhum (despacha) |
| Padrões UI | dispatcher: `quiz` → `_quiz-placeholder`; `youtube_url` → `_video`; `pdf_path` → `_pdf`; senão `_text-image` |
| Teste Dusk | `MultiOrgStudentClassroomTest.php`, `VideoThresholdCompletionTest.php` |

**Composição alvo:** `<x-ui.breadcrumb :items="[curso, módulo]">` + `<x-layout.page-header :title="$lesson->title">`
+ `<x-ui.button variant="secondary" dusk="back-to-classroom">`
+ `<x-ui.card>` envolvendo o `@include` — **manter a ordem de despacho intacta** (`quiz` tem prioridade).

**Dusk imutáveis:** `back-to-classroom`.

### 11.3 `classroom/partials/_video.blade.php` — **JS-pesado**

| Campo | Valor |
|---|---|
| Inline `style=` | 7 |
| JS | **`LessonPlayer.js`** — `[data-youtube-player]`, `data-lesson-id`, `data-video-id`, `data-progress-url`, `[data-completion-badge]` |
| Teste Dusk | `VideoThresholdCompletionTest.php` → `--filter=VideoThresholdCompletionTest` |

**Composição alvo:** substituir o hack `position:relative; padding-top:56.25%` por **`.ratio.ratio-16x9`**
no `<div data-youtube-player ... dusk="video-player-{id}">`, e o `<iframe style="position:absolute;inset:0;...">`
por um `<iframe>` sem estilo (o `.ratio` já posiciona o filho).
Badge → `<x-ui.badge variant="accent" data-completion-badge dusk="lesson-completed-badge" style="{{ $isCompleted ? '' : 'display:none;' }}">` — **manter o `style` inline** (§0.3).
Dica → `.form-text` / `.small.text-body-secondary` com `data-progress-hint`.

**Dusk imutáveis:** `video-player-{id}`, `lesson-completed-badge`.

### 11.4 `classroom/partials/_pdf.blade.php` — **JS-pesado**

| Campo | Valor |
|---|---|
| Inline `style=` | 7 · JS: **`LessonPlayer.js`** (`[data-mark-complete-url]`, `[data-completion-badge]`) |
| Teste Dusk | `LessonMultimediaTest.php`, `MultiOrgStudentClassroomTest.php` |

**Composição alvo:** `<div class="ratio" style="--bs-aspect-ratio:75%">` ou `.embed-responsive` custom
envolvendo `<iframe class="border" dusk="pdf-viewer-{id}">` (a altura fixa de `600px` pode ficar como
`style="height:70vh"` ou classe utilitária `.h-viewer`).
Rodapé → `.d-flex.justify-content-between.align-items-center` com `<a class="link-accent fw-bold small" download dusk="pdf-download-{id}">`
+ `<x-ui.badge data-completion-badge dusk="lesson-completed-badge" style=...>` + `<x-ui.button data-mark-complete-url dusk="mark-complete-button">`.

**Dusk imutáveis:** `pdf-viewer-{id}`, `pdf-download-{id}`, `lesson-completed-badge`, `mark-complete-button`.

### 11.5 `classroom/partials/_text-image.blade.php` — **JS-pesado**

| Campo | Valor |
|---|---|
| Inline `style=` | 6 · JS: **`LessonPlayer.js`** |
| Teste Dusk | `LessonMultimediaTest.php`, `MultiOrgStudentClassroomTest.php` |

**Composição alvo:** `<img class="img-fluid border mb-3" dusk="lesson-image-{id}">`
+ `<div class="lh-base" dusk="lesson-content-{id}">` (conteúdo `nl2br(e())`)
+ `.d-flex.align-items-center.gap-3` com badge (mantendo `style="display:none"`) + `<x-ui.button data-mark-complete-url dusk="mark-complete-button">`.

**Dusk imutáveis:** `lesson-image-{id}`, `lesson-content-{id}`, `lesson-completed-badge`, `mark-complete-button`.

### 11.6 `classroom/partials/_quiz-placeholder.blade.php`

| Campo | Valor |
|---|---|
| Inline `style=` | 4 · JS: nenhum |
| Teste Dusk | `StudentQuizAttemptTest.php` |

**Composição alvo:** `<div class="text-center p-4 border border-dashed" dusk="quiz-placeholder">`
+ `<p>` + `<x-ui.button href dusk="start-quiz">`; ramo `@else` → `.text-center.p-4.border.border-dashed.text-body-secondary`.
Criar utilitária `.border-dashed { border-style: dashed !important }` (Bootstrap 5.3 não a fornece).

**Dusk imutáveis:** `quiz-placeholder`, `start-quiz`.

---

## 12. Módulo `forum/` — Fórum de discussão (8 telas)

Middleware `student.enrolled` para index/show/create/edit; `role:admin|gestor` para moderação e pin.

### 12.1 `forum/index.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `forum.index` (GET `courses/{course}/forum`), modal posta em `forum.store` |
| Papéis | aluno matriculado, gestor da org, admin |
| Inline `style=` | 13 |
| JS | `ModalManager` (`data-modal-target="new-topic-modal"`) |
| Teste Dusk | `ForumDuskTest.php` → `vendor/bin/sail artisan dusk --filter=ForumDuskTest` |

**Composição alvo:** `<x-layout.page-header kicker="Fórum" :title="$course->title">` + `actions` = `<x-ui.button data-modal-target="new-topic-modal" dusk="new-topic-button">`
· lista → `<div class="list-group">` + `@include('forum.partials._topic')` por tópico
· `@empty` → `<p class="text-body-secondary" dusk="no-topics">`
· `<x-ui.pagination :paginator="$topics">`
· modal → `<x-ui.modal id="new-topic-modal" size="md">` com `<form id="new-topic-form">` + `<x-ui.input name="title" dusk="new-topic-title">` + `<x-ui.textarea name="content" dusk="new-topic-content">` e o slot `actions` mantendo **`form="new-topic-form"`** no submit (o slot renderiza fora do `<form>`; com `.modal-footer` do Bootstrap a situação é idêntica — **não remover o atributo `form`**).

**Dusk imutáveis:** `new-topic-button`, `no-topics`, `new-topic-form`, `new-topic-title`, `new-topic-content`, `new-topic-submit`.

### 12.2 `forum/partials/_topic.blade.php` — **PARTIAL COMPARTILHADO**

| Campo | Valor |
|---|---|
| Incluído por | `forum/index.blade.php` · Inline `style=`: 5 · JS: nenhum |

**Composição alvo:** `<div class="list-group-item d-flex justify-content-between align-items-center gap-3" dusk="topic-row-{id}">`
+ `<a class="text-body text-decoration-none flex-grow-1 min-w-0" dusk="open-topic-{id}">` com `<x-ui.badge>` Fixado + `<strong>` + linha de metadados `.small.text-body-secondary`
+ form de pin → `<x-ui.button size="sm" variant="ghost" type="submit" dusk="pin-topic-{id}">`.

**Dusk imutáveis:** `topic-row-{id}`, `open-topic-{id}`, `pinned-badge-{id}`, `pin-form-{id}`, `pin-topic-{id}`.

### 12.3 `forum/show.blade.php` — **A tela com mais `dusk` do projeto (17)**

| Campo | Valor |
|---|---|
| Rotas | `forum.show`; ações: `forum.edit`, `forum.destroy`, `forum.pin`, `forum-replies.store`, `forum-replies.fetch`, `forum-reports.store` |
| Inline `style=` | 24 |
| JS | **`ForumPolling.js`** (`data-forum-polling`, `data-fetch-url`, `data-last-id`), **`ForumReportModal.js`** (`data-forum-report-button`, `data-postable-*`, `data-forum-report-form`, `data-forum-report-postable-*`), **`ForumEditHistory.js`**, `ModalManager` |
| Teste Dusk | `ForumDuskTest.php` |

**Composição alvo:**
- `<x-layout.page-header>` + `<x-ui.button variant="secondary" dusk="back-to-forum">`.
- Post original → **`<x-ui.post-card>`** (novo componente, compartilhado com `_reply`) →
  `.card.mb-3` + `.card-header.d-flex.justify-content-between.align-items-center` (autor + data + `@include(_edit-history-modal)`) + `.card-body` com `.text-prewrap`.
  Barra de ações → `.btn-group.btn-group-sm` com Denunciar (`data-forum-report-button`), Editar, Fixar, Apagar (`<x-ui.delete-button>`).
  Manter `dusk="topic-post"`, `data-topic-id`, `dusk="topic-content"`.
- `<h2 class="h5">Respostas</h2>`.
- **`<div id="replies-list" data-forum-polling data-fetch-url data-last-id dusk="replies-list" class="vstack gap-2">`** — atributos literais.
- Form de resposta → `<x-ui.textarea name="content" dusk="new-reply-content">` + `.d-flex.justify-content-end` + `<x-ui.button type="submit" dusk="new-reply-submit">`.
- Modal de denúncia → `<x-ui.modal id="report-modal" size="sm">` com os 2 hidden `data-forum-report-postable-type|id`, `<x-ui.textarea name="reason" dusk="report-reason">`, e o submit com **`form="report-form"`** preservado.

**Dusk imutáveis:** `back-to-forum`, `pinned-badge-{id}`, `report-topic-{id}`, `edit-topic-{id}`, `pin-form-{id}`, `pin-topic-{id}`, `delete-topic-form`, `delete-topic`, `topic-post`, `topic-content`, `replies-list`, `new-reply-form`, `new-reply-content`, `new-reply-submit`, `report-form`, `report-reason`, `report-submit`.

### 12.4 `forum/partials/_reply.blade.php` — **PARTIAL + espelhado em JS (conflito crítico)**

| Campo | Valor |
|---|---|
| Incluído por | `forum/show.blade.php` |
| Inline `style=` | 8 |
| JS | **`ForumPolling.appendReply()` recria este markup em JS** com `el.style.cssText`; `ForumReportModal.js` liga em `[data-forum-report-button]` |
| Teste Dusk | `ForumDuskTest.php` |

**Composição alvo:** reutilizar **`<x-ui.post-card>`** →
`<div class="card mb-2" dusk="reply-{id}" data-reply-id="{id}">` + `.card-body` com header `.d-flex.justify-content-between.align-items-center.mb-2.small.text-body-secondary` + conteúdo `.text-prewrap` (`dusk="reply-content-{id}"`)
+ `.btn-group.btn-group-sm` (Denunciar + Apagar).

> 🚨 **Alterar `resources/js/modules/ForumPolling.js:70-90` no MESMO commit**, trocando o
> `el.style.cssText = '...'` por `el.className = 'card mb-2'` e replicando exatamente a
> estrutura `.card-body` / `.small.text-body-secondary` / `.text-prewrap` acima, preservando
> `data-reply-id`, `dusk="reply-{id}"` e `dusk="reply-content-{id}"`.

**Dusk imutáveis:** `reply-{id}`, `reply-content-{id}`, `report-reply-{id}`, `delete-reply-form-{id}`, `delete-reply-{id}`.

### 12.5 `forum/partials/_edit-history-modal.blade.php` — **PARTIAL + modal**

| Campo | Valor |
|---|---|
| Incluído por | `forum/show.blade.php` (1× para o tópico + 1× por resposta) |
| Inline `style=` | 7 |
| JS | **`ForumEditHistory.js`** (`[data-edit-history-trigger]`, `[id^="edit-history-"]`) + `ModalManager` |
| Teste Dusk | `ForumDuskTest.php` |

**Composição alvo:** `<span class="small text-body-secondary ms-2">` + `<button class="btn btn-link btn-sm p-0 align-baseline" data-modal-target="{modalId}" data-edit-history-trigger dusk="edit-history-trigger-{modalId}">`
+ `<x-ui.modal :id="$modalId" size="md">` com `<ul class="list-group list-group-flush">` por entrada
(`.list-group-item` + `.small.text-body-secondary` do autor/data + `.text-prewrap` do conteúdo anterior, `dusk="edit-history-entry-{id}"`)
+ `@empty` → `<p class="text-body-secondary small" dusk="edit-history-empty-{modalId}">`.

> ⚠️ O `id` do modal **precisa continuar começando com `edit-history-`** — `ForumEditHistory.js`
> usa `querySelectorAll('[id^="edit-history-"]')`.

**Dusk imutáveis:** `edit-history-trigger-{modalId}`, `edit-history-entry-{editId}`, `edit-history-empty-{modalId}`.

### 12.6 `forum/create.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `forum.create`, posta em `forum.store` · Inline `style=`: 2 · JS: nenhum |
| Teste Dusk | `ForumDuskTest.php` |

**Composição alvo:** `<x-ui.card title="Novo Tópico" :kicker="$course->title.' / Fórum'">` + `<form dusk="new-topic-form">`
+ `<x-ui.field-stack>` (`<x-ui.input dusk="new-topic-title">` + `<x-ui.textarea dusk="new-topic-content">`) + `<x-ui.form-actions>`.

**Dusk imutáveis:** `new-topic-form`, `new-topic-title`, `new-topic-content`, `new-topic-submit`.

### 12.7 `forum/edit.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `forum.edit`, posta em `forum.update` · Inline `style=`: 2 · JS: nenhum |
| Teste Dusk | `ForumDuskTest.php` |

**Composição alvo:** idêntica a 12.6, com `@method('PUT')`.
**Dusk imutáveis:** `edit-topic-form`, `edit-topic-title`, `edit-topic-content`, `edit-topic-submit`.

### 12.8 `forum/moderation/index.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `forum-moderation.index` (+ `.dismiss`, `.remove`) · Papéis admin, gestor |
| Inline `style=` | 12 · JS: nenhum |
| Teste Dusk | `ForumDuskTest.php` |

**Composição alvo:** `<x-layout.page-header kicker="Fórum" title="Fila de Denúncias">`
· `<x-ui.data-table striped hover responsive :headers="['Denunciado por','Motivo','Publicação','Ações']">`
· coluna Publicação → `<x-ui.badge variant="outline">` do tipo + `<div class="small text-body-secondary text-truncate" style="max-width:20rem">` (substitui `overflow/text-overflow/white-space` manuais)
· Ações → `.btn-group.btn-group-sm` com os 2 forms (Descartar `.btn-outline-secondary`, Remover Publicação `.btn-outline-danger`), idealmente atrás de `<x-ui.confirm-modal>` para Remover
· `<x-ui.empty-state colspan="4" dusk="no-pending-reports">`.

**Dusk imutáveis:** `report-row-{id}`, `dismiss-form-{id}`, `dismiss-report-{id}`, `remove-form-{id}`, `remove-post-{id}`, `no-pending-reports`.

---

## 13. Módulo `certificates/` + `public/certificates/` (3 telas)

### 13.1 `certificates/index.blade.php` — **modais N + script inline**

| Campo | Valor |
|---|---|
| Rotas | `courses.certificates.index` (+ `certificates.revoke`, `certificates.download`) · Papéis admin, gestor |
| Layout | `layouts.app` · Inline `style=`: 18 |
| JS | `ModalManager` (`data-modal-target="revoke-modal-{id}"`, `data-modal-dismiss`) + **script inline em `@push('scripts')`** que liga `[data-revoke-form]` → `[data-revoke-reason]` → `[data-revoke-submit]` (habilita o submit com ≥10 caracteres) |
| Teste Dusk | `CertificateRevocationTest.php` → `vendor/bin/sail artisan dusk --filter=CertificateRevocationTest` |

**Composição alvo:**
`<x-layout.page-header kicker="Certificados" :title="$course->title">`
· `<x-ui.data-table striped hover responsive :headers="['Aluno','Emitido em','Status','Ações']">` + `<x-ui.badge dusk="certificate-status-{id}">`
· Ações → `.btn-group.btn-group-sm` (Baixar PDF + Revogar abrindo o modal)
· `<x-ui.empty-state colspan="4">` · `<x-ui.pagination>`
· 1 **`<x-ui.confirm-modal>`** por certificado ativo, contendo `<x-ui.textarea name="revoke_reason" rows="4" minlength="10" maxlength="500" data-revoke-reason dusk="revoke-reason-{id}">`
  + `.form-text` (`data-revoke-hint`) + `.modal-footer` com Cancelar (`data-modal-dismiss`) e Revogar (`data-revoke-submit`, `disabled`, `dusk="confirm-revoke-{id}"`)
· **mover o script de `@push('scripts')` para `resources/js/modules/RevokeCertificateForm.js`** e registrá-lo em `app.js` (elimina o único bloco `<script>` inline de tela deste módulo). Manter todos os `data-revoke-*`.
· mensagens de erro → `.invalid-feedback.d-block` em vez de `<p style="color: var(--color-danger-700, #b3261e)">`.

**Dusk imutáveis:** `certificate-row-{id}`, `certificate-status-{id}`, `download-certificate-{id}`, `revoke-certificate-{id}`, `revoke-form-{id}`, `revoke-reason-{id}`, `confirm-revoke-{id}`.

### 13.2 `public/certificates/show.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `certificates.verify` (GET `/validar-certificado/{hash}`) — **sem middleware algum** |
| Layout | **nenhum** — documento standalone com `@vite` |
| Papéis | público (anônimo ou logado) |
| Inline `style=` | 21 · JS: nenhum (carrega `app.js` por causa do `<x-help-button>`) |
| Teste Dusk | `CertificateVerificationTest.php`, `CertificateRevocationTest.php` |

**Composição alvo:** **`<x-layout.public title>`** (mesmo shell da landing) + `.container` + `.row.justify-content-center` + `.col-lg-6.py-5`
· cabeçalho `.text-center.position-relative` com kicker + `<x-help-button key="certificates.verify">` posicionado por `.position-absolute.top-0.end-0`
· banner → `<x-ui.alert variant="danger" dusk="certificate-revoked-banner">` / `<x-ui.alert variant="success" dusk="certificate-valid-banner">`
  (**substitui os fallbacks `var(--color-danger-100, #fdecea)` / `var(--color-accent-100, #eafaf1)` manuais** por `.alert-danger` / `.alert-success`)
· dados → **`<x-ui.kv-table :rows="[...]">`** → `.table.table-borderless.align-middle` com `<th class="text-body-secondary fw-normal w-40">` + `<td class="fw-bold" dusk="...">`
· hash → `.small.text-body-secondary.text-center.text-break`.

**Dusk imutáveis:** `certificate-revoked-banner`, `certificate-revoke-reason`, `certificate-valid-banner`, `certificate-student-name`, `certificate-course-title`, `certificate-org-name`, `certificate-workload`, `certificate-issued-at`.

### 13.3 🚨 `certificates/pdf.blade.php` — **TEMPLATE dompdf, ZERO BOOTSTRAP**

| Campo | Valor |
|---|---|
| Rotas | **nenhuma** — renderizado por `CertificatePdfService::generate()` via `barryvdh/laravel-dompdf` |
| Layout | documento HTML próprio, `<style>` embutido |
| Papéis | N/A (saída binária de `certificates.download`) |
| Inline `style=` | 7 (todos em `<td style="width:XX%">` — **corretos e obrigatórios** para dompdf) |
| JS | nenhum · **`dusk`: nenhum** |
| Teste | PHPUnit: `vendor/bin/sail artisan test --compact --filter=Certificate` + inspeção manual do PDF |

**Ação de migração: praticamente NENHUMA.** Ver §0.5 na íntegra. Resumo executável:

- ❌ **NÃO** adicionar `class="container"`, `class="table"`, `class="text-center"`, `class="row"`, `d-flex`, `badge`, `card`, nem qualquer classe Bootstrap.
- ❌ **NÃO** adicionar `@vite` nem `<link>` para o CSS do app.
- ❌ **NÃO** trocar as cores hex literais por `var(--color-*)`.
- ❌ **NÃO** trocar `<table class="header">` / `<table class="meta-table">` / `<table class="footer">` por grid/flex.
- ✅ Permitido: extrair o shell `<!DOCTYPE html>…<head><style>…` para `<x-layout.print>`, desde que o componente também não contenha Bootstrap.
- ✅ Permitido/desejável: corrigir `.kicker { color: #7a5cff }` para `#ec3013` (alinhamento com `--color-accent`), **como literal hex**.
- ✅ Manter `font-family: 'DejaVu Sans'` (única fonte com cobertura Unicode registrada no dompdf) — **não** trocar por Archivo/`var(--font-heading)`.

**Se um agente implementador receber esta tela num lote junto de outras, ele deve tratá-la como
um item isolado e não aplicar nenhuma regra de "converter inline styles em utilitárias".**

---

## 14. Módulo `audit-logs/` — Auditoria (2 telas)

### 14.1 `audit-logs/index.blade.php` — **maior densidade de inline styles (27)**

| Campo | Valor |
|---|---|
| Rotas | `admin.audit-logs.index` (role:admin) **e** `gestor.audit-logs.index` (role:gestor) — a mesma view resolve o nome em runtime e deriva `*.export` |
| Layout | `layouts.app` · Papéis admin, gestor |
| Inline `style=` | 27 |
| JS | **`AuditLogDiffModal.js`** (`[data-audit-diff-trigger]`, `data-event`, `data-old-values`, `data-new-values`) + `ModalManager` |
| Teste Dusk | `AuditLogUiTest.php` → `vendor/bin/sail artisan dusk --filter=AuditLogUiTest` |

**Composição alvo:**
`<x-layout.page-header title="Logs de Auditoria">` + `actions` = `<x-ui.button variant="secondary" dusk="export-audit-logs-csv">`
(elimina o `<a class="btn btn-secondary" style="...190 chars...">`)
· **`<x-ui.filter-bar :action="route($auditLogsRouteName)" dusk="audit-logs-filter-form">`** →
  `<form class="row g-2 align-items-end p-3 border bg-body-tertiary mb-4">` contendo
  `<x-ui.input type="date" dusk="audit-logs-date-from">` (`.col-md-2`),
  `<x-ui.input type="date" dusk="audit-logs-date-to">` (`.col-md-2`),
  `@role('admin')` `<x-ui.select dusk="audit-logs-org-filter">` (`.col-md-2`),
  `<x-ui.select dusk="audit-logs-event-filter">` (`.col-md-2`),
  `<x-ui.input dusk="audit-logs-user-filter">` (`.col-md-2`),
  `.col-auto` com `.btn-group` (Filtrar `.btn-primary` `dusk="audit-logs-filter-submit"` + Limpar `.btn-link`)
· `<x-ui.data-table striped hover responsive :headers="[...]" dusk="audit-logs-table">` + `<x-ui.badge variant="neutral">` no evento
· botão "Ver diff" → `<x-ui.button variant="ghost" size="sm" data-modal-target="audit-diff-modal" data-audit-diff-trigger data-event data-old-values data-new-values dusk="view-diff-{id}">` (**todos os `data-*` literais**)
· `<x-ui.empty-state colspan="6">` · `<x-ui.pagination :paginator="$auditLogs">`
· `@include('audit-logs.partials._diff-modal')`.

**Dusk imutáveis:** `audit-logs-index`, `export-audit-logs-csv`, `audit-logs-filter-form`, `audit-logs-date-from`, `audit-logs-date-to`, `audit-logs-org-filter`, `audit-logs-event-filter`, `audit-logs-user-filter`, `audit-logs-filter-submit`, `audit-logs-table`, `audit-log-row-{id}`, `view-diff-{id}`.

### 14.2 `audit-logs/partials/_diff-modal.blade.php` — **PARTIAL + modal único compartilhado**

| Campo | Valor |
|---|---|
| Incluído por | `audit-logs/index.blade.php` (1× por página, compartilhado pelas 25 linhas) |
| Inline `style=` | 8 |
| JS | **`AuditLogDiffModal.js`** preenche `[dusk="audit-diff-event"]`, `[dusk="audit-diff-old"]`, `[dusk="audit-diff-new"]` |
| Teste Dusk | `AuditLogUiTest.php` |

**Composição alvo:** `<x-ui.modal id="audit-diff-modal" size="lg">` (→ `.modal-lg`)
+ linha de evento `.mb-3.small` com `<span class="text-uppercase fw-bold text-body-secondary">` + `<span dusk="audit-diff-event">`
+ **`.row.g-3`** com duas `.col-md-6`, cada uma com `<h4 class="small text-uppercase fw-bold">` e
`<pre class="bg-body-tertiary p-3 small text-break overflow-auto" style="white-space:pre-wrap" dusk="audit-diff-old|new">`
(substitui `color-mix(in srgb, var(--color-neutral-900) 5%, transparent)` por `.bg-body-tertiary`)
+ `.modal-footer` com `<button class="btn btn-link" data-modal-dismiss="true">Fechar</button>`.

**Dusk imutáveis:** `audit-diff-event`, `audit-diff-old`, `audit-diff-new`.

---

## 15. Módulos `profile/` e `settings/` (2 telas)

### 15.1 `profile/edit.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `profile.edit` (GET), `profile.update` (PATCH), `password.update` (PUT, `throttle:6,1`) |
| Layout | `layouts.app` · Papéis **qualquer usuário autenticado** (admin, gestor, aluno) |
| Inline `style=` | 7 · JS: nenhum |
| Teste Dusk | `ProfileTest.php` → `vendor/bin/sail artisan dusk --filter=ProfileTest` |

**Composição alvo:** `<x-layout.page-header title="Meu Perfil">`
· `.row` + `.col-lg-7` + `.vstack.gap-4` com **dois `<x-ui.card>` independentes**:
  1. "Informações do Perfil" → `<form dusk="profile-form">` `@method('PATCH')` + `<x-ui.field-stack>` (name, email, cpf) + `<x-ui.form-actions>` (`dusk="profile-submit"`)
  2. "Atualizar Senha" → `<form dusk="password-form">` `@method('PUT')` + 3 `<x-ui.input type="password">` + `<x-ui.form-actions>` (`dusk="password-submit"`)
· **Não** adicionar `<x-help-button>` aqui — `layouts.app` já monta um global (ver comentário no topo do arquivo; adicionar um segundo duplicaria a chave de rota).

**Dusk imutáveis:** `profile-form`, `profile-submit`, `password-form`, `password-submit`.

### 15.2 `settings/edit.blade.php`

| Campo | Valor |
|---|---|
| Rotas | `settings.edit` (GET `/admin/settings`), `settings.update` (PUT) · Papéis admin, gestor |
| Layout | `layouts.app` · Inline `style=`: 9 · JS: nenhum |
| Teste Dusk | `DashboardDuskTest.php` |

**Composição alvo:** `<x-ui.card title="Configurações" kicker="Sistema">` + `<form enctype="multipart/form-data" dusk="settings-form">`
· **duas subseções** via `<x-layout.section-header title="SMTP">` e `<x-layout.section-header title="Identidade Visual">`
  (hoje são `<div style="font-size:11px; text-transform:uppercase; ...">` soltos) — alternativa melhor: **`.nav-tabs` + `.tab-content`** com 2 abas (`#tab-smtp`, `#tab-branding`), já que Bootstrap JS passa a estar disponível
· SMTP → `.row.g-3` com `<x-ui.input name="smtp_host">` (`.col-md-8`), `<x-ui.input type="number" name="smtp_port">` (`.col-md-4`), `<x-ui.input name="smtp_username">`, `<x-ui.input type="password" name="smtp_password">`
· Identidade → **`<x-ui.file-input name="logo" :current="$settings['logo_path'] ?? null">`** + `<x-ui.textarea name="signature">`
· `<x-ui.form-actions>`.

**Dusk imutáveis:** `settings-form`, `settings-submit`.

---

## 16. Ondas de migração (waves)

> **Total no escopo: 60 telas.** A Wave A (fundação) é pré-requisito e cobre os 18 arquivos
> de `components/**` + `layouts/**`, que estão **fora** do escopo deste documento mas devem
> estar 100% concluídos antes de qualquer onda abaixo começar.

### Wave A — Fundação e layout (pré-requisito, 18 arquivos, fora do escopo desta doc)

`resources/css/app.css` (Bootstrap 5.3 completo + tokens), `resources/js/app.js`
(`bootstrap.bundle`), `layouts/app.blade.php`, `layouts/guest.blade.php`,
`components/layout/{topbar,sidebar,footer,alerts}.blade.php`,
`components/ui/{alert,badge,button,card,icon,input,modal,select,stat-card,table}.blade.php`,
`components/{help-button,notifications-bell}.blade.php`,
**+ os 22 componentes novos da §0.2**, **+ a ponte `ModalManager` ↔ `bootstrap.Modal` (§0.4)**,
**+ `config/app.php`/`AppServiceProvider::boot()` → `Paginator::useBootstrapFive()`**.
Verificação: `vendor/bin/sail artisan dusk --filter=LayoutRenderingTest` e `--filter=BladeComponentsTest`.

### Wave B — Partials compartilhados e formulários base — **8 telas**

Serializada por definição (cada partial é ponto de conflito). Nenhuma renderiza rota própria.

| # | Arquivo | Verificação |
|---|---|---|
| 1 | `courses/_form.blade.php` | `dusk --filter=CourseManagementTest` |
| 2 | `courses/modules/_form.blade.php` | `dusk --filter=CourseManagementTest` |
| 3 | `organizations/_form.blade.php` | `dusk --filter=OrganizationCrudTest` |
| 4 | `courses/modules/_list.blade.php` | `dusk --filter=ModuleReorderTest` |
| 5 | `quizzes/partials/_question-list.blade.php` | `dusk --filter=EssayGradingScreenTest` |
| 6 | `forum/partials/_topic.blade.php` | `dusk --filter=ForumDuskTest` |
| 7 | `classroom/partials/_quiz-placeholder.blade.php` | `dusk --filter=StudentQuizAttemptTest` |
| 8 | `audit-logs/partials/_diff-modal.blade.php` | `dusk --filter=AuditLogUiTest` |

### Wave C — Telas de leitura (index) de baixo risco — **11 telas**

Sem JS próprio, sem formulário complexo. **Totalmente paralelizáveis entre si** (nenhuma
compartilha arquivo), desde que a Wave B esteja concluída.

| # | Arquivo | Papéis | Verificação |
|---|---|---|---|
| 1 | `dashboard/index.blade.php` | admin, gestor | `--filter=DashboardDuskTest` |
| 2 | `organizations/index.blade.php` | admin | `--filter=OrganizationCrudTest` |
| 3 | `users/index.blade.php` | admin, gestor | `--filter=UserManagementTest` |
| 4 | `courses/index.blade.php` | admin, gestor | `--filter=CourseManagementTest` |
| 5 | `courses/modules/index.blade.php` | admin, gestor | `--filter=ModuleReorderTest` |
| 6 | `courses/invitation-links/index.blade.php` | admin, gestor | sem Dusk → `test --filter=InvitationLink` |
| 7 | `courses/enrollments/index.blade.php` | admin, gestor | `--filter=UserManagementTest` |
| 8 | `quizzes/attempts/pending.blade.php` | admin, gestor | `--filter=EssayGradingScreenTest` |
| 9 | `forum/moderation/index.blade.php` | admin, gestor | `--filter=ForumDuskTest` |
| 10 | `student/courses/index.blade.php` | aluno | `--filter=MultiOrgStudentClassroomTest` |
| 11 | `classroom/show.blade.php` | aluno, gestor, admin | `--filter=MultiOrgStudentClassroomTest` |

### Wave D — Telas de formulário / CRUD — **19 telas**

Dependem dos partials da Wave B. Paralelizáveis **por par create/edit** (ver §17).

| # | Arquivo | Verificação |
|---|---|---|
| 1 | `organizations/create.blade.php` | `--filter=OrganizationCrudTest` |
| 2 | `organizations/edit.blade.php` | `--filter=OrganizationCrudTest` |
| 3 | `users/create.blade.php` | `--filter=UserManagementTest` |
| 4 | `users/edit.blade.php` | `--filter=UserManagementTest` |
| 5 | `courses/create.blade.php` | `--filter=CourseManagementTest` |
| 6 | `courses/edit.blade.php` | `--filter=CourseManagementTest` |
| 7 | `courses/modules/create.blade.php` | `--filter=CourseManagementTest` |
| 8 | `courses/modules/edit.blade.php` | `--filter=CourseManagementTest` |
| 9 | `courses/invitation-links/create.blade.php` | sem Dusk |
| 10 | `courses/completion-rules/index.blade.php` | `--filter=CourseCompletionRuleTest` |
| 11 | `modules/lessons/create.blade.php` | `--filter=LessonMultimediaTest` |
| 12 | `modules/lessons/edit.blade.php` | `--filter=LessonMultimediaTest` |
| 13 | `modules/lessons/index.blade.php` | `--filter=LessonMultimediaTest` |
| 14 | `quizzes/create.blade.php` | `--filter=EssayGradingScreenTest` |
| 15 | `quizzes/attempts/show.blade.php` | `--filter=EssayGradingScreenTest` |
| 16 | `forum/create.blade.php` | `--filter=ForumDuskTest` |
| 17 | `forum/edit.blade.php` | `--filter=ForumDuskTest` |
| 18 | `profile/edit.blade.php` | `--filter=ProfileTest` |
| 19 | `settings/edit.blade.php` | `--filter=DashboardDuskTest` |

### Wave E — Telas JS-pesadas / interativas — **15 telas** (+3 módulos JS)

**Alto risco.** Cada item exige alterar Blade **e** JS no mesmo commit, ou verificar
explicitamente que o JS não depende de estilo/classe.

| # | Arquivo(s) | JS acoplado | Verificação |
|---|---|---|---|
| 1 | `classroom/lesson.blade.php` | — (dispatcher) | `--filter=MultiOrgStudentClassroomTest` |
| 2 | `classroom/partials/_video.blade.php` | `LessonPlayer.js` | `--filter=VideoThresholdCompletionTest` |
| 3 | `classroom/partials/_pdf.blade.php` | `LessonPlayer.js` | `--filter=LessonMultimediaTest` |
| 4 | `classroom/partials/_text-image.blade.php` | `LessonPlayer.js` | `--filter=LessonMultimediaTest` |
| 5 | `student/quizzes/show.blade.php` | `quiz-timer.js` | `--filter=StudentQuizAttemptTest` |
| 6 | `quizzes/edit.blade.php` | `ModalManager` | `--filter=EssayGradingScreenTest` |
| 7 | `quizzes/partials/_question-form.blade.php` | **`quiz-builder.js`** (template clonado) | `--filter=EssayGradingScreenTest` |
| 8 | `modules/lessons/_form.blade.php` | script inline `@push` → extrair p/ `LessonForm.js` | `--filter=LessonMultimediaTest` |
| 9 | `forum/index.blade.php` | `ModalManager` | `--filter=ForumDuskTest` |
| 10 | `forum/show.blade.php` | `ForumPolling`, `ForumReportModal`, `ForumEditHistory`, `ModalManager` | `--filter=ForumDuskTest` |
| 11 | `forum/partials/_reply.blade.php` **+ `ForumPolling.js`** | 🚨 markup espelhado | `--filter=ForumDuskTest` |
| 12 | `forum/partials/_edit-history-modal.blade.php` | `ForumEditHistory.js` | `--filter=ForumDuskTest` |
| 13 | `users/import.blade.php` | **`CsvImporter.js` (liga por `[dusk]`)** | `--filter=MultiTenantStudentImportTest` |
| 14 | `audit-logs/index.blade.php` | `AuditLogDiffModal.js` | `--filter=AuditLogUiTest` |
| 15 | `certificates/index.blade.php` | `ModalManager` + script inline → extrair p/ `RevokeCertificateForm.js` | `--filter=CertificateRevocationTest` |
| 16 | `convite/show.blade.php` | `SmartInvitationForm.js` | `--filter=MultiOrgEnrollmentTest` |

> A tabela lista 16 linhas porque `convite/show` é contabilizado na Wave F (público) **ou** na E
> (JS). **Decisão: contabilizar em E** (o risco dominante é o `SmartInvitationForm`), logo a
> Wave F fica com 3 telas.

### Wave F — Telas públicas e de impressão — **3 telas**

Feitas por último: são as únicas sem `layouts.app`/`layouts.guest` e por isso não se beneficiam
da fundação até que `<x-layout.public>`/`<x-layout.print>` existam.

| # | Arquivo | Observação | Verificação |
|---|---|---|---|
| 1 | `landing/show.blade.php` | shell standalone → `<x-layout.public>` | `--filter=LayoutRenderingTest` + `--filter=BladeComponentsTest` |
| 2 | `public/certificates/show.blade.php` | shell standalone → `<x-layout.public>` | `--filter=CertificateVerificationTest` |
| 3 | 🚨 `certificates/pdf.blade.php` | **dompdf — ZERO Bootstrap (§0.5)** | `test --compact --filter=Certificate` + inspeção manual do PDF |

### Resumo das ondas

| Onda | Descrição | Telas |
|---|---|---|
| A | Fundação/layout/componentes (pré-requisito, fora do escopo) | 18 (não contadas) |
| B | Partials compartilhados e formulários base | **8** |
| C | Telas de leitura (index) de baixo risco | **11** |
| D | Telas de formulário / CRUD | **19** |
| E | Telas JS-pesadas / interativas | **16** |
| F | Telas públicas e de impressão | **3** |
| — | **Total no escopo** | **60** |

---

## 17. Paralelização e conjuntos de conflito

### 17.1 Conjuntos que DEVEM ser serializados (um agente por conjunto, um commit por conjunto)

| Conjunto | Arquivos | Motivo |
|---|---|---|
| **CS-01 — Form de Curso** | `courses/_form.blade.php`, `courses/create.blade.php`, `courses/edit.blade.php` | create e edit incluem o mesmo partial |
| **CS-02 — Form de Módulo** | `courses/modules/_form.blade.php`, `courses/modules/create.blade.php`, `courses/modules/edit.blade.php` | idem |
| **CS-03 — Form de Organização** | `organizations/_form.blade.php`, `organizations/create.blade.php`, `organizations/edit.blade.php` | idem |
| **CS-04 — Form de Lição** | `modules/lessons/_form.blade.php`, `modules/lessons/create.blade.php`, `modules/lessons/edit.blade.php`, **+ novo `resources/js/modules/LessonForm.js`** | partial compartilhado + script inline a extrair |
| **CS-05 — Lista ordenável** | `courses/modules/_list.blade.php`, `courses/modules/index.blade.php`, `modules/lessons/index.blade.php`, `quizzes/partials/_question-list.blade.php`, **+ `<x-ui.sortable-list>`/`<x-ui.sortable-item>`**, **+ `ModuleReorder.js`** | 3 telas consomem o mesmo componente novo e o mesmo módulo JS |
| **CS-06 — Player de lição** | `classroom/lesson.blade.php`, `classroom/partials/_video.blade.php`, `_pdf.blade.php`, `_text-image.blade.php`, `_quiz-placeholder.blade.php`, **+ `LessonPlayer.js`** | 3 partials compartilham o par badge/botão e o mesmo módulo JS |
| **CS-07 — Fórum thread** 🚨 | `forum/show.blade.php`, `forum/partials/_reply.blade.php`, `forum/partials/_edit-history-modal.blade.php`, **+ `ForumPolling.js`**, **+ `ForumReportModal.js`**, **+ `ForumEditHistory.js`** | `ForumPolling.appendReply()` replica o markup de `_reply` em JS |
| **CS-08 — Fórum listagem** | `forum/index.blade.php`, `forum/partials/_topic.blade.php` | index inclui o partial |
| **CS-09 — Quiz builder** | `quizzes/edit.blade.php`, `quizzes/partials/_question-form.blade.php`, `quizzes/partials/_question-list.blade.php`, **+ `quiz-builder.js`** | `<template data-option-template>` deve casar com o markup server-side |
| **CS-10 — Auditoria** | `audit-logs/index.blade.php`, `audit-logs/partials/_diff-modal.blade.php`, **+ `AuditLogDiffModal.js`** | modal único compartilhado pela tabela |
| **CS-11 — Certificados staff** | `certificates/index.blade.php`, **+ novo `resources/js/modules/RevokeCertificateForm.js`** | script inline a extrair |
| **CS-12 — Shell público** | `landing/show.blade.php`, `public/certificates/show.blade.php`, **+ `<x-layout.public>`** | mesmo componente de shell |
| **CS-13 — Importador CSV** | `users/import.blade.php`, **+ `CsvImporter.js`** | JS liga por `[dusk=...]` |
| **CS-14 — Convite** | `convite/show.blade.php`, **+ `SmartInvitationForm.js`** | toggle por `style.display` |
| **CS-15 — Modais globais** ⚠️ | **`ModalManager.js`** + `<x-ui.modal>` | tocado por 6 telas (`certificates/index`, `forum/index`, `forum/show`, `forum/partials/_edit-history-modal`, `quizzes/edit`, `audit-logs/index`). **Deve ser resolvido inteiro na Wave A**, antes de qualquer uma delas |

### 17.2 Telas totalmente paralelizáveis (sem conflito de arquivo com ninguém)

Cada uma pode ser atribuída a um agente independente, simultaneamente, uma vez concluídas as Waves A e B:

`dashboard/index.blade.php` · `organizations/index.blade.php` · `users/index.blade.php` ·
`users/create.blade.php` · `users/edit.blade.php` · `courses/index.blade.php` ·
`courses/enrollments/index.blade.php` · `courses/invitation-links/index.blade.php` ·
`courses/invitation-links/create.blade.php` · `courses/completion-rules/index.blade.php` ·
`quizzes/create.blade.php` · `quizzes/attempts/pending.blade.php` · `quizzes/attempts/show.blade.php` ·
`forum/create.blade.php` · `forum/edit.blade.php` · `forum/moderation/index.blade.php` ·
`student/courses/index.blade.php` · `student/quizzes/show.blade.php` · `classroom/show.blade.php` ·
`profile/edit.blade.php` · `settings/edit.blade.php` · `auth/login.blade.php` ·
`auth/forgot-password.blade.php` · `auth/reset-password.blade.php` · `certificates/pdf.blade.php`

**25 telas independentes.** As 35 restantes pertencem a um dos 15 conjuntos de conflito acima.

### 17.3 Grafo de dependência entre ondas

```
Wave A (fundação + 22 componentes novos + ponte ModalManager + Paginator::useBootstrapFive)
  ├─► Wave B (8 partials)  ────┐
  │                            ├─► Wave D (19 CRUD)
  ├─► Wave C (11 index)  ──────┘
  ├─► Wave E (16 JS-pesadas)   [depende de B para _list/_question-list/_topic]
  └─► Wave F (3 públicas/print) [depende de <x-layout.public>/<x-layout.print> da Wave A]
```

---

## 18. Matriz completa `dusk` × arquivo × teste

> Referência rápida para os agentes: 331 ocorrências de `dusk="..."` na camada de tela,
> distribuídas em 57 dos 60 arquivos (`certificates/pdf.blade.php`, `courses/_form.blade.php`,
> `courses/modules/_form.blade.php` e `organizations/_form.blade.php` **não têm nenhum**).

| Arquivo | Qtd. `dusk` | Teste(s) Dusk |
|---|---|---|
| `audit-logs/index.blade.php` | 12 | `AuditLogUiTest` |
| `audit-logs/partials/_diff-modal.blade.php` | 3 | `AuditLogUiTest` |
| `auth/login.blade.php` | 6 | `Auth/LoginTest` |
| `auth/forgot-password.blade.php` | 4 | `Auth/LoginTest` |
| `auth/reset-password.blade.php` | 5 | `Auth/LoginTest` |
| `certificates/index.blade.php` | 8 | `CertificateRevocationTest` |
| `certificates/pdf.blade.php` | **0** | — (PHPUnit + inspeção manual) |
| `classroom/show.blade.php` | 9 | `MultiOrgStudentClassroomTest`, `CertificateVerificationTest`, `StudentQuizAttemptTest` |
| `classroom/lesson.blade.php` | 1 | `MultiOrgStudentClassroomTest`, `VideoThresholdCompletionTest` |
| `classroom/partials/_video.blade.php` | 2 | `VideoThresholdCompletionTest` |
| `classroom/partials/_pdf.blade.php` | 4 | `LessonMultimediaTest` |
| `classroom/partials/_text-image.blade.php` | 4 | `LessonMultimediaTest` |
| `classroom/partials/_quiz-placeholder.blade.php` | 3 | `StudentQuizAttemptTest` |
| `convite/show.blade.php` | 8 | `MultiOrgEnrollmentTest` |
| `courses/index.blade.php` | 7 | `CourseManagementTest`, `ImpersonateOrgTest` |
| `courses/create.blade.php` | 2 | `CourseManagementTest`, `ImpersonateOrgTest` |
| `courses/edit.blade.php` | 2 | `CourseManagementTest` |
| `courses/_form.blade.php` | **0** | (via create/edit) |
| `courses/completion-rules/index.blade.php` | 8 | `CourseCompletionRuleTest` |
| `courses/enrollments/index.blade.php` | 6 | `UserManagementTest` |
| `courses/invitation-links/index.blade.php` | 4 | **nenhum** |
| `courses/invitation-links/create.blade.php` | 2 | **nenhum** |
| `courses/modules/index.blade.php` | 2 | `ModuleReorderTest` |
| `courses/modules/_list.blade.php` | 6 | `ModuleReorderTest` |
| `courses/modules/create.blade.php` | 2 | `CourseManagementTest` |
| `courses/modules/edit.blade.php` | 2 | `CourseManagementTest` |
| `courses/modules/_form.blade.php` | **0** | (via create/edit) |
| `dashboard/index.blade.php` | 9 | `DashboardDuskTest`, `NavigationMenuDuskTest`, `NotificationBellTest`, `MultiTenantStudentImportTest` |
| `forum/index.blade.php` | 6 | `ForumDuskTest` |
| `forum/show.blade.php` | 17 | `ForumDuskTest` |
| `forum/create.blade.php` | 4 | `ForumDuskTest` |
| `forum/edit.blade.php` | 4 | `ForumDuskTest` |
| `forum/moderation/index.blade.php` | 6 | `ForumDuskTest` |
| `forum/partials/_topic.blade.php` | 5 | `ForumDuskTest` |
| `forum/partials/_reply.blade.php` | 5 | `ForumDuskTest` |
| `forum/partials/_edit-history-modal.blade.php` | 3 | `ForumDuskTest` |
| `landing/show.blade.php` | 3 | `BladeComponentsTest`, `LayoutRenderingTest`, `ExampleSmokeTest` |
| `modules/lessons/index.blade.php` | 6 | `CourseManagementTest`, `LessonMultimediaTest` |
| `modules/lessons/create.blade.php` | 2 | `CourseManagementTest`, `LessonMultimediaTest` |
| `modules/lessons/edit.blade.php` | 2 | `LessonMultimediaTest` |
| `modules/lessons/_form.blade.php` | 7 | `LessonMultimediaTest` |
| `organizations/index.blade.php` | 9 | `OrganizationCrudTest`, `ImpersonateOrgTest`, `Auth/LoginTest` |
| `organizations/create.blade.php` | 2 | `OrganizationCrudTest` |
| `organizations/edit.blade.php` | 2 | `OrganizationCrudTest` |
| `organizations/_form.blade.php` | **0** | (via create/edit) |
| `profile/edit.blade.php` | 4 | `ProfileTest` |
| `public/certificates/show.blade.php` | 8 | `CertificateVerificationTest`, `CertificateRevocationTest` |
| `quizzes/create.blade.php` | 8 | `EssayGradingScreenTest` |
| `quizzes/edit.blade.php` | 9 | `EssayGradingScreenTest` |
| `quizzes/partials/_question-form.blade.php` | 11 | `EssayGradingScreenTest` |
| `quizzes/partials/_question-list.blade.php` | 6 | `EssayGradingScreenTest` |
| `quizzes/attempts/pending.blade.php` | 3 | `EssayGradingScreenTest` |
| `quizzes/attempts/show.blade.php` | 6 | `EssayGradingScreenTest` |
| `settings/edit.blade.php` | 2 | `DashboardDuskTest` |
| `student/courses/index.blade.php` | 5 | `MultiOrgStudentClassroomTest`, `HelpCenterDuskTest`, `NavigationMenuDuskTest`, `NotificationBellTest` |
| `student/quizzes/show.blade.php` | 13 | `StudentQuizAttemptTest` |
| `users/index.blade.php` | 8 | `UserManagementTest`, `NavigationMenuDuskTest` |
| `users/create.blade.php` | 2 | `UserManagementTest`, `NavigationMenuDuskTest` |
| `users/edit.blade.php` | 4 | `UserManagementTest` |
| `users/import.blade.php` | 8 | `MultiTenantStudentImportTest` |

### 18.1 Telas SEM cobertura Dusk (verificação manual obrigatória)

- `courses/invitation-links/index.blade.php`
- `courses/invitation-links/create.blade.php`
- `certificates/pdf.blade.php` (por natureza — saída PDF)

Para as duas primeiras, recomenda-se **escrever um `InvitationLinkDuskTest` antes de migrar**
(TDD de regressão visual), cobrindo: gerar link, listar, revogar, estado expirado.

### 18.2 Suíte completa de verificação pós-onda

```bash
vendor/bin/sail artisan test --compact          # PHPUnit — sem regressão de contrato
vendor/bin/sail npm run build                   # Vite — CSS/JS novos compilados
vendor/bin/sail artisan dusk                    # 28 arquivos de teste de browser
vendor/bin/sail bin pint --dirty --format agent # estilo PHP
```

> ⚠️ Sempre rodar `vendor/bin/sail npm run build` antes de `dusk` — vários módulos JS
> (`ForumPolling`, `NotificationBell`, `CsvImporter`, `LessonPlayer`) só chegam ao browser
> pelo bundle em `public/build`, e um bundle velho faz testes de UI falharem por motivos que
> nada têm a ver com o Blade migrado.

---

## 19. Achados colaterais registrados durante o inventário

1. **`QuizBuilder`/`QuizTimer` estão fora de `resources/js/modules/`** — vivem em
   `resources/js/quiz-builder.js` e `resources/js/quiz-timer.js`, ao contrário dos outros 12
   módulos. Padronizar (mover para `modules/`) é uma boa oportunidade durante a Wave E, mas
   exige atualizar os `import` de `resources/js/app.js`.
2. **`ForumPolling.js` duplica markup de Blade em JS** (§0.4, CS-07). É o maior risco de
   divergência visual silenciosa da migração inteira.
3. **`CsvImporter.js` acopla-se a atributos `dusk`**, não a `data-*`. Viola a convenção do
   resto do projeto e transforma seletores de teste em API de produção.
4. **BUG-004 (botão de dismiss do alerta inerte)** é resolvido gratuitamente pela Wave A ao
   incluir `bootstrap.bundle.js` e usar `.alert-dismissible` + `btn-close[data-bs-dismiss]`.
5. **`certificates/pdf.blade.php` usa `#7a5cff` no `.kicker`**, divergente de
   `--color-accent: #ec3013`. Corrigir como literal hex (§0.5).
6. **`dashboard/index.blade.php` usa `grid-template-columns: repeat(4, 1fr)` fixo** — a tela
   não é responsiva hoje. A migração para `.row-cols-*` corrige isso.
7. **Dois blocos `<script>` inline em telas** (`certificates/index.blade.php` e
   `modules/lessons/_form.blade.php`) devem virar módulos JS reais
   (`RevokeCertificateForm.js`, `LessonForm.js`), alinhando com a arquitetura SOLID de JS
   documentada em `frontend-architecture`.
8. **Padrão `<x-slot:title>` inconsistente**: apenas `dashboard/index`, `settings/edit` e
   `audit-logs/index` definem título de página. As outras 57 telas herdam o título default do
   layout. Uniformizar durante a migração (baixo custo, alto ganho de UX/SEO interno).
