# 04 — Camada JavaScript e Pipeline de Build: Estado Atual e Alvo Bootstrap 5.3

> Documento de auditoria e plano-alvo. Escopo: `resources/js/**`, `resources/css/app.css`,
> `vite.config.js`, `package.json` e o contrato DOM que esses arquivos exigem das views Blade.
> Fora de escopo: reescrita dos 634 `style="..."` inline (documento 02/03 desta série).

---

## 0. Sumário de achados (leia isto primeiro)

| # | Achado | Severidade |
|---|--------|-----------|
| A1 | **O JavaScript do Bootstrap nunca é carregado.** `resources/css/app.css` importa apenas `bootstrap-grid.min.css` e `bootstrap-utilities.min.css`; nenhum arquivo importa `bootstrap/dist/js/*`. Popper não está instalado. Logo, **100% do comportamento interativo é hand-rolled**. | Crítico |
| A2 | **Alpine.js não está instalado**, mas as views usam `x-data`, `x-show`, `x-cloak`, `@click`, `x-transition`, `@keydown.escape.window`, `@click.outside`. Todos são atributos HTML inertes. Isso é a raiz do **BUG-003** (modal sempre aberto), do **BUG-004** (botão de dismiss do alert inerte) e do **menu mobile morto** (`sidebarOpen` em `layouts/app.blade.php:13`). | Crítico |
| A3 | **A tipografia está quebrada hoje.** `public/fonts/archivo/archivo-v19-latin-{400,600,800}.woff2` têm **0 bytes** (`du -b` confirmado). O `@font-face` aponta para arquivos vazios → o browser cai no fallback `system-ui`. Simultaneamente, o plugin `bunny('Instrument Sans')` **baixa e emite** 6 arquivos de fonte (ver `public/build/fonts-manifest.json`) que **nenhuma regra CSS referencia** (`--font-heading`/`--font-body` apontam para `"Archivo"`). Ou seja: paga-se o download de uma fonte morta e a fonte pretendida não carrega. | Alto |
| A4 | **Tailwind é dependência morta.** `resources/css/app.css` não contém `@import "tailwindcss"` nem `@tailwind`; o plugin `tailwindcss()` roda em todo build sem produzir uma única classe utilizada. | Médio |
| A5 | `resources/js/app.js` instancia **tudo** em escopo de módulo e chama `.init()` de 11 dos 14 módulos dentro de **um único** `DOMContentLoaded`. `ModalManager` e `HttpClient` são singletons `export default new X()` — `ModalManager` chama `this.init()` **no próprio construtor**, então já está ativo antes do `DOMContentLoaded` do `app.js`. Inconsistência de ciclo de vida. | Médio |
| A6 | `NotificationService` produz um toast com `class="toast-notification alert-item variant-{type}"` e um `<button class="btn-close">` — **nomes que colidem com o Bootstrap** (`.toast`, `.btn-close`) mas sem o CSS/JS do Bootstrap por trás. Migrar resolve a colisão. | Médio |
| A7 | `ForumPolling.appendReply()` injeta markup com `el.style.cssText = '...'` — **o único ponto onde JS gera estilo inline novo em runtime**. Precisa virar classes na migração, senão o `.card`/`.border` do Bootstrap nunca se aplica às respostas novas. | Médio |
| A8 | A suíte Dusk roda contra `public/build/`. O CI (`.github/workflows/ci.yml:58`) faz `npm ci && npm run build`, mas **localmente não há hook**: um build velho quebra Dusk silenciosamente. 8 arquivos de teste Dusk dependem diretamente de comportamento JS. | Alto |

---

## 1. Inventário completo de `resources/js/`

```
resources/js/
├── app.js                    (44 linhas)  — entrypoint
├── quiz-builder.js           (181)        — fora de modules/ (inconsistência)
├── quiz-timer.js             (65)         — fora de modules/ (inconsistência)
└── modules/
    ├── AuditLogDiffModal.js  (88)
    ├── CsvImporter.js        (181)
    ├── ForumEditHistory.js   (50)
    ├── ForumPolling.js       (101)
    ├── ForumReportModal.js   (74)
    ├── HttpClient.js         (111)
    ├── LessonPlayer.js       (183)
    ├── ModalManager.js       (152)
    ├── ModuleReorder.js      (83)
    ├── NotificationBell.js   (172)
    ├── NotificationService.js (99)
    └── SmartInvitationForm.js (100)
```
Total: **1.684 linhas** de JS de aplicação (fora `app.css`).

### 1.1 `resources/js/app.js`

- **Responsabilidade:** importar, instanciar, publicar em `window.*` e inicializar todos os módulos.
- **Dependências:** os 14 módulos.
- **API pública:** `window.HttpClient`, `window.ModalManager`, `window.NotificationService`,
  `window.CsvImporter`, `window.ModuleReorder`, `window.SmartInvitationForm`, `window.LessonPlayer`,
  `window.QuizBuilder`, `window.QuizTimer`, `window.ForumPolling`, `window.ForumReportModal`,
  `window.ForumEditHistory`, `window.NotificationBell`, `window.AuditLogDiffModal`.
- **Contrato DOM:** nenhum diretamente.
- **Eventos:** um `DOMContentLoaded`.
- **Endpoints:** nenhum.
- **Observações:** `HttpClient`, `ModalManager` e `NotificationService` são atribuídos como *instância
  default do módulo* (singleton), os demais como `new X(...)`. `ModalManager` e `NotificationService`
  **não** recebem `.init()` no bloco `DOMContentLoaded` (o primeiro auto-inicializa no construtor;
  o segundo é lazy). `window.LessonPlayer.reportProgress(...)` é **seam de teste Dusk**
  (`VideoThresholdCompletionTest`) — não pode sumir.

### 1.2 `modules/HttpClient.js`

- **Responsabilidade:** wrapper sobre `fetch` com CSRF, headers JSON e normalização de erro.
- **Construtor:** `constructor(baseURL = '')`. Sem dependências.
- **API pública:** `getCsrfToken()`, `getDefaultHeaders()`, `request(url, options)`,
  `get/post/put/patch/delete`. Export default = **instância singleton**.
- **Contrato DOM:** `meta[name="csrf-token"]` (presente em `layouts/app.blade.php:6`); fallback no
  cookie `XSRF-TOKEN`.
- **Eventos:** nenhum.
- **Endpoints:** agnóstico (recebe URL do chamador).
- **Retorno:** `{ ok, status, data, headers }`; erro lançado carrega `.status`, `.data`, `.response`.
- **Veredito de migração:** **MANTER INTEGRALMENTE.** Bootstrap não tem camada HTTP.

### 1.3 `modules/ModalManager.js`

- **Responsabilidade:** abrir/fechar modais, backdrop, Esc, click-outside, focus inicial, stack.
- **Construtor:** sem dependências; **chama `this.init()` dentro do construtor**.
- **API pública:** `open(idOrEl)`, `close(idOrEl)`, `closeElement(el)`, `closeTopModal()`,
  `closeAll()`, `toggle(idOrEl)`, `hideBackdropsOnLoad()`, `bindGlobalEvents()`; estado `activeModals[]`.
- **Contrato DOM (delegação global em `document`):**
  - Trigger: `[data-modal-target="{id}"]`
  - Dismiss: `[data-modal-dismiss]`
  - Container do modal: `#{id}`, e ancestral `.dialog-backdrop`
  - Seletores de fechamento: `.dialog, [role="dialog"], .modal`
  - Backdrop clicável: `.dialog-backdrop`, `.modal-backdrop`
  - Classes aplicadas: `active`, `open`, `show` no modal; `active`, `show` no backdrop;
    `modal-open` no `<body>`
  - Atributos: `aria-hidden`, `aria-modal`
  - Estilo direto: `modal.style.display = 'block'|'none'`, `backdrop.style.display = 'flex'|'none'`
  - Foco: primeiro `button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])`
- **Eventos ouvidos:** `click` (document), `keydown` (Escape).
- **Endpoints:** nenhum.
- **Consumidores:** 10 views usam `data-modal-target` — `forum/index`, `forum/show`,
  `forum/partials/_reply`, `forum/partials/_edit-history-modal`, `certificates/index`,
  `quizzes/edit`, `quizzes/partials/_question-list`, `components/help-button` (2×),
  `audit-logs/index`.
- **Veredito:** **SUBSTITUIR 100% por `bootstrap.Modal`.**

### 1.4 `modules/NotificationService.js`

- **Responsabilidade:** toasts efêmeros.
- **Construtor:** `constructor(containerId = 'notification-container')`. Export default = singleton.
- **API pública:** `show(message, type, {duration})`, `success/error/warning/info(message, opts)`,
  `dismiss(toastEl)`, `getOrCreateContainer()`.
- **Contrato DOM:** cria `#notification-container` com
  `class="notification-container position-fixed top-0 end-0 p-3"` **+ `style` inline redundante**;
  cada toast recebe `class="toast-notification alert-item variant-{type}"` e um
  `<button class="btn-close">&times;</button>` com `aria-label="Fechar"`.
- **Eventos:** `click` no botão de fechar; `setTimeout` de 5000 ms padrão.
- **Endpoints:** nenhum.
- **Consumidores:** `ModuleReorder`, `SmartInvitationForm`, `LessonPlayer`, `QuizBuilder`,
  `ForumReportModal` (via `notify(type, msg)`).
- **Veredito:** **SUBSTITUIR por `bootstrap.Toast`** mantendo a assinatura
  `success/error/warning/info` (5 chamadores dependem dela).

### 1.5 `modules/NotificationBell.js`

- **Responsabilidade:** sino da topbar — polling de contagem, dropdown, marcar lida(s).
- **Construtor:** `constructor(httpClient, intervalMs = 30000)`.
- **API pública:** `init()`, `bind()`, `toggleDropdown(el)`, `closeDropdown(el)`, `startPolling()`,
  `refreshUnreadCount()`, `updateBadge(count)`, `markAllRead()`, `handleItemClick(e, item)`, `stop()`.
