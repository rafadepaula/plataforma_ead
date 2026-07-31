# **00. Arquitetura Geral, Esquema Multitenant de Banco de Dados e Guardrails de Qualidade**

---

## **1. Visão Geral da Arquitetura & Diretrizes de Infraestrutura**

### **1.1. Stack Tecnológica Base & Multitenancy**
* **Backend:** **Laravel 13** executando em **PHP 8.5** (versão única, sem matrix de compatibilidade — ver SPEC-01 §2).
* **Gerenciamento de Roles & Permissões:** Pacote **`spatie/laravel-permission` (`^6.10`)** com 3 papéis fundamentais: `admin`, `gestor`, `aluno`.
* **Arquitetura Multitenant (Single Database):**
  - Cada inquilino é denominado **Organização (`org`)** e armazenado na tabela `organizations`.
  - Tabelas de domínio possuem a coluna `org_id` (chave estrangeira).
  - O isolamento automático de dados é realizado via Trait Eloquent **`OrgScope`**.
* **Banco de Dados:** **MariaDB 10.4+ / MySQL 8.0+** utilizando engine **InnoDB** com suporte a transações ACID e integridade referencial.
* **Frontend:** Blade Templating Engine (SSR), JavaScript ES6+ & jQuery 3.7+, Bootstrap 5.3.
* **Processamento de Documentos:** `barryvdh/laravel-dompdf` para renderização server-side de certificados em PDF.
* **Escopo Explícito — Sem Pagamentos:** A plataforma **não possui módulo de pagamento/billing/assinatura**. A coluna `organizations.cnpj` é mantida apenas como dado cadastral/institucional da Organização (usado na identidade visual do certificado, RF16), não alimenta nenhum fluxo financeiro.

### **1.2. Restrições e Ajustes para Hospedagem Compartilhada**
* `QUEUE_CONNECTION=sync` por padrão (ou `database` acionado via Cron minuto a minuto).
* `SESSION_DRIVER=database` ou `file`, `CACHE_STORE=database` ou `file`.
* Proteção do diretório de upload (`storage/app/public`) via `.htaccess` bloqueando execução de scripts PHP (`Options -Indexes`).
* Limites globais: `memory_limit = 128M`, `max_execution_time = 60s`.

---

## **2. Esquema Completo do Banco de Dados Multitenant (22 Tabelas)**

```
                                +--------------------+
                                |   organizations    |
                                +--------------------+
                                  | (1)           | (1)
                                  |               |
                        +---------+               +------------+
                        |                                      |
                        v (N)                                  v (N)
                +---------------+                      +-------------------+
                |     users     |                      |   invitation_links|
                +---------------+                      +-------------------+
                  | (1)           | (1)                        | (N)
                  |               |                            v
        +---------+               +------------+               |
        |                                      |               v (1)
        v (N)                                  v (N) +---------+
+---------------+                      +--------------------+
|  course_user  |                      |      courses       |
+---------------+                      +--------------------+
        ^ (N)                                    | (1)     | (1)  | (1)
        |                                        |         |      +------------------+
        +------------------+                     +---------+      +------------+     |
                           | (1)                 | (N)                         | (N) v (N)
                +--------------------+           v                             v   +-------------+
                |     certificates   |    +--------------+            +--------------+ |certificates |
                +--------------------+    |   modules    |            | forum_topics | +-------------+
                                          +--------------+            +--------------+
                                                 | (1)                       | (1)
                                                 v (N)                       v (N)
                                          +--------------+            +--------------+
                                          |   lessons    |            | forum_replies|
                                          +--------------+            +--------------+
                                                 | (1)
                                                 v (1)
                                          +--------------+
                                          |   quizzes    |
                                          +--------------+
                                                 | (1)
                                                 v (N)
                                          +------------------+
                                          |  quiz_questions  |
                                          +------------------+
                                                 | (1)
                                                 v (N)
                                          +------------------+
                                          |   quiz_options   |
                                          +------------------+
```

---

### **2.1. Definição das Tabelas Principais**

> Convenção: toda FK usa `unsignedBigInteger` + `foreign()`; `onDelete` explícito em cada uma. Tabelas marcadas com 🗑️ usam **Soft Delete** (`deleted_at`, nullable, sem índice próprio necessário no MySQL/MariaDB — o `SoftDeletes` trait do Eloquent já filtra por `WHERE deleted_at IS NULL`).

