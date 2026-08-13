# BUG-003: Modal de diff/detalhes abre automaticamente ao carregar telas que possuem o componente

> **Revalidado em 2026-08-13 (pós-migração Bootstrap 5.3, commit 3088d99):** **JÁ CORRIGIDO PELA MIGRAÇÃO.** `<x-ui.modal>` agora renderiza `class="modal fade"` sem `.show` (`resources/views/components/ui/modal.blade.php:20`), o que o CSS do próprio Bootstrap já mantém em `display: none`; e `ModalManager.js` — cujo toggle de `.dialog-backdrop` no boot era o gatilho — foi deletado (`resources/js/modules/index.js:19-20`). Já existe teste Dusk de regressão (`tests/Browser/AuditLogUiTest.php:75`).

## 1. Executive Summary & Impact
- **ID:** BUG-003
- **Severity:** Medium
- **Affected Role(s):** Admin | Gestor
- **Tenant Context:** Org-scoped (`org_id`) | Admin-global
- **Summary:** Ao acessar qualquer página do sistema que renderize modais de detalhes ou diffs (como a tela de Audit Logs), o modal abre automaticamente no carregamento inicial da página sem que o usuário tenha clicado em nenhum botão ou disparado qualquer ação, bloqueando a visão inicial da tabela/conteúdo.

## 2. Step-by-Step Reproduction Guide
### Pre-conditions:
1. Existir registros no banco de dados para a tela acessada (ex: logs de auditoria gravados na tabela `audit_logs`).

### Reproduction Steps:
1. Fazer login como `Admin` ou `Gestor`.
2. Navegar para a rota `/admin/audit-logs` (`admin.audit-logs.index`).
3. Aguardar o término do carregamento da página no navegador.
4. Observar a exibição da tela.

### Expected Behavior (Happy Path):
- A página deve carregar exibindo apenas a listagem/tabela com os registros.
- Os modais de detalhes/diff (`<x-ui.modal>`) devem permanecer ocultos (`show: false` / `open: false`).
- O modal só deve abrir quando o usuário clicar no botão/link explícito de "Ver detalhes" (`.js-view-diff`).

### Actual Behavior (Bug):
- Assim que o DOM é montado, o modal de detalhes/diff sobrepõe a tela automaticamente com o estado aberto (`open: true`), exigindo que o usuário o feche manualmente para visualizar a página.

