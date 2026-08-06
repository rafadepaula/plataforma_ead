# **Especificação de Caso de Uso: UC02 — Gestão de Perfil do Usuário**

---

## **1. Identificação do Caso de Uso**

* **ID:** UC02
* **Nome:** Gestão de Perfil do Usuário
* **Módulo:** Autenticação e Gestão de Perfil (`Profile`)
* **Atores Principais:** Aluno, Gestor de Organização, Administrador Global
* **Versão:** 2.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RF01** | Permitir atualização de dados cadastrais e senha pessoal pelo próprio usuário. |
| **Requisito Funcional** | **RF04** | Manter a integridade de e-mail único e validação de CPF. |
| **Regra de Negócio** | **RN08** | Restrição de Acesso por Contexto do Usuário. |
| **Regra de Negócio** | **RN12** | Impersonate Org Preservado (alterações de perfil afetam o registro `users` individual). |
| **Regra de Negócio** | **RN14** | Mascaramento e Auditoria de Alterações Cadastrais (`AuditableTrait`). |

---

## **3. Visão Geral e Objetivo**

Permitir que qualquer usuário autenticado (Admin, Gestor ou Aluno) visualize e atualize suas informações pessoais de cadastro (Nome, E-mail, CPF) e altere sua senha de acesso mediante a confirmação da senha atual.

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* O usuário deve estar autenticado no sistema (`middleware: auth`).

### **4.2. Pós-condições**
* Dados cadastrais atualizados no banco de dados na tabela `users`.
* Se a senha for alterada, o novo hash bcrypt é salvo e o evento `user.profile_updated` ou mutação Eloquent é auditada em `audit_logs`.

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal: Alteração de Dados Pessoais e Senha**

1. O usuário clica no seu nome/avatar no topbar da aplicação e seleciona a opção **"Meu Perfil"** (ou navega até `/profile`).
2. O controller `ProfileController::edit()` renderiza a página de perfil (`profile.edit`).
3. A interface apresenta dois blocos principais de formulário:
   - **Bloco 1: Informações do Perfil**
     - Campo **Nome Completo** (`name="name"`).
     - Campo **E-mail** (`name="email"`).
     - Campo **CPF** (`name="cpf"`).
     - Botão **"Salvar Alterações"**.
   - **Bloco 2: Atualização de Senha**
     - Campo **Senha Atual** (`name="current_password"`).
     - Campo **Nova Senha** (`name="password"`).
     - Campo **Confirmar Nova Senha** (`name="password_confirmation"`).
     - Botão **"Atualizar Senha"**.
4. Para atualizar dados pessoais, o usuário modifica os campos desejados e clica em **"Salvar Alterações"** (`PATCH /profile`).
5. O `ProfileUpdateRequest` valida:
   - `name`: string, max 150.
   - `email`: e-mail válido, único na tabela `users` (ignorando o próprio ID do usuário).
   - `cpf`: formato CPF válido, único na tabela `users` (se preenchido).
6. O backend salva as alterações e envia feedback via sessão: *"Perfil atualizado com sucesso."*
7. Para alterar a senha, o usuário preenche o Bloco 2 e clica em **"Atualizar Senha"** (`PUT /password`).
8. O sistema valida se `current_password` confere com o hash atual. Se válido, salva `Hash::make(password)` e exibe o alerta: *"Senha alterada com sucesso."*

---

## **6. Fluxos de Exceção**

### **6.1. Fluxo de Exceção 1: E-mail ou CPF Já Cadastrado por Outro Usuário**
* **Gatilho:** Inserção de e-mail ou CPF pertencente a outro usuário.
* **Comportamento:** O formulário recarrega exibindo erro de validação (HTTP 422) sob o campo correspondente: *"Este e-mail/CPF já está em uso por outra conta."*

### **6.2. Fluxo de Exceção 2: Senha Atual Incorreta**
* **Gatilho:** Preenchimento incorreto do campo `current_password`.
* **Comportamento:** A alteração de senha é rejeitada e o erro *"A senha atual informada está incorreta"* é exibido sob o campo.

---

## **7. Assinatura Técnica de Rotas e Componentes**

* **Rotas:** `GET /profile`, `PATCH /profile`, `PUT /password`.
* **Middleware:** `auth`.
* **Controller:** `ProfileController`.