- **Contrato DOM** (todos confirmados em `components/notifications-bell.blade.php`):
  - `[data-notifications-bell]` + `dusk="notifications-bell"` — container; atributos
    `data-unread-count-url`, `data-mark-all-read-url`
  - `[data-notifications-toggle]` + `dusk="notifications-toggle"`
  - `[data-notifications-dropdown]` + `dusk="notifications-dropdown"` (`style="display:none"`)
  - `[data-notifications-badge]` + `dusk="notifications-badge"`
  - `[data-notifications-mark-all]` + `dusk="notifications-mark-all-read"`
  - `[data-notifications-item]` + `dusk="notifications-item-{id}"`, com `data-notification-id`,
    `data-mark-read-url`, `href` = `data.action_url`
  - `[data-notifications-list]`
- **Eventos:** `click` no toggle (com `stopPropagation`), `click` no document (fecha fora),
  `keydown` Escape, `click` em cada item, `click` no "marcar todas".
- **Endpoints:** `GET notifications.unread-count` (30 s), `PATCH notifications.read-all`,
  `PATCH notifications.read/{id}`.
- **Veredito:** **HÍBRIDO** — o dropdown vira `bootstrap.Dropdown`; o polling e as chamadas HTTP
  permanecem.

### 1.6 `modules/CsvImporter.js`

- **Responsabilidade:** ler CSV no cliente, validar cabeçalho, fatiar em lotes de 50 e POSTar em série.
- **Construtor:** `constructor(httpClient)`; `chunkSize = 50`; `requiredColumns = ['name','email']`.
- **API pública:** `init()`, `bind()`, `handleSubmit(form)`, `readFileAsText(file)`, `splitLines(text)`,
  `extractHeader(text)`, `parseCsv(text)`, `chunkRows(rows, size)`, `setProgress(form, done, total)`,
  `showResults(form, message, type)`.
- **Contrato DOM** (confirmado em `users/import.blade.php`):
  - `[dusk="csv-import-form"]` com `data-chunk-url="{{ route('users.import.chunk') }}"`
  - `[dusk="csv-file-input"]`, `[dusk="csv-course-select"]`
  - `[dusk="csv-import-progress-wrapper"]` (`display:none` → `flex`)
  - `[dusk="csv-import-progress-bar"]` (`style.width = "{n}%"`)
  - `[dusk="csv-import-progress-text"]`
  - `[dusk="csv-import-results"]` (`display`, `textContent`, `style.color`)
  - **Nota:** este módulo usa `dusk=` como seletor funcional, não `data-*`. É o único que faz isso
    de forma sistemática.
- **Eventos:** `submit` no form (com `preventDefault`).
- **Endpoints:** `POST users.import.chunk` com `{ course_id, filename, rows[] }`.
- **Veredito:** **HÍBRIDO** — parsing/chunking/HTTP permanecem; a barra de progresso vira
  `.progress > .progress-bar` e a área de resultado vira `.alert`.

### 1.7 `modules/ModuleReorder.js`

- **Responsabilidade:** drag-and-drop nativo HTML5 para reordenar módulos/aulas/questões.
- **Construtor:** `constructor(httpClient, notificationService)`.
- **API pública:** `init()`, `bind()`, `bindList(list)`, `persistOrder(list)`, `notify(type, msg)`.
- **Contrato DOM:** `[data-reorder-url]` (a lista) e `[data-id]` (cada item arrastável).
- **Eventos:** `dragstart`, `dragover`, `drop`, `dragend`.
- **Endpoints:** `POST {data-reorder-url}` com `{ ordered_ids: number[] }`.
- **Veredito:** **MANTER.** Bootstrap 5.3 não tem plugin de sortable/drag-and-drop.

### 1.8 `modules/SmartInvitationForm.js`

- **Responsabilidade:** formulário de convite adaptativo (RF03) — mostra/esconde campos conforme
  o e-mail já exista.
- **Construtor:** `constructor(httpClient, notificationService)`; `debounceMs = 400`.
- **API pública:** `init()`, `bind()`, `bindForm(form)`, `checkEmail(form, field)`,
  `toggleFields(form, exists)`, `notify()`.
- **Contrato DOM:** `[data-check-email-url]` (o form), `[data-invitation-email]`,
  `[data-invitation-field="new-account"]` (n×), `[data-invitation-field="existing-account-hint"]`;
  usa `dataset.originallyRequired` para preservar `required`.
- **Eventos:** `blur` (imediato) e `input` (debounced 400 ms) no campo de e-mail.
- **Endpoints:** `POST {data-check-email-url}` com `{ email }` → `{ exists: bool }`.
- **Veredito:** **MANTER** a lógica; opcionalmente trocar `style.display` por `.d-none`.
  (`bootstrap.Collapse` **não** serve aqui: precisamos de troca instantânea sem animação e com
  toggle de `required`.)

### 1.9 `modules/LessonPlayer.js`

- **Responsabilidade:** (a) player YouTube + reporte de progresso a cada 5 s; (b) conclusão manual.
- **Construtor:** `constructor(httpClient, notificationService)`.
- **API pública:** `init()`, `bind()`, `bindManualCompletion()`, `markComplete(button)`,
  `bindVideoPlayers()`, `loadYouTubeApi(cb)`, `createPlayer(container)`,
  `startPolling(lessonId, player)`, **`reportProgress(lessonId, watched, duration)` (seam Dusk)**,
  `resolveProgressUrl(lessonId)`, `reflectCompletion(data)`, `notify()`.
- **Contrato DOM:** `[data-mark-complete-url]` (botão), `[data-youtube-player]` com
  `data-lesson-id`, `data-video-id`, `data-progress-url` e um `id` (exigido pelo `YT.Player`),
  `[data-completion-badge]` (revelado com `style.display='inline-flex'`).
- **Eventos:** `click` no botão; `setInterval` 5 s; callback global `window.onYouTubeIframeAPIReady`.
- **Endpoints:** `POST {data-progress-url}` (`lessons.progress`) com
  `{ watched_seconds, duration_seconds }`; `POST {data-mark-complete-url}` (`lessons.complete`).
  Ambos respondem `{ is_completed: bool }`. Script externo: `https://www.youtube.com/iframe_api`.
- **Veredito:** **MANTER.** Só o `reflectCompletion()` muda (`.d-none` em vez de `style.display`),
  e a barra de progresso da aula pode virar `.progress`.

### 1.10 `modules/ForumPolling.js`

- **Responsabilidade:** polling incremental de respostas do fórum (10 s, `since_id`).
- **Construtor:** `constructor(httpClient, intervalMs = 10000)`; `timers: Map`.
- **API pública:** `init()`, `bind()`, `bindContainer(el)`, `appendReply(container, reply)`,
  `stop(container)`.
- **Contrato DOM:** `[data-forum-polling]` com `data-fetch-url` e `data-last-id`;
  gera `[data-reply-id="{id}"]` + `dusk="reply-{id}"` e `dusk="reply-content-{id}"`.
- **Eventos:** `setInterval` 10 s; falha é engolida silenciosamente (429 do `throttle:60,1`).
- **Endpoints:** `GET {data-fetch-url}?since_id={n}` → `{ data: [{id, content, created_at, user:{name}}] }`.
- **Veredito:** **MANTER a lógica**, mas **refatorar `appendReply()`**: hoje ele escreve
  `el.style.cssText = 'padding: 14px 16px; border: 1px solid var(--color-divider); ...'`. No alvo,
  deve **clonar um `<template>` server-rendered** ou aplicar classes Bootstrap
  (`card mb-2`, `card-body`), senão as respostas novas destoam das server-rendered.

### 1.11 `modules/ForumReportModal.js`

- **Responsabilidade:** preencher e submeter o modal de denúncia (RF26).
- **Construtor:** `constructor(httpClient, notificationService, modalManager)`.
- **API pública:** `init()`, `bind()`, `prefill(button)`, `submit(event, form)`, `notify()`.
- **Contrato DOM:** `[data-forum-report-button]` com `data-postable-type` e `data-postable-id`
  (também carrega `data-modal-target="report-modal"`); `[data-forum-report-form]` com `action`;
  `[data-forum-report-postable-type]`, `[data-forum-report-postable-id]`, `[name="reason"]`;
  fecha via `modalManager.close('report-modal')`.
- **Eventos:** `click` no botão, `submit` no form.
- **Endpoints:** `POST {form.action}` (`forum-reports.store`) com
  `{ postable_type, postable_id, reason }`.
- **Veredito:** **HÍBRIDO** — prefill e submit permanecem; abertura/fechamento migram para
  `bootstrap.Modal` + evento `show.bs.modal` (`event.relatedTarget` substitui o listener de click
  no botão — API nativa do Bootstrap para exatamente este caso).

### 1.12 `modules/ForumEditHistory.js`

- **Responsabilidade:** abrir o modal de histórico de edição (conteúdo já server-rendered).
- **Construtor:** `constructor(modalManager)`.
- **API pública:** `init()`, `bind()`, `hideBackdrops()`.
- **Contrato DOM:** `[data-edit-history-trigger]` com `data-modal-target`; modais com
  `id^="edit-history-"`; força `.dialog-backdrop { display: none }`.
- **Endpoints:** nenhum.
- **Veredito:** **ELIMINAR O MÓDULO INTEIRO.** Com `data-bs-toggle="modal"` +
  `data-bs-target="#edit-history-{id}"`, zero JS é necessário. `hideBackdrops()` existe só porque
  o Alpine é inerte — problema que deixa de existir.

### 1.13 `modules/AuditLogDiffModal.js`

- **Responsabilidade:** popular o `#audit-diff-modal` compartilhado com o JSON da linha clicada.
- **Construtor:** `constructor(modalManager)`.
- **API pública:** `init()`, `bind()`, `render(button)`, `formatJson(raw)`, `hideBackdrop()`.
- **Contrato DOM:** `[data-audit-diff-trigger]` com `data-event`, `data-old-values`,
  `data-new-values`, `data-modal-target="audit-diff-modal"`; `#audit-diff-modal` contendo
  `[dusk="audit-diff-event"]`, `[dusk="audit-diff-old"]`, `[dusk="audit-diff-new"]`.