1. **`organizations`** 🗑️ (Nova Tabela Mestre Tenant):
   - `id` PK, `name` VARCHAR(150) NOT NULL, `slug` VARCHAR(160) NOT NULL UNIQUE, `cnpj` VARCHAR(18) NULLABLE UNIQUE (formato `00.000.000/0000-00`, apenas cadastral — sem uso em billing, ver §1.1), `logo_path` VARCHAR(255) NULLABLE, `status` ENUM(`active`,`inactive`) NOT NULL DEFAULT `active`, `deleted_at`, `timestamps`.
2. **`users`**:
   - `id` PK, `org_id` unsignedBigInteger NULLABLE FK -> `organizations.id` ON DELETE RESTRICT, INDEX(`org_id`), `name` VARCHAR(150) NOT NULL, `email` VARCHAR(190) NOT NULL UNIQUE, `cpf` VARCHAR(14) NULLABLE UNIQUE, `password` VARCHAR(255) NOT NULL, `status` ENUM(`active`,`inactive`) NOT NULL DEFAULT `active`, `remember_token` VARCHAR(100) NULLABLE, `timestamps`.
   - *Nota:* `admin` possui `org_id = null`. `gestor` possui `org_id` preenchido. `aluno` pode ter `org_id = null` pois se matricula via `course_user`.
3. **`courses`** 🗑️:
   - `id` PK, `org_id` unsignedBigInteger NOT NULL FK -> `organizations.id` ON DELETE RESTRICT, INDEX(`org_id`), `title` VARCHAR(200) NOT NULL, `description` TEXT NULLABLE, `workload_hours` unsignedSmallInteger NOT NULL DEFAULT 0, `is_published` BOOLEAN NOT NULL DEFAULT false, `deleted_at`, `timestamps`. INDEX composto (`org_id`, `is_published`).
   - **Guard de exclusão:** `DELETE`/`destroy()` em `Course` deve verificar `course_user.status = 'active'` antes de soft-deletar; se houver matrícula ativa, aborta com erro de validação (`422`) instruindo o Gestor a cancelar as matrículas primeiro. Não existe hard delete via UI.
4. **`course_user`** (Pivô de Matrícula Multi-Org):
   - `id` PK, `user_id` FK -> `users.id` ON DELETE CASCADE, `course_id` FK -> `courses.id` ON DELETE CASCADE, `enrolled_at` TIMESTAMP NOT NULL, `status` ENUM(`active`,`cancelled`,`completed`) NOT NULL DEFAULT `active`, `progress_percentage` unsignedTinyInteger NOT NULL DEFAULT 0 (0–100, ver SPEC-07 §1.1 — fórmula de peso igual por lição), `completed_at` TIMESTAMP NULLABLE, `timestamps`. UNIQUE(`user_id`, `course_id`). INDEX(`course_id`, `status`).
5. **`modules`** 🗑️: `id` PK, `course_id` FK -> `courses.id` ON DELETE CASCADE, `title` VARCHAR(200) NOT NULL, `description` TEXT NULLABLE, `order_index` unsignedSmallInteger NOT NULL DEFAULT 0, `deleted_at`, `timestamps`. INDEX(`course_id`, `order_index`).
6. **`lessons`** 🗑️: `id` PK, `module_id` FK -> `modules.id` ON DELETE CASCADE, `title` VARCHAR(200) NOT NULL, `type` ENUM(`content`,`quiz`) NOT NULL DEFAULT `content`, `content_text` LONGTEXT NULLABLE, `youtube_url` VARCHAR(255) NULLABLE, `pdf_path` VARCHAR(255) NULLABLE, `image_path` VARCHAR(255) NULLABLE, `order_index` unsignedSmallInteger NOT NULL DEFAULT 0, `is_published` BOOLEAN NOT NULL DEFAULT false, `deleted_at`, `timestamps`. INDEX(`module_id`, `order_index`).
   - **Nota (SPEC-07):** módulo/curso `deleted_at`/`is_published=false` a meio de matrícula ativa **oculta** a lição para o aluno (403 controlado, não erro 500), preservando `lesson_progress` já gravado.
