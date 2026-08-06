# **Especificação de Caso de Uso: UC20 — Logs de Auditoria, Monitoramento e Expurgo Programado**

---

## **1. Identificação do Caso de Uso**

* **ID:** UC20
* **Nome:** Logs de Auditoria, Monitoramento e Expurgo Programado
* **Módulo:** Auditoria e Segurança (`Audit Trail & Monitoring`)
* **Atores Principais:** Administrador Global, Gestor de Organização
* **Versão:** 2.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RF31** | Registro automatizado de auditoria para todas as mutações de banco de dados (`AuditableTrait` / `AuditObserver`). |
| **Requisito Funcional** | **RF32** | Registro de auditoria para eventos de segurança e ações críticas com mascaramento LGPD `[REDACTED]` de senhas. |
| **Requisito Funcional** | **RF33** | Interface web de consulta (`/admin/audit-logs` e `/gestor/audit-logs`) com modal de diff JSON e expurgo agendado `audit-logs:prune`. |
| **Regra de Negócio** | **RN14** | **Mascaramento e Retenção:** Senhas registradas como `[REDACTED]`. Retenção por `AUDIT_LOG_RETENTION_DAYS=365` e expurgo diário. |

---

## **3. Visão Geral e Objetivo**

Rastrear e auditar todas as mutações de banco de dados e ações críticas executadas na plataforma (autenticações, alterações cadastrais, trocas de permissões, importações CSV, correções manuais e revogações de certificado). Os logs são gravados na tabela `audit_logs` e no canal de arquivo Monolog `storage/logs/audit.log`, com visualização em painel web e suporte a expurgo automático de registros com mais de 365 dias.

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* Operador autenticado com perfil `admin` ou `gestor`.

### **4.2. Pós-condições**
* Mutações e ações críticas gravadas em `audit_logs` com dados de IP, User-Agent, URL, `old_values` e `new_values` (com senhas mascaradas como `[REDACTED]`).
* Expurgo de logs antigos via comando CLI Artisan.

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal 1: Interceptação e Gravação de Auditoria**

1. Ocorre uma ação de mutação em um model que possui a trait `AuditableTrait` (ex: atualização do status de um usuário ou criação de um curso) ou uma ação crítica explícita (ex: login falhou).
2. O `AuditObserver` intercepta o evento Eloquent ou o controller aciona o `AuditService::log()`:
   - Extrai o `org_id` do contexto.
   - Extrai o `user_id` do operador logado.
   - Captura IP do cliente (`$request->ip()`) e User-Agent.
   - Aplica a sanitização LGPD de credenciais: Qualquer atributo `password`, `password_confirmation` ou `remember_token` tem seu valor substituído pela string literal `"[REDACTED]"`.
3. O sistema grava o registro na tabela `audit_logs` e canaliza o registro para o arquivo `storage/logs/audit.log`.

---

### **5.2. Fluxo Principal 2: Consulta e Modal de Diff no Painel Web**

1. O Admin acessa `/admin/audit-logs` (ou o Gestor acessa `/gestor/audit-logs`).
2. O `AuditLogController::index()` lista os logs filtrados por `OrgScope` (se Gestor, apenas da sua Org; se Admin, todas ou a Org do Impersonate).
3. A tela exibe o formulário de filtros:
   - Data Inicial e Data Final.
   - Evento (Dropdown: `login.success`, `login.failed`, `created`, `updated`, `deleted`, `impersonate.start`, `certificate.revoked`, etc.).
   - Usuário (Busca por nome/e-mail).
4. A tabela lista os registros com: Data/Hora, Usuário, Evento, Recurso Afetado, IP e o botão **"Ver Diff (JSON)"**.
5. O operador clica no botão **"Ver Diff (JSON)"**.
6. O modal JavaScript exibe a comparação lado a lado entre os valores antigos (`old_values`) e novos valores (`new_values`) formatados em JSON com destaque sintático.

---

### **5.3. Fluxo Principal 3: Expurgo Programado CLI (`audit-logs:prune`)**

1. O agendador do Laravel (Cron) executa o comando diariamente às 00:00:
   `php artisan audit-logs:prune`
2. O comando lê a variável `config('audit.retention_days')` (default 365 dias).
3. Deleta permanentemente as linhas da tabela `audit_logs` onde `created_at < now()->subDays(365)`.
4. Exibe a mensagem no console: *"Expurgo concluído. X registros antigos de auditoria foram removidos."*

---

## **6. Assinatura Técnica de Rotas e Componentes**

* **Rotas:** `GET /admin/audit-logs`, `GET /admin/audit-logs/export`, `GET /gestor/audit-logs`, `GET /gestor/audit-logs/export`.
* **Middleware:** `auth`, `role:admin` (para rota admin); `auth`, `role:gestor` (para rota gestor).
* **Console Command:** `php artisan audit-logs:prune` (`App\Console\Commands\PruneAuditLogsCommand`).