- **Endpoints:** nenhum (JSON já inline nos `data-*`).
- **Veredito:** **REDUZIR** — mantém-se apenas `render()`/`formatJson()`, disparados por
  `show.bs.modal` + `event.relatedTarget`. `hideBackdrop()` e a delegação para o `ModalManager`
  desaparecem.

### 1.14 `quiz-builder.js`

- **Responsabilidade:** builder dinâmico de questões/opções (RF08).
- **Construtor:** `constructor(notificationService)`.
- **API pública:** `init()`, `bind()`, `currentType(suffix)`, `applyTypeBehavior(suffix)`,
  `enforceSingleCorrect(form, checkbox)`, `addOption(suffix)`, `applyRowDisabledState(row, suffix)`,
  `removeOption(form, button)`, `notify()`.
- **Contrato DOM (tudo sufixado por `formSuffix`):** `[data-question-type-select="{s}"]`,
  `[data-options-container="{s}"]`, `[data-essay-hint="{s}"]`, `[data-add-option-btn="{s}"]`,
  `[data-options-list="{s}"]`, `template[data-option-template="{s}"]` (usa placeholder
  `__INDEX__`), `[data-question-form="{s}"]`, `[data-option-row]`, `[data-correct-checkbox]`,
  `[data-remove-option-btn]`, `input[type="text"]`, `input[type="hidden"]` (o `options[i][id]`).
- **Eventos:** `change` no select de tipo, `click` em add/remove (delegado no form),
  `change` delegado nos checkboxes.
- **Endpoints:** nenhum (submit normal de formulário).
- **Veredito:** **MANTER.** Só trocar `style.display = 'none'|'flex'|'inline-flex'|'block'` por
  `classList.toggle('d-none', ...)`.

### 1.15 `quiz-timer.js`

- **Responsabilidade:** countdown cosmético do quiz (RF09).
- **Construtor:** sem dependências.
- **API pública:** `init()`, `bind()`, `tick(container, deadline)`, `formatRemaining(ms)`.
- **Contrato DOM:** `[data-quiz-timer]` (em `student/quizzes/show.blade.php:73`) com
  `data-started-at` e `data-time-limit-minutes`. Escreve `textContent` e
  `style.color = 'var(--color-accent-2, #e15b47)'`.
- **Eventos:** `setInterval` 1 s.
- **Endpoints:** nenhum. Nunca submete o form (por design — a validação é server-side em
  `SubmitQuizAttemptAction`).
- **Veredito:** **MANTER a lógica**, apresentar como `.badge` + `.progress` (ver §2).

---

## 2. Tabela DE ↔ PARA — comportamento hand-rolled → API nativa Bootstrap 5.3

### 2.1 Substituições diretas (o Bootstrap faz nativamente)

| DE (hoje) | PARA (Bootstrap 5.3) | Ganho |
|---|---|---|
| `ModalManager.open(id)` / `[data-modal-target="x"]` | `data-bs-toggle="modal"` + `data-bs-target="#x"` | Auto-init via data-api |
| `ModalManager.close()` / `[data-modal-dismiss]` | `data-bs-dismiss="modal"` (tipicamente em `<button class="btn-close">`) | Zero JS |
| `ModalManager` chamado por JS | `bootstrap.Modal.getOrCreateInstance(el).show()` / `.hide()` | API idêntica em intenção |
| `.dialog-backdrop` custom + `display:flex/none` | `.modal.fade` + `.modal-dialog` + backdrop gerado pelo plugin | Backdrop, `overflow` do body e scrollbar-compensation nativos |
| Foco no primeiro focável (`ModalManager.open`) | Focus trap nativo do plugin + `autofocus` / `.modal` `tabindex="-1"` | Acessibilidade correta (trap, não só foco inicial) |
| `keydown` Escape em `ModalManager` | opção `keyboard: true` (padrão) | Nativo |
| Click no backdrop fecha (`ModalManager`) | opção `backdrop: true` (padrão) / `'static'` | Nativo |
| `activeModals[]` (stack manual) | Suporte nativo a modais empilhados (5.3) | Menos estado |
| Callbacks manuais pós-abertura | Eventos `show.bs.modal`, `shown.bs.modal`, `hide.bs.modal`, `hidden.bs.modal`, `hidePrevented.bs.modal` | Ganchos padronizados |
| `ForumReportModal.prefill()` ligado ao `click` do botão | `show.bs.modal` + `event.relatedTarget` (padrão documentado "Varying modal content") | Elimina um listener e o acoplamento ao `ModalManager` |
| `AuditLogDiffModal.render()` ligado ao `click` | idem `show.bs.modal` + `relatedTarget` | idem |
| `NotificationService.show()` (div + inline style) | `bootstrap.Toast` sobre `.toast` dentro de `.toast-container.position-fixed.top-0.end-0.p-3` | CSS pronto, `autohide`, `delay`, eventos `show.bs.toast`/`shown.bs.toast`/`hide.bs.toast`/`hidden.bs.toast` |
| `NotificationService.dismiss()` + fade manual | `data-bs-dismiss="toast"` + `.fade` | Zero JS |
| `NotificationBell.toggleDropdown()` + `display:block/none` + click-outside + Esc | `bootstrap.Dropdown` (`data-bs-toggle="dropdown"`, `.dropdown-menu`) | Click-outside, Esc, navegação por setas e posicionamento Popper nativos |
| Eventos manuais do sino | `show.bs.dropdown`, `shown.bs.dropdown`, `hide.bs.dropdown`, `hidden.bs.dropdown` | `shown.bs.dropdown` substitui o `refreshUnreadCount()` acoplado ao toggle |
| `sidebarOpen` (Alpine inerte) + `.mobile-sidebar-drawer` + `.mobile-sidebar-backdrop` | `bootstrap.Offcanvas` (`data-bs-toggle="offcanvas"`, `data-bs-target="#sidebar"`, `.offcanvas.offcanvas-start`) + `data-bs-dismiss="offcanvas"` | **Corrige o menu mobile morto**; eventos `show/shown/hide/hidden.bs.offcanvas` |
| `<x-ui.alert dismissable>` com `@click="show=false"` (**BUG-004**) | `.alert.alert-dismissible.fade.show` + `<button class="btn-close" data-bs-dismiss="alert">` | **BUG-004 é resolvido de graça** — ver §2.3 |
| `CsvImporter.setProgress()` (`bar.style.width`) | `.progress` + `.progress-bar` com `style="width:{n}%"` + `aria-valuenow` | Componente CSS pronto; `.progress-bar-striped.progress-bar-animated` opcional |
| `CsvImporter.showResults()` (`style.color` por tipo) | `.alert.alert-success` / `.alert.alert-danger` | Semântica + cor via `$theme-colors` |
| `QuizTimer.tick()` (`style.color`) | `<span class="badge text-bg-secondary">` → `text-bg-danger` ao esgotar + `.progress` opcional para o tempo restante | Consistência visual |
| `ForumEditHistory` (módulo inteiro) | `data-bs-toggle="modal"` no `[data-edit-history-trigger]` | **Módulo deletado** |
| `LessonPlayer.reflectCompletion()` (`style.display='inline-flex'`) | `badge.classList.remove('d-none')` | Utilitário nativo |
| `SmartInvitationForm.toggleFields()` (`style.display`) | `field.classList.toggle('d-none', exists)` | idem |
| `body.classList.add('modal-open')` manual | gerido pelo plugin | — |

### 2.2 Oportunidades adicionais (Collapse / Tab / Tooltip / Popover / Scrollspy)

| Componente | Onde aplicar | Justificativa |
|---|---|---|
| `bootstrap.Collapse` | Sidebar do player de aula (lista de módulos → aulas, `learning/`), accordion de FAQ na Landing Page, filtros avançados em `audit-logs/index` e `users/index` | Substitui expand/collapse que hoje simplesmente não existe (tudo aberto). `.accordion` cobre o caso módulo→aulas do classroom. |
| `bootstrap.Tab` | `quizzes/edit` (Questões / Configurações), tela de perfil (`Dados` / `Senha` — hoje **dois forms independentes** `dusk="profile-form"` e `dusk="password-form"` empilhados), `courses/show` (Visão geral / Módulos / Fórum / Certificado) | Reduz scroll vertical sem JS custom. **Atenção Dusk:** conteúdo em aba inativa não é "visible" — testes que fazem `assertSee` em painéis ocultos quebram. |
| `bootstrap.Tooltip` | Ícones-only (`btn-icon` de ajuda, sino, ações de tabela com apenas SVG) | Hoje há só `aria-label`; tooltip dá affordance visual. **Requer Popper** e **init explícito** (`data-bs-toggle="tooltip"` **não** auto-inicializa — é opt-in por performance). |
| `bootstrap.Popover` | Botão de ajuda contextual (`<x-help-button>`) quando o artigo for curto | Alternativa mais leve ao modal; manter modal para artigos longos. Também opt-in. |
| `bootstrap.Scrollspy` | Página de artigo de ajuda longa / termos | Baixa prioridade. |
| `bootstrap.Carousel` | — | Não aplicável; não usar. |

### 2.3 BUG-004 — resolvido de graça pela adoção do plugin Alert

**Declaração explícita, conforme solicitado:**

> **A adoção do `bootstrap.Alert` resolve o BUG-004 sem escrever uma linha de JavaScript de
> aplicação.** O bug é causado por `x-data="{ show: true }"` / `@click="show = false"` em
> `resources/views/components/ui/alert.blade.php` serem atributos inertes (Alpine não instalado).
> Ao reescrever o componente como `.alert.alert-dismissible.fade.show` com
> `<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar alerta">`,
> o próprio `bootstrap/js/dist/alert.js` registra o handler de dismiss no `document` em tempo de
> carga do módulo (`enableDismissTrigger(Alert, 'close')` — verificado em
> `node_modules/bootstrap/js/dist/alert.js:79`). Cada alerta fecha independentemente, com fade,
> e o botão continua acessível por teclado (é um `<button>` real). Isso satisfaz **todos** os
> critérios de aceite da §6 do BUG-004, incluindo *"nenhuma dependência nova foi adicionada ao
> `package.json`"* — `bootstrap@^5.3.3` **já é dependência declarada**; a migração apenas passa a
> usar o JS que já está instalado. **O `AlertDismisser.js` sugerido na §4 do report do bug torna-se
> desnecessário e NÃO deve ser criado.**