7. **`quizzes`**: `id` PK, `lesson_id` FK -> `lessons.id` ON DELETE CASCADE, UNIQUE(`lesson_id`), `title` VARCHAR(200) NOT NULL, `instructions` TEXT NULLABLE, `allow_retries` BOOLEAN NOT NULL DEFAULT true, `max_attempts` unsignedTinyInteger NULLABLE (NULL = ilimitado enquanto `allow_retries=true`), `time_limit_minutes` unsignedSmallInteger NULLABLE (NULL = sem limite), `show_correct_answers` BOOLEAN NOT NULL DEFAULT false, `min_score_percentage` unsignedTinyInteger NOT NULL DEFAULT 70, `timestamps`.
8. **`quiz_questions`**: `id` PK, `quiz_id` FK -> `quizzes.id` ON DELETE CASCADE, `question_text` TEXT NOT NULL, `type` ENUM(`single_choice`,`multiple_choice`,`true_false`,`essay`) NOT NULL DEFAULT `single_choice` (ver SPEC-08 §1.2), `order_index` unsignedSmallInteger NOT NULL DEFAULT 0, `timestamps`. INDEX(`quiz_id`, `order_index`).
9. **`quiz_options`**: `id` PK, `question_id` FK -> `quiz_questions.id` ON DELETE CASCADE, `option_text` VARCHAR(500) NOT NULL, `is_correct` BOOLEAN NOT NULL DEFAULT false, `timestamps`. Não se aplica a questões `type=essay`.
10. **`quiz_attempts`**: `id` PK, `quiz_id` FK -> `quizzes.id` ON DELETE CASCADE, `user_id` FK -> `users.id` ON DELETE CASCADE, `score_percentage` decimal(5,2) NULLABLE (NULL enquanto houver questão `essay` pendente de correção manual), `is_passed` BOOLEAN NULLABLE, `status` ENUM(`in_progress`,`awaiting_manual_grading`,`graded`) NOT NULL DEFAULT `in_progress`, `started_at` TIMESTAMP NOT NULL, `completed_at` TIMESTAMP NULLABLE, `timestamps`. INDEX(`quiz_id`, `user_id`).
11. **`quiz_answers`**: `id` PK, `attempt_id` FK -> `quiz_attempts.id` ON DELETE CASCADE, `question_id` FK -> `quiz_questions.id` ON DELETE CASCADE, `selected_option_ids` JSON NULLABLE, `essay_answer` TEXT NULLABLE, `is_correct` BOOLEAN NULLABLE (NULL = pendente de correção manual, aplicável a `type=essay`), `graded_by` unsignedBigInteger NULLABLE FK -> `users.id` ON DELETE SET NULL, `graded_at` TIMESTAMP NULLABLE, `timestamps`.
12. **`lesson_progress`**: `id` PK, `user_id` FK -> `users.id` ON DELETE CASCADE, `lesson_id` FK -> `lessons.id` ON DELETE CASCADE, `is_completed` BOOLEAN NOT NULL DEFAULT false, `completion_source` ENUM(`manual_click`,`video_threshold`,`quiz_passed`) NULLABLE (grava como a lição foi concluída — ver SPEC-07 §1.1), `watched_seconds` unsignedInteger NULLABLE (só para lições com `youtube_url`), `completed_at` TIMESTAMP NULLABLE, `timestamps`. UNIQUE(`user_id`, `lesson_id`).
13. **`invitation_links`**: `id` PK, `org_id` FK -> `organizations.id` ON DELETE CASCADE, INDEX(`org_id`), `token` CHAR(64) NOT NULL UNIQUE, `course_id` FK -> `courses.id` ON DELETE CASCADE, `max_uses` unsignedSmallInteger NULLABLE, `current_uses` unsignedSmallInteger NOT NULL DEFAULT 0, `expires_at` TIMESTAMP NULLABLE, `revoked_at` TIMESTAMP NULLABLE, `created_by` FK -> `users.id` ON DELETE RESTRICT, `timestamps`.
14. **`certificates`**: `id` PK, `user_id` FK -> `users.id` ON DELETE RESTRICT, `course_id` FK -> `courses.id` ON DELETE RESTRICT, `validation_hash` CHAR(64) NOT NULL UNIQUE, `issued_at` TIMESTAMP NOT NULL, `revoked_at` TIMESTAMP NULLABLE, `revoked_by` unsignedBigInteger NULLABLE FK -> `users.id` ON DELETE SET NULL, `revoke_reason` VARCHAR(500) NULLABLE, `timestamps`. UNIQUE(`user_id`, `course_id`).
    - **Nota (SPEC-09):** revogação é lógica (não é soft-delete da linha) — o hash continua resolvendo em `/validar-certificado/{hash}`, exibindo status "Revogado" com `revoked_at`/`revoke_reason` publicamente.
