# **15. Sistema de Logs de Auditoria e Monitoramento Multitenant**

---

## **1. Visão Geral & Mapeamento de Requisitos Multitenant**

* **RF31:** Registro automatizado de auditoria para todas as mutações de banco de dados (Criação, Alteração, Exclusão) em modelos auditáveis.
* **RF32:** Registro detalhado de auditoria para eventos de segurança e ações críticas do sistema.
* **RF33:** Interface web de consulta e filtragem de logs de auditoria no painel gerencial (`/admin/audit-logs` para Admin e `/gestor/audit-logs` para Gestor).
* **RN14:**
  - **Sensibilidade de Credenciais:** Todo evento de autenticação (Login, Logout, Falha de Login, Troca de Senha) DEVE mascarar rigorosamente o campo de senha como `[REDACTED]`. Senhas em texto plano jamais podem ser registradas em banco ou arquivos de log (conformidade LGPD e diretrizes OWASP).
  - **Isolamento Multitenant:** A tabela `audit_logs` utiliza a trait `OrgScope`. Gestores enxergam apenas registros de auditoria pertencentes à sua própria Organização (`org_id`). O Admin global pode filtrar registros por qualquer Organização ou visualizar o escopo global.
  - **Retenção & Expurgo:** O sistema mantém os logs de auditoria durante o período configurado na variável de ambiente `AUDIT_LOG_RETENTION_DAYS=365` (padrão 365 dias). O expurgo é realizado pelo comando Artisan `audit-logs:prune`.
* **Roles Cobertas:** `role:admin`, `role:gestor`, `role:aluno` (ações auditadas em segundo plano).

---

## **2. Modelo do Banco de Dados & Segurança**

### **2.1. Tabela `audit_logs`**

- **`id`**: bigint, PK, auto-increment.
- **`org_id`**: bigint, nullable, FK -> `organizations.id` (null apenas para ações do Admin sem escopo de Org ou tentativas de login falhas antes de identificar a Org).
- **`user_id`**: bigint, nullable, FK -> `users.id` (null para convidados ou tentativas falhas de login).
- **`event`**: string(50), indexado (ex.: `created`, `updated`, `deleted`, `login.success`, `login.failed`, `logout`, `impersonate.start`, `impersonate.stop`, `csv.import`, `essay.graded`, `certificate.issued`, `certificate.revoked`).
- **`auditable_type`**: string, nullable, indexado (ex.: `App\Models\Course`).
- **`auditable_id`**: bigint, nullable, indexado.
- **`old_values`**: json, nullable (valores anteriores antes da mutação).
- **`new_values`**: json, nullable (novos valores aplicados na mutação).
- **`ip_address`**: string(45), nullable (suporte a IPv4/IPv6).
- **`user_agent`**: text, nullable.
- **`url`**: text, nullable (rota/URL onde o evento ocorreu).
- **`created_at`**: timestamp, indexado.
- **`updated_at`**: timestamp.

### **2.2. Duplo Armazenamento (Banco + Monolog)**

Além do registro na tabela MySQL `audit_logs`, todos os eventos de auditoria são canalizados via Monolog para o arquivo diário/dedicado `storage/logs/audit.log`, garantindo persistência mesmo em caso de falha de banco.

---

## **3. Ações Críticas Auditadas & Mapeamento de Eventos**

