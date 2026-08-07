# **Especificação de Caso de Uso: UC02 — Gestão de Perfil do Usuário**

---

## **1. Identificação do Caso de Uso**

* **ID:** UC02
* **Nome:** Gestão de Perfil do Usuário
* **Módulo:** Autenticação e Gestão de Perfil (`Profile`)
* **Spec Técnica:** [`SPEC-18`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/18-user-profile-management.md)
* **Atores Principais:** Aluno, Gestor de Organização, Administrador Global
* **Versão:** 3.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RF01** | Permitir atualização de dados cadastrais pelo próprio usuário. |
| **Requisito Funcional** | **RF34** | Permitir troca de senha pelo próprio usuário mediante confirmação da senha atual, invalidando as demais sessões. |
| **Requisito Funcional** | **RF04** | Manter a integridade de e-mail e CPF únicos. |
| **Regra de Negócio** | **RN08** | Restrição de acesso por contexto — o alvo é sempre `Auth::user()`, nunca um `{user}` de rota. |
| **Regra de Negócio** | **RN12** | Impersonate Org preservado — a edição age sobre a linha `users` individual; `org_id` é imutável por este endpoint. |
| **Regra de Negócio** | **RN14** | Auditoria de alterações cadastrais via `AuditableTrait`, com senha redigida como `[REDACTED]`. |
| **Regra de Negócio** | **RN17** | Validação de CPF por dígito verificador, uniforme em todo o sistema. |

---

## **3. Visão Geral e Objetivo**

Permitir que qualquer usuário autenticado (Admin, Gestor ou Aluno) visualize e atualize suas informações pessoais de cadastro (Nome, E-mail, CPF) e altere sua senha de acesso mediante a confirmação da senha atual.

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* O usuário deve estar autenticado no sistema (`middleware: auth`).

### **4.2. Pós-condições**
* Dados cadastrais atualizados na tabela `users`; `org_id` e `status` permanecem inalterados.
* Se a senha for alterada, o novo hash bcrypt é salvo, as demais sessões do usuário são invalidadas (`Auth::logoutOtherDevices()`) e a mutação é auditada em `audit_logs` com a senha redigida.

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal: Alteração de Dados Pessoais e Senha**

1. O usuário clica no seu nome/avatar no topbar e seleciona **"Meu Perfil"** (ou navega até `/profile`).
2. `ProfileController::edit()` renderiza a página `profile.edit`.
3. A interface apresenta dois blocos independentes, cada um com seu próprio formulário:
   - **Bloco 1: Informações do Perfil** (`dusk="profile-form"`)
     - Campo **Nome Completo** (`name="name"`).
     - Campo **E-mail** (`name="email"`).
     - Campo **CPF** (`name="cpf"`).
     - Botão **"Salvar Alterações"** (`dusk="profile-submit"`).
   - **Bloco 2: Atualização de Senha** (`dusk="password-form"`)
     - Campo **Senha Atual** (`name="current_password"`).
     - Campo **Nova Senha** (`name="password"`).
     - Campo **Confirmar Nova Senha** (`name="password_confirmation"`).
     - Botão **"Atualizar Senha"** (`dusk="password-submit"`).
4. Para atualizar dados pessoais, o usuário modifica os campos e clica em **"Salvar Alterações"** (`PATCH /profile`).
5. O `ProfileUpdateRequest` valida:
   - `name`: `required|string|max:255`.
   - `email`: e-mail válido, único em `users` ignorando o próprio ID.
   - `cpf`: `nullable`, `App\Rules\Cpf` (dígito verificador), único em `users` ignorando o próprio ID.
6. O backend salva e redireciona para `profile.edit` com flash: *"Perfil atualizado com sucesso."*
7. Para alterar a senha, o usuário preenche o Bloco 2 e clica em **"Atualizar Senha"** (`PUT /profile/password`).
8. O `PasswordUpdateRequest` valida `current_password` pela regra nativa `current_password` e `password` por `Password::defaults()` + `confirmed`.
9. Se válido, o sistema salva `Hash::make(password)`, chama `Auth::logoutOtherDevices()` e exibe: *"Senha alterada com sucesso."*

---

## **6. Fluxos de Exceção**

### **6.1. E-mail ou CPF Já Cadastrado por Outro Usuário**
* **Gatilho:** Inserção de e-mail ou CPF pertencente a outro usuário.
* **Comportamento:** Redirect de volta com o erro de validação renderizado inline sob o campo correspondente por `components/ui/input.blade.php`.
* **Nota:** por ser formulário Blade (não JSON), o Laravel responde com redirect + erros na sessão, não com um HTTP 422 literal. O 422 só ocorreria em requisição JSON.

### **6.2. CPF com Dígito Verificador Inválido**
* **Gatilho:** CPF sintaticamente bem formado mas com checksum inválido, ou sequência de dígitos idênticos.
* **Comportamento:** Erro sob o campo `cpf`: *"O CPF informado é inválido."*

### **6.3. Senha Atual Incorreta**
* **Gatilho:** Preenchimento incorreto de `current_password`.
* **Comportamento:** A alteração é rejeitada, a senha permanece inalterada e o erro é exibido sob o campo. A rota tem `throttle:6,1` para que o campo não vire oráculo de força bruta.

### **6.4. Usuário Não Autenticado**
* **Gatilho:** Convidado acessa `/profile`.
* **Comportamento:** Redirect para `/login` pelo middleware `auth`.

---

## **7. Assinatura Técnica de Rotas e Componentes**

| Método | URI | Nome | Ação |
| :--- | :--- | :--- | :--- |
| `GET` | `/profile` | `profile.edit` | `ProfileController@edit` |
| `PATCH` | `/profile` | `profile.update` | `ProfileController@update` |
| `PUT` | `/profile/password` | `password.update` | `PasswordController@update` |

* **Middleware:** `auth` em todas; `throttle:6,1` adicional em `password.update`.
* **Controllers:** `ProfileController`, `PasswordController`.
* **Form Requests:** `ProfileUpdateRequest`, `PasswordUpdateRequest`.
* **Rule:** `App\Rules\Cpf`, reaproveitada por `StoreUserRequest`, `UpdateUserRequest` e `ProcessInvitationRequest`.
* **Ajuda contextual:** `<x-help-button key="profile.edit" />`.

> `password.update` e não `password.store`: `password.store` já pertence à rota pública de reset (`Auth\NewPasswordController`) do UC01.

---

## **8. Fora de Escopo**

* Re-verificação de e-mail após troca (o projeto não usa `MustVerifyEmail`).
* Upload de foto/avatar.
* Exclusão da própria conta.