15. **`course_completion_rules`**: `id` PK, `course_id` FK -> `courses.id` ON DELETE CASCADE, `rule_type` ENUM(`all_lessons`,`min_quiz_score`,`specific_module`) NOT NULL DEFAULT `all_lessons`, `target_id` unsignedBigInteger NULLABLE (aponta para `modules.id` quando `rule_type=specific_module` ou `quizzes.id` quando `min_quiz_score`), `required_percentage` unsignedTinyInteger NOT NULL DEFAULT 100, `timestamps`.
16. **`forum_topics`**: `id` PK, `org_id` FK -> `organizations.id` ON DELETE CASCADE, INDEX(`org_id`), `course_id` FK -> `courses.id` ON DELETE CASCADE, `user_id` FK -> `users.id` ON DELETE CASCADE, `title` VARCHAR(200) NOT NULL, `content` TEXT NOT NULL, `is_pinned` BOOLEAN NOT NULL DEFAULT false, `edited_at` TIMESTAMP NULLABLE, `timestamps`. INDEX(`course_id`, `is_pinned`).
17. **`forum_replies`**: `id` PK, `topic_id` FK -> `forum_topics.id` ON DELETE CASCADE, `user_id` FK -> `users.id` ON DELETE CASCADE, `content` TEXT NOT NULL, `edited_at` TIMESTAMP NULLABLE, `timestamps`. INDEX(`topic_id`).
18. **`forum_post_edits`** *(nova — histórico público de edição, ver SPEC-10 §2.2)*: `id` PK, `postable_type` VARCHAR(50) NOT NULL (`forum_topic` ou `forum_reply`), `postable_id` unsignedBigInteger NOT NULL, INDEX(`postable_type`, `postable_id`), `editor_user_id` FK -> `users.id` ON DELETE CASCADE, `previous_content` TEXT NOT NULL (snapshot do conteúdo *antes* da edição), `edited_at` TIMESTAMP NOT NULL, `timestamps`.
19. **`forum_reports`** *(nova — fila de denúncia/moderação, ver SPEC-10 §2.2)*: `id` PK, `postable_type` VARCHAR(50) NOT NULL, `postable_id` unsignedBigInteger NOT NULL, INDEX(`postable_type`, `postable_id`), `reported_by` FK -> `users.id` ON DELETE CASCADE, `reason` VARCHAR(500) NOT NULL, `status` ENUM(`pending`,`reviewed_dismissed`,`reviewed_removed`) NOT NULL DEFAULT `pending`, `reviewed_by` unsignedBigInteger NULLABLE FK -> `users.id` ON DELETE SET NULL, `reviewed_at` TIMESTAMP NULLABLE, `timestamps`. INDEX(`status`).
20. **`help_articles`**: `id` PK, `org_id` unsignedBigInteger NULLABLE FK -> `organizations.id` ON DELETE CASCADE, `title` VARCHAR(200) NOT NULL, `slug` VARCHAR(220) NOT NULL UNIQUE, `category` VARCHAR(100) NULLABLE, `target_page_key` VARCHAR(150) NULLABLE, INDEX(`target_page_key`), `content` LONGTEXT NOT NULL, `timestamps`.
21. **`system_settings`**: `setting_key` VARCHAR(150) NOT NULL, `org_id` unsignedBigInteger NULLABLE FK -> `organizations.id` ON DELETE CASCADE, `setting_value` TEXT NULLABLE, `timestamps`, PRIMARY KEY (`setting_key`, `org_id`).
22. **`notifications`** *(nova — SPEC-13, formato padrão de Notifications do Laravel)*: `id` CHAR(36) UUID PK, `type` VARCHAR(255) NOT NULL, `notifiable_type` VARCHAR(255) NOT NULL, `notifiable_id` unsignedBigInteger NOT NULL, INDEX(`notifiable_type`, `notifiable_id`), `data` JSON NOT NULL, `read_at` TIMESTAMP NULLABLE, `timestamps`.