O mesmo raciocínio vale para o **BUG-003** (modal sempre aberto): a causa é `x-show`/`x-cloak`
inertes em `components/ui/modal.blade.php`, contornados hoje por `hideBackdropsOnLoad()` e três
cópias de `hideBackdrop(s)()` espalhadas. Com `.modal` do Bootstrap, o estado fechado é o padrão
do CSS (`.modal { display: none }`), e as quatro rotinas de "esconder backdrop no load" somem.

### 2.4 Módulos SEM equivalente Bootstrap — **MANTER**

| Módulo | Por quê | O que muda mesmo assim |
|---|---|---|
| `HttpClient` | Bootstrap não tem camada HTTP | Nada. |
| `ForumPolling` | Bootstrap não faz polling | `appendReply()` para de escrever `style.cssText`; passa a clonar um `<template>` ou aplicar classes. |
| `ModuleReorder` | Bootstrap não tem sortable/drag-and-drop | Toast de sucesso/erro passa pelo `NotificationService` já migrado para `bootstrap.Toast`. |
| `LessonPlayer` | YouTube IFrame API é externa | `reflectCompletion()` usa `.d-none`; preserva `reportProgress()` como seam Dusk. |
| `QuizBuilder` | Builder dinâmico é lógica de domínio | `style.display` → `.d-none`; `notify()` via Toast. |
| `QuizTimer` | Countdown é lógica de domínio | Renderiza `.badge` em vez de `style.color`. |
| `SmartInvitationForm` | Regra `exists` + toggle de `required` é de domínio | `style.display` → `.d-none`. |
| `CsvImporter` (parsing/chunking) | FileReader + split + lotes de 50 é de domínio | Progresso → `.progress`; resultado → `.alert`. |

> **Regra transversal:** nenhum desses módulos pode continuar manipulando modal/toast por conta
> própria. Todos passam a **consumir a API do Bootstrap** (`Modal.getOrCreateInstance`,
> `new Toast(el).show()`), nunca `element.style.display = ...`.

---

## 3. Pipeline de build alvo

### 3.1 Bundle vs. imports ESM por componente — **RECOMENDAÇÃO: `bootstrap.bundle.min.js`**

**Fatos verificados** (em `node_modules/bootstrap@5.3.8`):

| Arquivo | Tamanho (não-gzip) | Popper incluso |
|---|---|---|
| `dist/js/bootstrap.bundle.min.js` | 80.496 B (~23 KB gzip) | **Sim** |
| `dist/js/bootstrap.min.js` | 60.539 B | Não |
| `dist/js/bootstrap.esm.min.js` | 73.811 B | Não |

**Sobre auto-init por `data-bs-*`:** cada arquivo em `bootstrap/js/dist/*.js` **registra os
listeners de data-api no momento da avaliação do módulo**. Verificado:
`js/dist/modal.js:284` → `EventHandler.on(document, EVENT_CLICK_DATA_API, '[data-bs-toggle="modal"]', ...)`;
`js/dist/alert.js:79` → `enableDismissTrigger(Alert, 'close')`.
Logo, **um componente só responde a `data-bs-*` se o módulo dele tiver sido importado**. Não há
registry global lazy. Tooltip e Popover são exceção deliberada: mesmo importados, exigem
instanciação explícita (opt-in por performance).

**Decisão: importar o bundle.**

Razões:
1. **Superfície de uso é ampla.** O alvo usa Modal, Toast, Dropdown, Offcanvas, Alert, Collapse,
   Tab, Tooltip, Popover — 9 dos 12 componentes. A economia do tree-shaking em relação ao bundle
   é marginal (Dropdown + Tooltip + Popover já arrastam Popper inteiro, que é a maior parte do
   delta de 20 KB entre `bootstrap.min.js` e o bundle).