| Categoria | Evento | Gatilho / Localização | Payload Registrado |
| :--- | :--- | :--- | :--- |
| **Autenticação** | `login.success` | Sucesso no `LoginController` | `user_id`, `email`, `ip`, `user_agent`, `password: "[REDACTED]"` |
| **Autenticação** | `login.failed` | Falha no `LoginController` | `email`, `ip`, `user_agent`, `status: "invalid_credentials"`, `password: "[REDACTED]"` |
| **Autenticação** | `logout` | Execução do `LogoutController` | `user_id`, `email`, `ip`, `user_agent` |
| **Autenticação** | `password.reset` | Solicitação/Redefinição de Senha | `user_id`, `email`, `ip` |
| **Gestão de Acesso**| `impersonate.start` | Admin assume contexto de Org (`ImpersonateController`) | `admin_id`, `target_org_id`, `target_org_name` |
| **Gestão de Acesso**| `impersonate.stop` | Admin encerra impersonate | `admin_id`, `original_org_id` |
| **Gestão de Usuários**| `user.status_changed` | Ativação/Desativação de Aluno/Gestor (`UserController`) | `user_id`, `old_status`, `new_status`, `reason` |
| **Importação Lote** | `csv.import` | Importação em Lote de Alunos (`UserImportService`) | `total_processed`, `success_count`, `error_count`, `file_name` |
| **Avaliações** | `essay.graded` | Correção manual de questão dissertativa (`QuizAttemptController`) | `quiz_attempt_id`, `question_id`, `old_grade`, `new_grade`, `evaluator_id` |
| **Certificados** | `certificate.issued` | Emissão de Certificado (`IssueCertificateAction`) | `certificate_id`, `user_id`, `course_id`, `validation_hash` |
| **Certificados** | `certificate.revoked` | Revogação de Certificado (`CertificateController`) | `certificate_id`, `validation_hash`, `revocation_reason` |
| **Gestão Conteúdo** | `content.deleted` | Exclusão de Curso, Módulo ou Lição | `model_type`, `model_id`, `title`, `deleted_by` |
| **Mutação Geral** | `created`, `updated`, `deleted` | Models utilizando `AuditableTrait` / `AuditObserver` | `auditable_type`, `auditable_id`, `old_values`, `new_values` |

---

## **4. Arquitetura de Implementação & Servidores de Auditoria**

### **4.1. `AuditableTrait` e `AuditObserver`**
- Models principais (`Organization`, `User`, `Course`, `Module`, `Lesson`, `Quiz`, `Certificate`, etc.) incluem a Trait `AuditableTrait`.
- A trait registra o `AuditObserver` para interceptar automaticamente os eventos Eloquent `created`, `updated` e `deleted`.
- Atributos sensíveis (`password`, `remember_token`) são automaticamente filtrados e omitidos das colunas `old_values` e `new_values`.

### **4.2. `AuditService`**
- Classe de serviço responsável por registrar eventos customizados de forma unificada:
  ```php
  AuditService::log(
      event: 'impersonate.start',
      orgId: $orgId,
      payload: ['target_org_id' => $orgId]
  );
  ```

### **4.3. Expurgo Programado (`audit-logs:prune`)**
- Comando Artisan `php artisan audit-logs:prune` agendado no `routes/console.php`:
  ```php
  Schedule::command('audit-logs:prune')->daily();
  ```
- Remove registros da tabela `audit_logs` com `created_at < now()->subDays(config('audit.retention_days', 365))`.

---

## **5. Interface Web de Auditoria (`/admin/audit-logs` e `/gestor/audit-logs`)**

- **Filtros Disponíveis:**
  - Intervalo de Datas (`Data Inicial` a `Data Final`).
  - Organização (Apenas visível para Admin).
  - Evento (Dropdown com categorias: Autenticação, Mutações de Banco, Ações Críticas).
  - Usuário (Busca por nome/e-mail).
- **Tabela de Resultados:**
  - Data/Hora, Usuário, Evento, Recurso Afetado (`auditable_type` #`auditable_id`), IP, Ações (Modal para visualizar diff JSON entre `old_values` e `new_values`).
- **Páginação:** 25 registros por página com suporte a exportação da consulta atual em CSV.

---

## **6. Checklist de Implementação & Testes (Target: 95%+ Coverage & Dusk E2E)**

- [ ] Migration `audit_logs` com FKs, índices e suporte a JSON.
- [ ] Model `AuditLog` utilizando a Trait `OrgScope`.
- [ ] Trait `AuditableTrait` e `AuditObserver` com filtro de redação de atributos sensíveis.
- [ ] `AuditService` centralizado + Listeners para eventos de autenticação (`Login`, `Failed`, `Logout`).
- [ ] Injeção de registros de auditoria nas Ações Críticas (Impersonate, Importação CSV, Correção de Provas, Revogação de Certificados).
- [ ] Controllers `/admin/audit-logs` e `/gestor/audit-logs` com filtros e suporte a modal diff JSON.
- [ ] Comando Artisan `audit-logs:prune` e agendamento no Console Kernel.
- [ ] Harness: Criar/atualizar as 3 skills (`audit-logs-architecture`, `audit-logs-conventions`, `audit-logs-maintenance`).
- [ ] Testes Automatizados Backend & Dusk E2E: `AuditLogTest.php`, `AuditLogUiTest.php` aprovados com 100%.
