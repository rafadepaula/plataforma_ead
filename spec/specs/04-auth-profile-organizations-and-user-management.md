# **04. Autenticação, Perfil do Usuário, Gestão de Organizações e Alunos com Importação em Lote**

---

## **1. Visão Geral & Mapeamento de Requisitos Multitenant**

* **RF01:** Autenticação via e-mail e senha (`bcrypt`) com verificação de papéis Spatie (`role:admin`, `role:gestor`, `role:aluno`).
* **RF02:** Recuperação de Senha com token de uso único via SMTP.
* **RF04:** CRUD de Alunos e Gestores pelo Admin / Gestor da Organização.
* **RF05:** Importação em lote de Alunos via CSV (streaming em chunks AJAX de 50 registros) vinculando-os à `org_id` atual.
* **RF23:** Gestão de Organizações (`organizations`) pelo Admin Global e atribuição de Gestores (`org_id`).
* **Roles Cobertas:** `role:admin`, `role:gestor`, `role:aluno`.
* **Casos de Uso:** UC01, UC02, UC03, UC18 (Gestão de Organizações).

---

## **2. Gestão de Organizações (`organizations`) & Impersonate Org**

- **CRUD de Organizações (`OrganizationController`):** Reservado ao `role:admin`. Cadastra Nome, Slug, CNPJ, Logo e Status.
- **Impersonate Org pelo Admin (`ImpersonateOrgController`):**
  - Admin inicia sessão de contexto em uma Org especificando `active_org_id`.
  - `OrgScope` lê `session('active_org_id')` e filtra a visão do Admin para aquela Organização especificamente.

---

## **3. Importação CSV em Chunks Multitenant**

- **`UserImportService`**: Processa CSV em chunks de 50 linhas ($O(1)$ RAM).
- Associa alunos ao `org_id` do gestor logado.
- Se o e-mail do aluno já existir globalmente no sistema (aluno estudando em outra Org), efetua a matrícula no novo curso da Org atual sem duplicar o usuário e sem sobrescrever sua senha (RN09).

---

## **4. Checklist de Implementação & Testes (Target: 95%+ Coverage & Dusk E2E)**

- [ ] Migration `organizations` e relacionamento `org_id` em `User`
- [ ] CRUD de Organizações para o Admin Global (`role:admin`)
- [ ] Controller `ImpersonateOrgController` (mudar contexto de Org do Admin)
- [ ] Service `UserImportService` isolado por `org_id`
- [ ] Harness: Criar/atualizar as 3 skills (`auth-orgs-architecture`, `auth-orgs-conventions`, `auth-orgs-maintenance`)
- [ ] Testes Automatizados Backend & Dusk E2E: `OrganizationCrudTest.php`, `ImpersonateOrgTest.php`, `MultiTenantStudentImportTest.php` aprovados com 100%.