2. **Popper vem junto.** Não precisamos adicionar `@popperjs/core` ao `package.json` — o que
   exigiria aprovação por CLAUDE.md ("Do not change the application's dependencies without
   approval"). O bundle mantém a árvore de dependências **exatamente como está hoje**.
3. **Risco operacional zero de "esqueci de importar".** Com imports por componente, adicionar um
   `data-bs-toggle="collapse"` numa Blade nova falha **silenciosamente** se ninguém lembrar de
   importar `collapse` no `app.js`. Num projeto com 634 estilos inline e uma migração em massa de
   views por múltiplas pessoas/agentes, esse modo de falha é caro e invisível — e cai justamente
   na suíte Dusk (§5).
4. **Custo real é ~23 KB gzip**, num app server-rendered sem SPA. Irrelevante.

**Quando reconsiderar:** se, ao fim da migração, Carousel/Scrollspy/Tooltip/Popover ficarem
comprovadamente sem uso, trocar por imports seletivos é um refactor de 10 linhas em `app.js`.
Registrar isso como dívida, não como bloqueio.

### 3.2 Migração de `app.css` → `resources/scss/app.scss`

**Ordem exigida pelo Bootstrap 5.3** (verificada na doc oficial `customize/sass/`):
`functions` → **overrides de variáveis** → `variables` → `variables-dark` → **overrides de maps** →
`maps` → `mixins` → `root` → `utilities` → `reboot` → demais componentes → `utilities/api` →
**código customizado**.

Estrutura de arquivos proposta:

```
resources/scss/
├── app.scss            # entrypoint único (@vite)
├── _tokens.scss        # tokens Modernist -> variáveis SCSS do Bootstrap (importado ANTES de variables)
├── _fonts.scss         # @font-face do Archivo (importado no fim)
└── _components.scss    # camada própria: .sidebar-item, .app-shell, .stat-card etc.
```

#### `resources/scss/_tokens.scss`

```scss
// =============================================================================
//  Modernist Design System — FONTE ÚNICA DE VERDADE
//  Estes valores alimentam as variáveis SCSS do Bootstrap (ver app.scss).
//  Não existe mais um bloco `:root { --color-*: ... }` mantido à mão:
//  `bootstrap/scss/root` gera `--bs-*` a partir daqui, e o shim de
//  compatibilidade no fim de app.scss expõe os aliases `--color-*` legados.
// =============================================================================

// --- Paleta base -------------------------------------------------------------
$modernist-bg:        #f3f2f2;
$modernist-surface:   #eae9e9;
$modernist-text:      #201e1d;
$modernist-accent:    #ec3013;
$modernist-accent-2:  #e15b47;

// --- Rampa neutra (100–900) --------------------------------------------------
$modernist-neutral-100: #f8f4f4;
$modernist-neutral-200: #eae7e7;
$modernist-neutral-300: #d7d3d3;
$modernist-neutral-400: #bab6b6;
$modernist-neutral-500: #9b9797;
$modernist-neutral-600: #7d7979;
$modernist-neutral-700: #605d5d;
$modernist-neutral-800: #444141;
$modernist-neutral-900: #2d2b2b;

// --- Rampa accent (100–900) --------------------------------------------------
$modernist-accent-100: #fff2ef;
$modernist-accent-200: #ffe0d9;
$modernist-accent-300: #ffc4b8;
$modernist-accent-400: #ff9783;
$modernist-accent-500: #ff563c;
$modernist-accent-600: #dd2b0f;
$modernist-accent-700: #ae1800;
$modernist-accent-800: #7c1405;
$modernist-accent-900: #4d170e;

// --- Rampa accent-2 (100–900) ------------------------------------------------
$modernist-accent-2-100: #fff2ef;
$modernist-accent-2-200: #ffe0da;
$modernist-accent-2-300: #ffc4b9;
$modernist-accent-2-400: #ff9784;
$modernist-accent-2-500: #ef6853;
$modernist-accent-2-600: #c94b39;
$modernist-accent-2-700: #9e3526;
$modernist-accent-2-800: #71261b;
$modernist-accent-2-900: #471d16;

// --- Tipografia --------------------------------------------------------------
$modernist-font-family:    "Archivo", system-ui, -apple-system, "Segoe UI", sans-serif;
$modernist-heading-weight: 800;
```

#### `resources/scss/app.scss` (entrypoint — arquivo completo proposto)

```scss
// =============================================================================
//  Plataforma EAD — entrypoint SCSS
//  Ordem obrigatória do Bootstrap 5.3 (docs/5.3/customize/sass):
//  functions -> overrides de variáveis -> variables -> variables-dark
//  -> overrides de maps -> maps -> mixins -> root -> componentes
//  -> utilities/api -> código próprio
// =============================================================================

@use "sass:map";

// -----------------------------------------------------------------------------
// 1. Functions primeiro (habilita tint-color, shade-color, color-contrast...)
// -----------------------------------------------------------------------------
@import "bootstrap/scss/functions";

// -----------------------------------------------------------------------------
// 2. Tokens Modernist + overrides de variáveis (ANTES de `variables`)
// -----------------------------------------------------------------------------
@import "tokens";

// --- Cores de marca ---
$primary:   $modernist-accent;      // #ec3013
$secondary: $modernist-neutral-700;
$success:   $modernist-accent;      // mandato Modernist: sucesso usa o accent, não verde
$danger:    $modernist-accent-2;    // #e15b47
$warning:   $modernist-neutral-400;
$info:      $modernist-neutral-600;
$light:     $modernist-surface;
$dark:      $modernist-neutral-900;

// --- Superfícies e texto ---
$body-bg:              $modernist-bg;      // #f3f2f2
$body-color:           $modernist-text;    // #201e1d
$body-secondary-color: $modernist-neutral-600;
$body-tertiary-bg:     $modernist-surface; // #eae9e9
$border-color:         rgba($modernist-text, .4); // equivale ao --color-divider

// --- MANDATO MODERNIST: raio de borda é ZERO em todo o sistema ---------------
$enable-rounded:        false;  // desliga a emissão de border-radius no core
$border-radius:         0;
$border-radius-sm:      0;
$border-radius-lg:      0;
$border-radius-xl:      0;
$border-radius-xxl:     0;
$border-radius-pill:    0;

// --- Sombras -----------------------------------------------------------------
$enable-shadows:  false;  // não adiciona gradiente/sombra "3D" aos componentes
$box-shadow-sm:   0 1px 2px  rgba($modernist-neutral-900, .14);
$box-shadow:      0 3px 10px rgba($modernist-neutral-900, .16);
$box-shadow-lg:   0 12px 32px rgba($modernist-neutral-900, .22);

// --- Outros flags ------------------------------------------------------------
$enable-gradients:          false;
$enable-transitions:        true;
$enable-negative-margins:   true;
$enable-smooth-scroll:      true;
$enable-cssgrid:            false;

// --- Tipografia --------------------------------------------------------------
$font-family-sans-serif: $modernist-font-family;
$font-size-base:         0.875rem;   // 14px — base atual dos estilos inline
$headings-font-family:   $modernist-font-family;
$headings-font-weight:   $modernist-heading-weight; // 800
$headings-color:         $modernist-text;

// --- Espaçamento (mapeia --space-1..8 para a escala do Bootstrap) ------------
$spacer: 1rem;

// --- Foco (regra :focus-visible atual usa accent com offset 2px) -------------
$focus-ring-width:   2px;
$focus-ring-opacity: 1;
$focus-ring-color:   $modernist-accent;

// -----------------------------------------------------------------------------
// 3. Variáveis do Bootstrap
// -----------------------------------------------------------------------------
@import "bootstrap/scss/variables";
@import "bootstrap/scss/variables-dark";

// -----------------------------------------------------------------------------
// 4. Overrides de MAPS (depois de `variables`, antes de `maps`)
// -----------------------------------------------------------------------------
$theme-colors: map.merge($theme-colors, (
  "accent":     $modernist-accent,
  "accent-2":   $modernist-accent-2,
  "surface":    $modernist-surface,
  "neutral":    $modernist-neutral-700,
));

$spacers: map.merge($spacers, (
  "1x": 4px,   // --space-1
  "2x": 8px,   // --space-2
  "3x": 12px,  // --space-3
  "4x": 16px,  // --space-4
  "6x": 24px,  // --space-6
  "8x": 32px,  // --space-8
));

// -----------------------------------------------------------------------------
// 5. Núcleo obrigatório
// -----------------------------------------------------------------------------
@import "bootstrap/scss/maps";
@import "bootstrap/scss/mixins";
@import "bootstrap/scss/utilities";
@import "bootstrap/scss/root";

// -----------------------------------------------------------------------------
// 6. Componentes (lista explícita — remover o que comprovadamente não for usado)
// -----------------------------------------------------------------------------
@import "bootstrap/scss/reboot";
@import "bootstrap/scss/type";
@import "bootstrap/scss/images";
@import "bootstrap/scss/containers";
@import "bootstrap/scss/grid";
@import "bootstrap/scss/tables";
@import "bootstrap/scss/forms";
@import "bootstrap/scss/buttons";
@import "bootstrap/scss/transitions";
@import "bootstrap/scss/dropdown";
@import "bootstrap/scss/button-group";
@import "bootstrap/scss/nav";
@import "bootstrap/scss/navbar";
@import "bootstrap/scss/card";
@import "bootstrap/scss/accordion";
@import "bootstrap/scss/breadcrumb";
@import "bootstrap/scss/pagination";
@import "bootstrap/scss/badge";
@import "bootstrap/scss/alert";
@import "bootstrap/scss/progress";
@import "bootstrap/scss/list-group";
@import "bootstrap/scss/close";
@import "bootstrap/scss/toasts";
@import "bootstrap/scss/modal";
@import "bootstrap/scss/tooltip";
@import "bootstrap/scss/popover";
@import "bootstrap/scss/spinners";
@import "bootstrap/scss/offcanvas";
@import "bootstrap/scss/placeholders";
@import "bootstrap/scss/helpers";

// -----------------------------------------------------------------------------
// 7. Utilities API por último (gera as classes a partir de $utilities)
// -----------------------------------------------------------------------------
@import "bootstrap/scss/utilities/api";

// -----------------------------------------------------------------------------
// 8. Camadas próprias do projeto
// -----------------------------------------------------------------------------
@import "fonts";       // @font-face do Archivo
@import "components";  // .app-shell, .sidebar-item, .stat-card, .dropzone...

// -----------------------------------------------------------------------------
// 9. Shim de compatibilidade — aliases `--color-*` legados
//    Mantém as ~634 declarações inline existentes funcionando DURANTE a
//    migração incremental. REMOVER quando o último `var(--color-*)` sair
//    das Blades (rastrear como dívida: `grep -rn "var(--color-" resources/views | wc -l`).
// -----------------------------------------------------------------------------
:root {
  --color-bg:      var(--bs-body-bg);
  --color-surface: var(--bs-tertiary-bg);
  --color-text:    var(--bs-body-color);
  --color-accent:  var(--bs-primary);
  --color-accent-2: var(--bs-danger);
  --color-divider: var(--bs-border-color);

  --color-neutral-100: #{$modernist-neutral-100};
  --color-neutral-200: #{$modernist-neutral-200};
  --color-neutral-300: #{$modernist-neutral-300};
  --color-neutral-400: #{$modernist-neutral-400};
  --color-neutral-500: #{$modernist-neutral-500};
  --color-neutral-600: #{$modernist-neutral-600};
  --color-neutral-700: #{$modernist-neutral-700};
  --color-neutral-800: #{$modernist-neutral-800};
  --color-neutral-900: #{$modernist-neutral-900};

  --color-accent-100: #{$modernist-accent-100};
  --color-accent-200: #{$modernist-accent-200};
  --color-accent-300: #{$modernist-accent-300};
  --color-accent-400: #{$modernist-accent-400};
  --color-accent-500: #{$modernist-accent-500};
  --color-accent-600: #{$modernist-accent-600};
  --color-accent-700: #{$modernist-accent-700};
  --color-accent-800: #{$modernist-accent-800};
  --color-accent-900: #{$modernist-accent-900};

  --color-accent-2-100: #{$modernist-accent-2-100};
  --color-accent-2-200: #{$modernist-accent-2-200};
  --color-accent-2-300: #{$modernist-accent-2-300};
  --color-accent-2-400: #{$modernist-accent-2-400};
  --color-accent-2-500: #{$modernist-accent-2-500};
  --color-accent-2-600: #{$modernist-accent-2-600};
  --color-accent-2-700: #{$modernist-accent-2-700};
  --color-accent-2-800: #{$modernist-accent-2-800};
  --color-accent-2-900: #{$modernist-accent-2-900};

  --font-heading:        var(--bs-body-font-family);
  --font-heading-weight: #{$modernist-heading-weight};
  --font-body:           var(--bs-body-font-family);

  --space-1: 4px;  --space-2: 8px;  --space-3: 12px;
  --space-4: 16px; --space-6: 24px; --space-8: 32px;

  --radius-sm: 0px; --radius-md: 0px; --radius-lg: 0px;

  --shadow-sm: #{$box-shadow-sm};
  --shadow-md: #{$box-shadow};
  --shadow-lg: #{$box-shadow-lg};
}

// Tratamento de imagem em escala de cinza + isenção para logo de organização
// (regras preservadas do app.css atual).
.grayscale { filter: grayscale(1) contrast(1.08); }
.org-logo,
.grayscale.org-logo,
img.org-logo { filter: none !important; }
```

#### `resources/scss/_fonts.scss`

```scss
// ATENÇÃO (achado A3): os três arquivos em public/fonts/archivo/ têm 0 BYTES
// hoje. Estas declarações só passam a funcionar depois de baixar os .woff2
// reais do Archivo (v19, subset latin) para public/fonts/archivo/.
// Verificação: `du -b public/fonts/archivo/*` deve retornar > 0.
@font-face {
  font-family: "Archivo";
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: url("/fonts/archivo/archivo-v19-latin-400.woff2") format("woff2");
}
@font-face {
  font-family: "Archivo";
  font-style: normal;
  font-weight: 600;
  font-display: swap;
  src: url("/fonts/archivo/archivo-v19-latin-600.woff2") format("woff2");
}
@font-face {
  font-family: "Archivo";
  font-style: normal;
  font-weight: 800;
  font-display: swap;
  src: url("/fonts/archivo/archivo-v19-latin-800.woff2") format("woff2");
}
```

#### `resources/scss/_components.scss` (esqueleto)

```scss
// Camada própria — só entra aqui o que NÃO existe no Bootstrap.
// Regra: se um utilitário Bootstrap resolve, use o utilitário na Blade.

.app-shell        { display: flex; flex-direction: column; min-height: 100vh; }
.app-body         { display: flex; flex: 1; position: relative; }
.app-main         { flex: 1; min-width: 0; padding: var(--space-6); background: var(--bs-body-bg); }

.sidebar          { width: 240px; background: $modernist-neutral-900; color: $modernist-neutral-400; flex-shrink: 0; }
.sidebar-item     { display: flex; align-items: center; gap: var(--space-3);
                    padding: 11px 20px; font-size: 13px; font-weight: 600;
                    text-decoration: none; color: $modernist-neutral-400;
                    border-left: 3px solid transparent;
                    &.active { color: $modernist-neutral-100;
                               border-left-color: $primary;
                               background: rgba($modernist-accent, .18); } }
.sidebar-section-title { font-size: 10px; letter-spacing: .1em; text-transform: uppercase;
                         color: $modernist-neutral-600; padding: 20px 20px 8px; font-weight: 700; }

.stat-card        { /* usado por dashboard-conventions <x-ui.stat-card> */ }
.forum-reply      { /* alvo do template clonado por ForumPolling.appendReply() */ }
```

### 3.3 `sass-embedded` — necessário e confirmado

O Vite **nunca** embute um pré-processador Sass; ele exige o pacote instalado no projeto. Se
faltar, o build falha com *"Preprocessor dependency 'sass' not found"*. O Vite usa
`sass-embedded` se estiver instalado, senão `sass`; `sass-embedded` é o recomendado por
performance (builds ~20–35% mais rápidos em projetos comparáveis).

Sobre a API: **Vite 7.0 tornou a Sass Compiler API o padrão** e removeu o suporte à API legada —
portanto, no **Vite 8** deste projeto (`vite@8.2.0` confirmado em `node_modules`), **não é preciso
nenhum `css.preprocessorOptions.scss.api`**. Basta instalar:

```bash
vendor/bin/sail npm install -D sass-embedded
```

> Nota de aprovação: adicionar `sass-embedded` é mudança de dependência e, por CLAUDE.md, exige
> aprovação explícita. É, porém, **pré-requisito inegociável** para customizar variáveis SCSS do
> Bootstrap — sem SCSS, `$border-radius: 0` e `$primary: #ec3013` só poderiam ser feitos por
> override de `--bs-*` em cascata, que **não** cobre valores compilados (mixins, mapas de
> utilitários, `$theme-colors` derivados). Recomendação: aprovar.

