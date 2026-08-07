# BUG-003: Modal de diff/detalhes abre automaticamente ao carregar telas que possuem o componente

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
- **Blade View / Component / JS:** 
  - `resources/views/audit-logs/index.blade.php`
  - `resources/views/audit-logs/partials/_diff-modal.blade.php`
  - `resources/views/components/ui/modal.blade.php`
  - `resources/js/modules/AuditLogDiffModal.js`
  - `resources/js/modules/ModalManager.js`

## 4. Root Cause Technical Analysis
- **Failure Branch:** Em `resources/js/modules/AuditLogDiffModal.js` ou `resources/views/components/ui/modal.blade.php`:
  - No `resources/views/components/ui/modal.blade.php`, o estado do Alpine.js `x-data="{ show: ... }"` está sendo inicializado como `true` por padrão ou a propriedade `@js($show)` no Blade parcial `_diff-modal.blade.php` não está sendo omitida/passada como `false`.
  - Adicionalmente, em `resources/js/modules/AuditLogDiffModal.js`, o manipulador de inicialização do evento `DOMContentLoaded` está executando a função de abertura (`ModalManager.open()`) incondicionalmente no ciclo de montagem do script.
- **Stack Trace / Log Evidence:**
  ```text
  N/A (Erro lógico de inicialização de UI / estado de componente Alpine.js e JS no frontend)