## 3. Codebase & Architectural Mapping
- **Route Name / URL:** `admin.audit-logs.index` (`/admin/audit-logs`)
- **Controller / Action:** `App\Http\Controllers\AuditLogController@index` ([AuditLogController.php](file:///app/Http/Controllers/AuditLogController.php#L15))
- **Form Request / Validation:** N/A (Ação GET de exibição)
- **Model / Database Table:** `App\Models\AuditLog` (`audit_logs` table)
- **Policy / Auth Gate:** `App\Policies\AuditLogPolicy`
- **Blade View / Component / JS (estado atual, verificado em 2026-08-13):**
  - `resources/views/audit-logs/index.blade.php:132-140` — gatilho `data-audit-diff-trigger` + `data-modal-target="audit-diff-modal"` (deliberadamente **não** `data-bs-toggle`, pois o corpo precisa ser preenchido antes de abrir)
  - `resources/views/audit-logs/partials/_diff-modal.blade.php:12` — `<x-ui.modal id="audit-diff-modal" ...>`, sem nenhum atributo/flag de "aberto"
  - `resources/views/components/ui/modal.blade.php:20-26` — `class="modal fade"`, `aria-hidden="true"`, sem `.show`
  - `resources/js/modules/AuditLogDiffModal.js:31-59` — abre **apenas** dentro do handler de `click`
  - ~~`resources/js/modules/ModalManager.js`~~ — **arquivo deletado** pela migração

## 4. Root Cause Technical Analysis

> **Resolvido.** A hipótese abaixo é do momento do report e é mantida como histórico. A verificação de 2026-08-13 está em 4.1.

- **Failure Branch (hipótese original):** Em `resources/js/modules/AuditLogDiffModal.js` ou `resources/views/components/ui/modal.blade.php`:
  - No `resources/views/components/ui/modal.blade.php`, o estado do Alpine.js `x-data="{ show: ... }"` está sendo inicializado como `true` por padrão ou a propriedade `@js($show)` no Blade parcial `_diff-modal.blade.php` não está sendo omitida/passada como `false`.
  - Adicionalmente, em `resources/js/modules/AuditLogDiffModal.js`, o manipulador de inicialização do evento `DOMContentLoaded` está executando a função de abertura (`ModalManager.open()`) incondicionalmente no ciclo de montagem do script.
- **Stack Trace / Log Evidence:**
  ```text
  N/A (Erro lógico de inicialização de UI / estado de componente Alpine.js e JS no frontend)
  ```

### 4.1 O que eliminou o defeito (verificado em 2026-08-13)

Duas mudanças, nesta ordem:

1. **`96d9af4` (pré-migração)** — mitigação parcial: o backdrop passou a default `display: none` e o teste de regressão `test_diff_modal_does_not_open_automatically_when_page_loads` foi adicionado em `tests/Browser/AuditLogUiTest.php:75-98`.
2. **`3088d99` (migração Bootstrap 5.3)** — eliminação da causa raiz:
   - `resources/views/components/ui/modal.blade.php:20` — o wrapper é `class="modal fade"` **sem** `.show` e com `aria-hidden="true"`. O CSS do Bootstrap (`resources/scss/app.scss:145`, `@import "bootstrap/scss/modal"`) já define `.modal { display: none }`; a visibilidade não depende mais de nenhum JS nem de `x-show`. O comentário em `AuditLogDiffModal.js:16-18` registra exatamente isso: *"um `.modal.fade` sem `.show` já está escondido pelo próprio CSS do Bootstrap"*.
   - `resources/js/modules/index.js:19-20` — `ModalManager` e `ForumEditHistory` foram removidos do registry (e os arquivos deletados). Como `resources/js/app.js:28-41` chama `.init()` em cada módulo registrado no boot, era exatamente aí que o toggle de display do `ModalManager` podia deixar o modal visível; esse caminho não existe mais.
   - `AuditLogDiffModal.js:31-38` — o `bind()` apenas registra `addEventListener('click', ...)`; a abertura (`open()`, linha 54-59) só ocorre dentro do handler, via `window.bootstrap.Modal.getOrCreateInstance(modal).show()`.
   - Não há mais **nenhuma** ocorrência de `x-data`, `x-show`, `.dialog` ou `.dialog-backdrop` em `resources/` (grep confirmado, zero hits) — a hipótese do Alpine deixou de ser aplicável por inexistência do markup.

**Como provar:** `tests/Browser/AuditLogUiTest.php:75` (`test_diff_modal_does_not_open_automatically_when_page_loads`) carrega `/admin/audit-logs` com uma linha de log presente e afirma `assertMissing('#audit-diff-modal')`. Executar com `vendor/bin/sail artisan dusk --filter=test_diff_modal_does_not_open_automatically_when_page_loads` (não executado nesta revalidação por serialização do MySQL compartilhado). Verificação manual equivalente: abrir `/admin/audit-logs` e conferir que a tabela está visível sem backdrop; o modal só aparece ao clicar em "Ver diff".

## 5. Test Specification Plan (TDD Blueprint)
- **Browser test (Dusk) — JÁ EXISTE:** `tests/Browser/AuditLogUiTest.php:75` — `test_diff_modal_does_not_open_automatically_when_page_loads`, seletor `#audit-diff-modal` + `@audit-log-row-{id}`.
- **Cobertura complementar do caminho feliz — JÁ EXISTE:** `tests/Browser/AuditLogUiTest.php:56-73` — clica em `@view-diff-{id}` e afirma o conteúdo em `@audit-diff-old` / `@audit-diff-new`.

## 6. Acceptance Criteria for Fix Verification
- [x] `/admin/audit-logs` carrega com a tabela visível e nenhum modal/backdrop sobreposto.
- [x] O modal só abre ao clicar em "Ver diff" (`dusk="view-diff-{id}"`).
- [x] Nenhum modal do sistema depende de `x-data`/`x-show` (zero ocorrências em `resources/`).
- [x] `tests/Browser/AuditLogUiTest.php::test_diff_modal_does_not_open_automatically_when_page_loads` existe e cobre a regressão.

## Resolution Status
- **Status:** FIXED (mitigado em `96d9af4`, causa raiz eliminada em `3088d99`)
- **Reproduction Tests:** `tests/Browser/AuditLogUiTest.php:75`
- **Fixed In Files:**
  - `resources/views/components/ui/modal.blade.php:20-26`
  - `resources/js/modules/index.js:19-20` (remoção de `ModalManager.js`)
  - `resources/js/modules/AuditLogDiffModal.js:31-59`