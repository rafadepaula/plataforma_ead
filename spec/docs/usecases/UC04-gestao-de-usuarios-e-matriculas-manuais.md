# **Especificação de Caso de Uso: UC04 — Gestão de Usuários e Matrículas Manuais**

---

## **1. Identificação do Caso de Uso**

* **ID:** UC04
* **Nome:** Gestão de Usuários e Matrículas Manuais
* **Módulo:** Gestão de Usuários e Matrículas (`Users & Enrollments`)
* **Atores Principais:** Administrador Global, Gestor de Organização
* **Versão:** 2.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RF04** | Permitir ao Admin/Gestor cadastrar, listar, buscar, editar e inativar/ativar alunos e gestores da Organização. |
| **Requisito Funcional** | **RF21** | Permitir ao Admin/Gestor vincular ou remover manualmente alunos em cursos da Organização. |
| **Regra de Negócio** | **RN08** | Restrição Estrita de Acesso e Matrícula. |
| **Regra de Negócio** | **RN12** | Impersonate Org e Contexto de Organização (`OrgScope` em `User` via `org_id`). |
| **Regra de Negócio** | **RN14** | Mascaramento e Auditoria (`user.status_changed`, mutações Eloquent). |

---

## **3. Visão Geral e Objetivo**

Permitir que Administradores Globais e Gestores de Organização gerenciem as contas de usuários (Alunos e Gestores) vinculadas à sua Organização (criação manual, edição de dados cadastrais, alteração de status ativo/inativo) e realizem a gestão manual de matrículas (vincular um aluno a um curso ou remover a matrícula existente).

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* O usuário operador deve estar autenticado com Role `admin` ou `gestor`.
* Se `admin`, uma Organização deve estar ativa via Impersonate Org ou a busca/criação deve especificar o `org_id`.

### **4.2. Pós-condições**
* Usuário cadastrado/atualizado na tabela `users` com Role atribuída via Spatie Permissions (`model_has_roles`).
* Matrícula registrada ou removida na tabela pivô `course_user`.
* Eventos de alteração de status auditados na tabela `audit_logs`.

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal 1: Cadastro e Gestão de Usuários**

1. O operador clica no menu lateral **"Usuários"** (`GET /users`).
2. O `UserController::index()` exibe a listagem de usuários escopados pela Organização ativa via `OrgScope` contendo: Nome, E-mail, CPF, Perfil/Role (Badge `Aluno` ou `Gestor`), Status (Badge `Ativo` ou `Inativo`) e Ações.
3. Para cadastrar um novo usuário, o operador clica em **"+ Novo Usuário"** (`GET /users/create`).
4. A tela `users.create` apresenta:
   - **Nome Completo** (`name="name"`, obrigatorio).
   - **E-mail** (`name="email"`, obrigatorio, unico).
   - **CPF** (`name="cpf"`, opcional, unico).
   - **Perfil / Role** (`name="role"`, select: `aluno` ou `gestor`).
   - **Senha** (`name="password"`, obrigatorio, min 8).
5. O operador preenche os campos e clica em **"Salvar Usuário"** (`POST /users`).
6. O `StoreUserRequest` valida os dados. O controller atribui a Role via `$user->assignRole($request->role)`, vincula o `org_id` resolvido e salva a conta.

---

### **5.2. Fluxo Principal 2: Gestão Manual de Matrículas em Cursos**

1. O operador navega até a listagem de Cursos (`GET /courses`) e clica na ação **"Matrículas"** do curso desejado (ou acessa `GET /courses/{course}/enrollments`).
2. O `EnrollmentController::index()` exibe:
   - Tabela de **Alunos Matriculados**: Lista os alunos com matrícula ativa em `course_user` para aquele `course_id`, data da matrícula e botão **"Remover Matrícula"**.
   - Form de **Nova Matrícula Manual**: Select contendo todos os alunos ativos da Organização que ainda *não* possuem matrícula naquele curso, e botão **"Matricular Aluno"**.
3. Para matricular um aluno, o operador seleciona o aluno no dropdown e clica em **"Matricular Aluno"** (`POST /courses/{course}/enrollments`).
4. O backend executa o upsert em `course_user` (`user_id`, `course_id`, `status => 'active'`, `enrolled_at => now()`) e dispara a notificação de confirmação de matrícula (`SPEC-13`).
5. A tabela é atualizada exibindo o novo aluno com feedback: *"Aluno matriculado com sucesso."*
6. Para remover uma matrícula, o operador clica em **"Remover Matrícula"** no aluno desejado (`DELETE /courses/{course}/enrollments/{user}`).
7. O backend altera o status da matrícula em `course_user` para `cancelled` (ou exclui a linha pivô) e atualiza a interface.

---

## **6. Fluxos de Exceção**

### **6.1. Fluxo de Exceção 1: E-mail ou CPF Duplicado**
* **Gatilho:** Submissão de e-mail ou CPF já existente no sistema.
* **Comportamento:** O controller rejeita a requisição e retorna erro de validação HTTP 422 destacando o campo duplicado.

### **6.2. Fluxo de Exceção 2: Tentativa de Acesso Cross-Tenant**
* **Gatilho:** Gestor da Org A tenta acessar `GET /users/{id}/edit` de um usuário pertencente à Org B.
* **Comportamento:** A Trait `OrgScope` ou a Policy `UserPolicy` bloqueia o acesso e retorna HTTP 404 (Not Found) ou HTTP 403 (Forbidden).

---

## **7. Assinatura Técnica de Rotas e Componentes**

* **Rotas:** `GET /users`, `POST /users`, `GET /users/{user}/edit`, `PUT /users/{user}`, `GET /courses/{course}/enrollments`, `POST /courses/{course}/enrollments`, `DELETE /courses/{course}/enrollments/{user}`.
* **Middleware:** `auth`, `role:admin|gestor`.
* **Controllers:** `UserController`, `EnrollmentController`.