### 3.4 Tailwind e o plugin de fontes bunny — **REMOVER AMBOS**

**Tailwind (`tailwindcss` + `@tailwindcss/vite`):** remover. `resources/css/app.css` nunca importa
`tailwindcss`; nenhuma classe utilitária do Tailwind é gerada nem usada. O plugin roda em todo
build, escaneia todo o projeto e produz zero output útil. Bootstrap Utilities cobre 100% do
território.

**`bunny('Instrument Sans')`:** remover. Verificado: `public/build/fonts-manifest.json` contém
6 `@font-face` de Instrument Sans e uma classe `.font-instrument-sans`, **e nada no projeto
referencia essa família** (`grep -rn "Instrument" resources/` → zero ocorrências). É download puro
de bytes mortos. O Archivo é self-hosted por `_fonts.scss`.

> **Ação corretiva paralela (A3):** substituir os três `.woff2` de 0 byte em
> `public/fonts/archivo/` pelos arquivos reais. Sem isso, `$font-family-sans-serif: "Archivo"`
> continua caindo em `system-ui` e a migração "não muda nada" visualmente na tipografia.

#### `package.json` alvo

```json
{
    "$schema": "https://www.schemastore.org/package.json",
    "private": true,
    "type": "module",
    "scripts": {
        "build": "vite build",
        "dev": "vite"
    },
    "dependencies": {
        "bootstrap": "^5.3.3"
    },
    "devDependencies": {
        "concurrently": "^9.0.1",
        "laravel-vite-plugin": "^3.1",
        "sass-embedded": "^1.80.0",
        "vite": "^8.0.0"
    }
}
```

Comandos:

```bash
vendor/bin/sail npm uninstall tailwindcss @tailwindcss/vite
vendor/bin/sail npm install -D sass-embedded
vendor/bin/sail npm run build
```

#### `vite.config.js` alvo

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // O entrypoint de CSS passa a ser o SCSS. As Blades precisam ser
            // atualizadas em lockstep:
            //   @vite(['resources/scss/app.scss', 'resources/js/app.js'])
            // em layouts/app.blade.php:10 e layouts/guest.blade.php:10.
            input: ['resources/scss/app.scss', 'resources/js/app.js'],
            refresh: true,
            // `fonts: [bunny('Instrument Sans')]` REMOVIDO: nenhuma regra CSS
            // referenciava essa família. O Archivo é self-hosted em
            // resources/scss/_fonts.scss + public/fonts/archivo/.
        }),
        // `tailwindcss()` REMOVIDO: app.css nunca importou "tailwindcss";
        // o plugin gerava zero classes utilizadas.
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    // Vite 7+ já usa a Sass Compiler API por padrão; não é necessário
    // `css.preprocessorOptions.scss.api = 'modern-compiler'` no Vite 8.
});
```

> **Não esquecer:** ao trocar o input, `@vite(['resources/css/app.css', ...])` em
> `resources/views/layouts/app.blade.php:10` **e** `resources/views/layouts/guest.blade.php:10`
> passa a lançar `ViteException: Unable to locate file in Vite manifest`. Os dois arquivos mudam
> no mesmo commit da troca de pipeline. Após remover `resources/css/app.css`, rodar
> `grep -rn "resources/css/app.css" resources/ config/` para garantir zero referências.

### 3.5 Convivência `--bs-*` × `--color-*` — **uma fonte de verdade, não duas**

**Recomendação: os tokens Modernist entram como variáveis SCSS que alimentam o Bootstrap; o
Bootstrap emite os `--bs-*`; os `--color-*` viram apenas *aliases* de compatibilidade temporária
e são deletados ao fim da migração.**

Por quê não manter dois sistemas: com dois conjuntos, toda mudança de marca exige editar dois
lugares, e componentes do Bootstrap (que só leem `--bs-*`/variáveis SCSS) divergiriam
silenciosamente dos estilos inline legados (que só leem `--color-*`). Além disso, valores como
`$border-radius: 0` **não** são expressáveis via CSS-var override: o Bootstrap compila
`border-radius` dentro de mixins e do mapa `$utilities`.

**Tabela de mapeamento:**

| Token Modernist (`--color-*`) | Variável SCSS Bootstrap | CSS var emitida | Observação |
|---|---|---|---|
| `--color-bg` `#f3f2f2` | `$body-bg` | `--bs-body-bg` | — |
| `--color-surface` `#eae9e9` | `$body-tertiary-bg`, `$light` | `--bs-tertiary-bg`, `--bs-light` | Fundo de card/modal/dropdown |
| `--color-text` `#201e1d` | `$body-color`, `$headings-color` | `--bs-body-color` | — |
| `--color-accent` `#ec3013` | `$primary`, `$success`, `$focus-ring-color` | `--bs-primary`, `--bs-success` | Mandato: sucesso usa accent |
| `--color-accent-2` `#e15b47` | `$danger` | `--bs-danger` | — |
| `--color-divider` (text @ 40%) | `$border-color` | `--bs-border-color` | `rgba($modernist-text, .4)` substitui `color-mix()` |
| `--color-neutral-700` | `$secondary` | `--bs-secondary` | — |
| `--color-neutral-900` `#2d2b2b` | `$dark` | `--bs-dark` | Fundo da sidebar |
| `--color-neutral-400` | `$warning` | `--bs-warning` | Modernist não tem amarelo |
| `--color-neutral-600` | `$info`, `$body-secondary-color` | `--bs-info`, `--bs-secondary-color` | Texto auxiliar |
| `--color-neutral-100..900` | `$modernist-neutral-*` | (alias em `:root`) | Rampa própria, sem par 1:1 no BS |
| `--color-accent-100..900` | `$modernist-accent-*` | (alias em `:root`) | O BS gera `-subtle`/`-emphasis`; a rampa fica como extra |
| `--color-accent-2-100..900` | `$modernist-accent-2-*` | (alias em `:root`) | idem |
| `--font-heading` / `--font-body` | `$font-family-sans-serif`, `$headings-font-family` | `--bs-body-font-family` | Ambos = Archivo |
| `--font-heading-weight` `800` | `$headings-font-weight` | `--bs-heading-font-weight`* | — |
| `--space-1..8` | chaves `1x..8x` em `$spacers` | classes `.p-4x`, `.m-6x`… | Escala px preservada |
| `--radius-sm/md/lg` `0px` | `$border-radius*: 0` + `$enable-rounded: false` | `--bs-border-radius: 0` | **Mandato Modernist** |
| `--shadow-sm/md/lg` | `$box-shadow-sm/$box-shadow/$box-shadow-lg` | `--bs-box-shadow*` | `$enable-shadows: false` mantém componentes flat |

**Sobre `[data-bs-theme]`:** ao importar `bootstrap/scss/variables-dark` e `root`, o projeto ganha
suporte a modo escuro "de graça" via `<html data-bs-theme="dark">`. **Não ativar agora** — o
Modernist é claro por definição e o shim `--color-*` tem valores hardcoded que não seguiriam o
tema. Registrar como possibilidade futura: quando o shim for removido, `data-bs-theme` passa a
funcionar sem trabalho adicional. Um único `data-bs-theme` por subárvore também pode ser usado
pontualmente (ex.: sidebar escura via `<aside data-bs-theme="dark">` em vez de cores hardcoded).

---

## 4. Plano de refatoração do JS, módulo a módulo

### 4.1 Problema de serialização: `app.js` é arquivo compartilhado

Hoje **toda** refatoração de módulo toca `resources/js/app.js` (import + `window.*` + `.init()`).
Com 14 módulos e trabalho paralelo, isso gera conflito de merge em todo PR.

**Estratégia proposta: `resources/js/modules/index.js` — registry auto-descritivo.**
Cada módulo passa a exportar uma entrada de registro; `app.js` vira estável e para de mudar.

#### `resources/js/app.js` alvo (não muda mais após a Tarefa 1)

```js
// -----------------------------------------------------------------------------
// Entrypoint. Estável por design: adicionar/remover um módulo NÃO toca este
// arquivo — edite `modules/index.js`. Isso elimina a serialização de PRs em
// torno de um arquivo compartilhado.
// -----------------------------------------------------------------------------

// Bootstrap COMPLETO (com Popper). Necessário para que os listeners de
// data-api (`data-bs-toggle`, `data-bs-dismiss`) sejam registrados: cada
// componente só responde a data-attributes se seu módulo tiver sido avaliado.
// Verificado em node_modules/bootstrap/js/dist/modal.js:284 e alert.js:79.
import * as bootstrap from 'bootstrap/dist/js/bootstrap.bundle.min.js';

import registry from './modules/index.js';

// `window.bootstrap` é o contrato que a suíte Dusk usa para dirigir modais e
// toasts programaticamente (`bootstrap.Modal.getOrCreateInstance(...)`).
window.bootstrap = bootstrap;

// Tooltip e Popover são opt-in por decisão de performance do Bootstrap:
// `data-bs-toggle="tooltip"` NÃO auto-inicializa. Init explícito aqui.
const initOptIns = () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]')
        .forEach((el) => bootstrap.Tooltip.getOrCreateInstance(el));
    document.querySelectorAll('[data-bs-toggle="popover"]')
        .forEach((el) => bootstrap.Popover.getOrCreateInstance(el));
};

const boot = () => {
    initOptIns();

    Object.entries(registry).forEach(([name, instance]) => {
        window[name] = instance;
        if (typeof instance.init === 'function') {
            try {
                instance.init();
            } catch (error) {
                console.error(`[app] falha ao inicializar ${name}`, error);
            }
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
```

