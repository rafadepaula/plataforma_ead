# **Especificação de Caso de Uso: UC22 — Povoamento Automatizado do Banco de Dados (Seeders) por Ambiente**

---

## **1. Identificação do Caso de Uso**

* **ID:** UC22
* **Nome:** Povoamento Automatizado do Banco de Dados (Seeders) por Ambiente
* **Módulo:** Banco de Dados e Seeding (`Database Seeders`)
* **Atores Principais:** Desenvolvedor, Administrador do Sistema
* **Versão:** 2.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RF29** | Povoar o banco de dados via `DatabaseSeeder` idempotente, isolando dados fictícios para ambiente de desenvolvimento/testes sem afetar produção. |
| **Regra de Negócio** | **RN16** | **Povoamento Idempotente e Isolamento:** O `DatabaseSeeder` utiliza `firstOrCreate`/`updateOrCreate` e bloqueia a criação de dados de testes em ambiente `production`. |

---

## **3. Visão Geral e Objetivo**

Garantir o provisionamento automatizado do banco de dados da aplicação com dados essenciais de infraestrutura (Roles, Permissões, Usuário Admin Inicial e Configurações de Sistema) de forma idempotente e segura por ambiente, fornecendo dados fictícios (*dummy data*) apenas quando executado em ambientes locais/testes e bloqueando a poluição do banco em produção.

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* Migrações de banco de dados executadas (`php artisan migrate`).

### **4.2. Pós-condições**
* Tabela de Roles e Permissões populada via Spatie (`RolesAndPermissionsSeeder`).
* Usuário Admin Global inicial criado (`AdminSeeder`).
* Se ambiente local/dev: Organizações, Cursos, Aulas, Questionários e Alunos fictícios populados.

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal: Execução do Seeding Automatizado**

1. O operador executa o comando no terminal:
   `vendor/bin/sail artisan db:seed` (ou `php artisan db:seed`).
2. O `DatabaseSeeder::run()` verifica o ambiente atual (`app()->environment()`):
   - **Módulos Essenciais de Produção (Executados em Todos os Ambientes):**
     - Invoca `RolesAndPermissionsSeeder`: Cria as roles `admin`, `gestor`, `aluno` via `Role::firstOrCreate()`.
     - Invoca `SystemSettingSeeder`: Popula as chaves padrão globais em `system_settings`.
     - Invoca `AdminSeeder`: Cria o usuário Admin Global padrão via `User::firstOrCreate()` com e-mail `admin@ead.com`.
   - **Módulos de Desenvolvimento/Testes (Executados APENAS se `app()->environment('local', 'testing', 'development')`):**
     - Invoca `OrganizationSeeder`: Cria 2 Organizações fictícias de teste.
     - Invoca `CourseSeeder`: Cria Cursos, Módulos e Lições multimídia de teste.
     - Invoca `UserSeeder`: Cria alunos e gestores de teste vinculados às Organizações.
     - Invoca `HelpArticleSeeder`: Popula artigos de ajuda contextuais para os testes.
3. Todas as criações utilizam a supressão de eventos `User::withoutEvents(...)` para impedir o disparo desnecessário de notificações por e-mail ou logs de auditoria durante o seeding.
4. O console exibe a mensagem de sucesso: *"Database seeding completed successfully."*

---

## **6. Assinatura Técnica de Comandos**

* **Comando CLI:** `php artisan db:seed` (`database/seeders/DatabaseSeeder.php`).