---

## **3. Trait Eloquent `OrgScope` & Impersonate Org**

```php
namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait OrgScope
{
    protected static function bootOrgScope(): void
    {
        static::addGlobalScope('org', function (Builder $builder): void {
            $user = Auth::user();

            if (! $user) {
                return;
            }

            if ($user->hasRole('admin')) {
                $activeOrgId = session('active_org_id');
                if ($activeOrgId) {
                    $builder->where($builder->getModel()->getTable() . '.org_id', $activeOrgId);
                }
                return;
            }

            if ($user->org_id) {
                $builder->where($builder->getModel()->getTable() . '.org_id', $user->org_id);
            } else {
                $builder->whereRaw('1 = 0');
            }
        });
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (! auth()->check()) {
                return;
            }

            $user = auth()->user();

            if ($model->org_id) {
                return;
            }

            $resolvedOrgId = $user->org_id ?? session('active_org_id');

            if (! $resolvedOrgId) {
                throw new \App\Exceptions\UnresolvedOrgContextException(
                    "Não foi possível resolver org_id para criar ".static::class." (usuário #{$user->id} sem org_id e sem active_org_id em sessão)."
                );
            }

            $model->org_id = $resolvedOrgId;
        });
    }
}
```

> **Correção de guardrail (auditoria SPEC-00):** a versão anterior gravava `org_id = null` silenciosamente quando nem `$user->org_id` nem `session('active_org_id')` estavam resolvidos (ex.: Admin sem Impersonate Org ativo criando um registro de tabela escopada). `UnresolvedOrgContextException` (extends `RuntimeException`) deve ser capturada globalmente pelo `Handler` e traduzida em HTTP 422 com mensagem "Selecione uma Organização ativa antes de continuar." Teste obrigatório: `OrgScopeUnresolvedContextTest.php`.

---

## **4. Roles Spatie & Enum (`RolesEnum`)**

```php
namespace App\Enums\Permissions;

enum RolesEnum: string
{
    case ADMIN = 'admin';
    case GESTOR = 'gestor';
    case ALUNO = 'aluno';

    public static function label(string $role): string
    {
        return match ($role) {
            self::ADMIN->value => 'Administrador do Sistema',
            self::GESTOR->value => 'Gestor de Organização',
            self::ALUNO->value => 'Aluno Capacitando',
            default => $role,
        };
    }
}
```

---

## **5. Guardrails de Qualidade (95%+ Cobertura & Testes E2E Laravel Dusk)**

- **Suíte de Testes Backend:** PHPUnit / Pest cobrindo unitários e integração com `OrgScope`, isolamento entre tenants, Impersonate Org e Roles Spatie (`role:admin`, `role:gestor`, `role:aluno`).
- **Testes E2E com Laravel Dusk (Obrigatório):**
  - **Todas as especificações e funcionalidades** devem possuir suíte completa de testes End-to-End (E2E) utilizando **Laravel Dusk**.
  - Os testes Dusk devem simular a jornada completa do usuário no navegador (fluxos de Login, Impersonate, Navegação, Formulários, Modais, AJAX jQuery, Download de PDF e Provas).
  - Cobertura obrigatória de **100% das telas e fluxos** da funcionalidade via Dusk.
- **Critério Rígido de Conclusão:** Uma tarefa/especificação **SÓ SERÁ CONSIDERADA CONCLUÍDA** quando 100% dos testes Unitários, de Integração e E2E (Dusk) passarem limpos com sucesso.
- Execução do script `scripts/check-coverage.php` validando a meta de $\ge 95,00\%$ de cobertura de código.

---

## **6. Agentic Harness & Auto-Update de Skills (SPEC-11)**

- **Obrigatório (Mínimo 3 Skills):** Criar e manter as 3 skills da feature (`tenancy-architecture`, `tenancy-conventions`, `tenancy-maintenance`) em `.agents/skills/`.
- **Protocolo de Auto-Update:** Toda alteração de código ou schema que impacte este módulo deve automaticamente acionar a reescrita/atualização das skills correspondentes antes da finalização da tarefa.