#### `resources/js/modules/index.js` alvo

```js
// -----------------------------------------------------------------------------
// Registry único de módulos. Cada chave vira `window.<chave>` e recebe
// `.init()` após o DOMContentLoaded. Este é o ÚNICO arquivo que muda quando um
// módulo entra ou sai — mantenha-o em ordem alfabética para minimizar conflito.
// -----------------------------------------------------------------------------
import AuditLogDiffModal   from './AuditLogDiffModal';
import CsvImporter         from './CsvImporter';
import ForumPolling        from './ForumPolling';
import ForumReportModal    from './ForumReportModal';
import HttpClient          from './HttpClient';
import LessonPlayer        from './LessonPlayer';
import ModuleReorder       from './ModuleReorder';
import NotificationBell    from './NotificationBell';
import NotificationService from './NotificationService';
import QuizBuilder         from './QuizBuilder';
import QuizTimer           from './QuizTimer';
import SmartInvitationForm from './SmartInvitationForm';

// ModalManager e ForumEditHistory foram REMOVIDOS: substituídos por
// bootstrap.Modal + data-bs-toggle/data-bs-dismiss.

const httpClient   = HttpClient;          // singleton
const notifications = NotificationService; // singleton (agora sobre bootstrap.Toast)

export default {
    HttpClient:          httpClient,
    NotificationService: notifications,
    AuditLogDiffModal:   new AuditLogDiffModal(),
    CsvImporter:         new CsvImporter(httpClient),
    ForumPolling:        new ForumPolling(httpClient),
    ForumReportModal:    new ForumReportModal(httpClient, notifications),
    LessonPlayer:        new LessonPlayer(httpClient, notifications),
    ModuleReorder:       new ModuleReorder(httpClient, notifications),
    NotificationBell:    new NotificationBell(httpClient),
    QuizBuilder:         new QuizBuilder(notifications),
    QuizTimer:           new QuizTimer(),
    SmartInvitationForm: new SmartInvitationForm(httpClient, notifications),
};
```

> Efeito colateral positivo: `quiz-builder.js` e `quiz-timer.js` migram para
> `modules/QuizBuilder.js` e `modules/QuizTimer.js`, eliminando a inconsistência de layout de
> diretório. É um `git mv` + ajuste de import.

#### `NotificationService` alvo (referência — o contrato que 5 módulos consomem)

```js
/**
 * NotificationService — toasts sobre `bootstrap.Toast`.
 * A assinatura pública (`success/error/warning/info/show/dismiss`) é preservada
 * porque ModuleReorder, SmartInvitationForm, LessonPlayer, QuizBuilder e
 * ForumReportModal dependem dela.
 */
import Toast from 'bootstrap/js/dist/toast';

const VARIANTS = {
    success: 'text-bg-primary',   // Modernist: sucesso usa o accent
    error:   'text-bg-danger',
    danger:  'text-bg-danger',
    warning: 'text-bg-warning',
    info:    'text-bg-secondary',
};

export class NotificationService {
    constructor(containerId = 'notification-container') {
        this.containerId = containerId;
    }

    getOrCreateContainer() {
        let container = document.getElementById(this.containerId);
        if (!container) {
            container = document.createElement('div');
            container.id = this.containerId;
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '1090';
            document.body.appendChild(container);
        }
        return container;
    }

    show(message, type = 'info', options = {}) {
        const el = document.createElement('div');
        el.className = `toast align-items-center border-0 ${VARIANTS[type] ?? VARIANTS.info}`;
        el.setAttribute('role', 'alert');
        el.setAttribute('aria-live', 'assertive');
        el.setAttribute('aria-atomic', 'true');
        el.setAttribute('dusk', `toast-${type}`); // seletor estável para Dusk
        el.innerHTML = `
            <div class="d-flex">
                <div class="toast-body"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto"
                        data-bs-dismiss="toast" aria-label="Fechar"></button>
            </div>`;
        el.querySelector('.toast-body').textContent = message;

        this.getOrCreateContainer().appendChild(el);

        const toast = new Toast(el, {
            autohide: (options.duration ?? 5000) > 0,
            delay: options.duration ?? 5000,
        });
        el.addEventListener('hidden.bs.toast', () => el.remove());
        toast.show();

        return el;
    }

    success(m, o = {}) { return this.show(m, 'success', o); }
    error(m, o = {})   { return this.show(m, 'error', o); }
    warning(m, o = {}) { return this.show(m, 'warning', o); }
    info(m, o = {})    { return this.show(m, 'info', o); }

    dismiss(el) { if (el) Toast.getOrCreateInstance(el).hide(); }
}

export default new NotificationService();
```

#### `AuditLogDiffModal` alvo (referência do padrão `show.bs.modal` + `relatedTarget`)

```js
/**
 * AuditLogDiffModal — popula o #audit-diff-modal compartilhado.
 * Sem ModalManager: o botão carrega data-bs-toggle="modal"
 * data-bs-target="#audit-diff-modal"; o Bootstrap abre, e nós apenas
 * preenchemos no evento `show.bs.modal` usando `event.relatedTarget`
 * (padrão documentado "Varying modal content").
 */
export class AuditLogDiffModal {
    init() {
        const modal = document.getElementById('audit-diff-modal');
        if (!modal) return;

        modal.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            if (!button) return;

            const set = (selector, value) => {
                const el = modal.querySelector(selector);
                if (el) el.textContent = value;
            };

            set('[dusk="audit-diff-event"]', button.getAttribute('data-event') || '');
            set('[dusk="audit-diff-old"]', this.formatJson(button.getAttribute('data-old-values')));
            set('[dusk="audit-diff-new"]', this.formatJson(button.getAttribute('data-new-values')));
        });
    }

    formatJson(raw) {
        if (!raw) return '—';
        try { return JSON.stringify(JSON.parse(raw), null, 2); } catch { return raw; }
    }
}

export default AuditLogDiffModal;
```

### 4.2 Lista ordenada de tarefas

Legenda: **[S]** = serializada (bloqueia/é bloqueada) · **[P]** = paralelizável ·
`⚠` = toca arquivo compartilhado.

#### Fase 0 — Fundação (estritamente serial)

| # | Tarefa | Arquivos | Tipo |
|---|---|---|---|
| 0.1 | Aprovar e instalar `sass-embedded`; remover `tailwindcss` + `@tailwindcss/vite` | `package.json` | **[S]** ⚠ |
| 0.2 | Criar `resources/scss/{app.scss,_tokens.scss,_fonts.scss,_components.scss}`; deletar `resources/css/app.css` | novos | **[S]** |
| 0.3 | Reescrever `vite.config.js` (input SCSS, sem tailwind, sem bunny) | `vite.config.js` | **[S]** ⚠ |
| 0.4 | Atualizar `@vite([...])` em `layouts/app.blade.php` e `layouts/guest.blade.php` | 2 arquivos | **[S]** ⚠ |
| 0.5 | **Substituir os `.woff2` de 0 byte** em `public/fonts/archivo/` (achado A3) | assets | **[S]** |
| 0.6 | `app.js`: importar `bootstrap.bundle.min.js`, expor `window.bootstrap`, criar `modules/index.js`, mover `quiz-builder.js`/`quiz-timer.js` para `modules/QuizBuilder.js`/`QuizTimer.js` | `app.js`, `modules/index.js` | **[S]** ⚠ |
| 0.7 | `vendor/bin/sail npm run build` + suíte Dusk completa como **baseline verde** | — | **[S]** |

> Após a Fase 0, `app.js` está **congelado**. Nenhuma tarefa seguinte o edita.

#### Fase 1 — Componentes globais (serial entre si; cada um toca Blades compartilhadas)

| # | Tarefa | Toca | Tipo |
|---|---|---|---|
| 1.1 | **`<x-ui.alert>` → `.alert.alert-dismissible` + `btn-close[data-bs-dismiss="alert"]`.** Fecha o **BUG-004**. Testes: `tests/Feature/Ui/AlertComponentTest.php` + `tests/Browser/Ui/AlertDismissTest.php` (já especificados no report do bug) | `components/ui/alert.blade.php`, `components/layout/alerts.blade.php` | **[S]** ⚠ |
| 1.2 | **`<x-ui.modal>` → `.modal.fade` + `.modal-dialog/.modal-content/.modal-header/.modal-body/.modal-footer`**; remove `x-data`/`x-show`/`x-cloak`/`@click.outside`/`@keydown.escape`; `data-modal-dismiss` → `data-bs-dismiss="modal"`. Fecha o **BUG-003** | `components/ui/modal.blade.php` | **[S]** ⚠ |
| 1.3 | Trocar os 10 usos de `data-modal-target="x"` por `data-bs-toggle="modal" data-bs-target="#x"` | forum/index, forum/show, forum/_reply, forum/_edit-history-modal, certificates/index, quizzes/edit, quizzes/_question-list, help-button (2×), audit-logs/index | **[S]** (depende de 1.2) |
| 1.4 | **Deletar `ModalManager.js` e `ForumEditHistory.js`**; remover do registry | `modules/`, `modules/index.js` | **[S]** (depende de 1.3) |
| 1.5 | `NotificationService` → `bootstrap.Toast` (contrato `success/error/warning/info` preservado) | `modules/NotificationService.js` | **[S]** ⚠ (5 consumidores) |
| 1.6 | **Sidebar mobile → `bootstrap.Offcanvas`**; remove `x-data="{sidebarOpen:false}"` do `<body>`, `x-show`/`x-transition` do drawer e do backdrop; hamburger vira `data-bs-toggle="offcanvas" data-bs-target="#app-sidebar"` | `layouts/app.blade.php`, `components/layout/sidebar.blade.php`, `components/layout/topbar.blade.php` | **[S]** ⚠ |

#### Fase 2 — Módulos independentes (**totalmente paralelizáveis** após a Fase 1)

Cada tarefa toca **apenas** seu módulo + as Blades daquela feature. Zero sobreposição.

| # | Tarefa | Arquivos | Depende de |
|---|---|---|---|
| 2.1 | `NotificationBell`: dropdown → `bootstrap.Dropdown`; remove `toggleDropdown/closeDropdown` e os listeners de click-outside/Esc; usa `shown.bs.dropdown` para o refresh de contagem. Mantém polling e os 3 endpoints | `modules/NotificationBell.js`, `components/notifications-bell.blade.php` | 0.6 |
| 2.2 | `AuditLogDiffModal`: reduzir para `show.bs.modal` + `relatedTarget` (código em §4.1) | `modules/AuditLogDiffModal.js`, `audit-logs/index.blade.php`, `audit-logs/partials/_diff-modal.blade.php` | 1.3 |
| 2.3 | `ForumReportModal`: `prefill()` migra para `show.bs.modal` + `relatedTarget`; `close()` vira `Modal.getOrCreateInstance(el).hide()`; drop da dependência `modalManager` | `modules/ForumReportModal.js`, `forum/show.blade.php`, `forum/partials/_reply.blade.php` | 1.3, 1.4 |
| 2.4 | `CsvImporter`: `setProgress()` → `.progress > .progress-bar` (+ `aria-valuenow`); `showResults()` → `.alert.alert-success`/`.alert-danger`. **Preservar todos os seletores `dusk=`** | `modules/CsvImporter.js`, `users/import.blade.php` | 1.1 |
| 2.5 | `QuizTimer`: `style.color` → `.badge.text-bg-secondary` → `.text-bg-danger`; opcional `.progress` do tempo restante | `modules/QuizTimer.js`, `student/quizzes/show.blade.php` | 0.6 |
| 2.6 | `QuizBuilder`: `style.display` → `classList.toggle('d-none', ...)` nos 5 pontos (`container`, `hint`, `addButton`, `removeButton`, linhas) | `modules/QuizBuilder.js`, `quizzes/partials/_question-form.blade.php` | 0.6 |
| 2.7 | `SmartInvitationForm`: `field.style.display` → `.d-none`; preservar a lógica de `originallyRequired` | `modules/SmartInvitationForm.js`, view do convite | 0.6 |
| 2.8 | `LessonPlayer`: `reflectCompletion()` → `.d-none`; **preservar `reportProgress()` como seam Dusk** | `modules/LessonPlayer.js`, views do classroom | 0.6 |
| 2.9 | `ForumPolling`: `appendReply()` para de escrever `style.cssText`; clona `<template id="forum-reply-template">` server-rendered ou aplica `.card.mb-2` + `.card-body`. **Preservar `dusk="reply-{id}"` e `dusk="reply-content-{id}"`** | `modules/ForumPolling.js`, `forum/show.blade.php` | 0.6 |
| 2.10 | `ModuleReorder`: sem mudança funcional; validar que os toasts saem via novo `NotificationService` | `modules/ModuleReorder.js` | 1.5 |
| 2.11 | `HttpClient`: **nenhuma mudança**. Apenas confirmar que `meta[name="csrf-token"]` continua nos dois layouts | — | — |

#### Fase 3 — Ganhos opcionais (paralelos, baixa prioridade)

| # | Tarefa | Risco |
|---|---|---|
| 3.1 | `bootstrap.Collapse` na navegação do classroom e nos filtros de `audit-logs`/`users` | Baixo |
| 3.2 | `bootstrap.Tab` em perfil / `quizzes/edit` / `courses/show` | **Médio — quebra Dusk**: conteúdo em aba inativa não é visível; revisar `assertSee` |
| 3.3 | `bootstrap.Tooltip` nos `btn-icon` (init explícito já provido em `app.js`) | Baixo |
| 3.4 | Remover o shim `--color-*` de `app.scss` quando `grep -rn "var(--color-" resources/views \| wc -l` chegar a 0 | Baixo |
| 3.5 | Reavaliar bundle → imports seletivos se Carousel/Scrollspy/Popover ficarem sem uso | Baixo |

### 4.3 Resumo de paralelismo

- **Fase 0 (7 tarefas):** estritamente serial, um único PR. Toca `package.json`, `vite.config.js`,
  `app.js` e os dois layouts.
- **Fase 1 (6 tarefas):** serial entre si (1.1 → 1.2 → 1.3 → 1.4; 1.5 e 1.6 podem ir junto de 1.1).
  Toca componentes globais consumidos por todas as telas.
- **Fase 2 (11 tarefas):** **totalmente paralela** — 11 agentes/PRs simultâneos sem conflito,
  graças ao `modules/index.js` (que já foi escrito na Fase 0 com a lista final).
- **Fase 3:** paralela e opcional.

> A serialização em torno de `app.js` — que hoje seria obrigatória — é **eliminada** pelo registry.
> O `modules/index.js` já nasce na Fase 0 contendo o inventário final (sem `ModalManager` e sem
> `ForumEditHistory`), então nenhuma tarefa da Fase 2 precisa editá-lo.

---

## 5. Gotcha crítico de build: a suíte Dusk roda contra `public/build/`

**A suíte Dusk usa os assets compilados em `public/build/`, não o dev server.** Um build velho não
gera erro nenhum — ele apenas serve o JavaScript antigo, e o teste falha por um sintoma
completamente desconectado da causa (elemento que não aparece, toast que não sobe, modal que não
abre). É a categoria de falha mais cara de diagnosticar nesta migração, porque **todo** o trabalho
aqui é JS + CSS.

Evidências no repositório:
- O CI já cobre isso: `.github/workflows/ci.yml:58` → `npm ci && npm run build`.
- **Localmente não há proteção equivalente.** Não existe hook de pre-test.
- O BUG-004 lista `Assets de frontend compilados (vendor/bin/sail npm run build)` como
  **pré-condição de reprodução** — o time já tropeçou nisso.
- 8 arquivos Dusk dependem diretamente de comportamento JS afetado por esta migração:
  `BladeComponentsTest`, `CertificateRevocationTest`, `HelpCenterDuskTest`, `LayoutRenderingTest`,
  `MultiOrgStudentClassroomTest`, `MultiTenantStudentImportTest`, `NotificationBellTest`,
  `VideoThresholdCompletionTest`.

**Regra obrigatória — toda tarefa deste plano termina com:**

```bash
vendor/bin/sail npm run build
```

E a verificação de qualquer tarefa que toque JS/CSS/Blade é, nesta ordem:

```bash
vendor/bin/sail npm run build                       # 1. SEMPRE primeiro
vendor/bin/sail artisan test --compact --filter=X   # 2. PHPUnit
vendor/bin/sail artisan dusk --filter=Y             # 3. Dusk (contra o build novo)
vendor/bin/sail bin pint --dirty --format agent     # 4. Se tocou PHP
```

Riscos específicos adicionais:

1. **Manifest quebrado na Fase 0.** Ao trocar `resources/css/app.css` por `resources/scss/app.scss`,
   qualquer `@vite()` não atualizado lança `ViteException: Unable to locate file in Vite manifest`
   em **toda** requisição — inclusive nos testes Dusk, que passam a falhar em massa com um erro
   que não parece de CSS. Tarefas 0.3 e 0.4 são **um único commit atômico**.
2. **Seletores `dusk=` são contrato de teste, não decoração.** `CsvImporter` usa `dusk=` como
   seletor *funcional* (`[dusk="csv-import-progress-bar"]`); `AuditLogDiffModal` também
   (`[dusk="audit-diff-old"]`). Reescrever a Blade e "limpar" esses atributos quebra
   simultaneamente o módulo e o teste. **Preservar todos os `dusk=`** ao migrar markup.
3. **Ganchos Dusk em `window`.** `window.LessonPlayer.reportProgress(...)` é chamado diretamente
   por `VideoThresholdCompletionTest`. O registry da §4.1 preserva `window.LessonPlayer` — não
   remover.
4. **Timing de animação.** `.modal.fade`, `.toast.fade` e `.offcanvas` têm transição CSS. Testes
   que hoje fazem `assertVisible` imediatamente após um click podem flakar. Padrão a adotar:
   `waitFor('.modal.show')` / `waitUntilMissing('.modal.show')`, nunca `pause()` fixo.
5. **`.dialog-backdrop` deixa de existir.** `HelpCenterDuskTest` e o skill `help-maintenance`
   mencionam espera por `.dialog-backdrop`. Esses waits precisam migrar para `.modal.show` no
   mesmo PR da tarefa 1.2.

---

## 6. Checklist de saída da migração

- [ ] `grep -rn "x-data\|x-show\|x-cloak\|x-transition\|@click" resources/views | wc -l` → **0**
- [ ] `grep -rn "data-modal-target\|data-modal-dismiss" resources/views | wc -l` → **0**
- [ ] `resources/js/modules/ModalManager.js` e `ForumEditHistory.js` **deletados**
- [ ] `resources/css/app.css` deletado; `resources/scss/app.scss` é o único entrypoint de estilo
- [ ] `package.json` sem `tailwindcss` e `@tailwindcss/vite`; com `sass-embedded`
- [ ] `vite.config.js` sem `tailwindcss()` e sem `fonts: [bunny(...)]`
- [ ] `du -b public/fonts/archivo/*` retorna **> 0** para os três arquivos
- [ ] `grep -rn "Instrument" public/build/` → **0** (fonte morta não é mais emitida)
- [ ] BUG-003 e BUG-004 marcados como resolvidos, com os testes da §5 de cada report verdes
- [ ] Menu mobile funcional (`bootstrap.Offcanvas`) em viewport `< 992px`
- [ ] `vendor/bin/sail npm run build` executado; `vendor/bin/sail artisan dusk` **integralmente verde**
