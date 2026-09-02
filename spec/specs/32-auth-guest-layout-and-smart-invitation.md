# **32. Autenticação, Shell de Visitante e Convite Inteligente Adaptativo (Material Bootstrap)**

---

## **1. Visão Geral & Mapeamento de Requisitos Multitenant**

* **Objetivo:** Refatorar a tela de login (`auth/login.blade.php`), layout de visitante (`layouts/guest.blade.php`) e o fluxo público de convite inteligente (`convite/show.blade.php` e `convite/invalid.blade.php`) com o padrão Material Bootstrap: split layout com painel institucional em `--blue-100` (46% de largura, colapsável abaixo de `lg`), coluna de formulário de 440px, botão revelador de senha (`PasswordToggle`), formulário adaptativo de convite com verificação assíncrona de e-mail via `SmartInvitationForm.js` (colapsando campos de cadastro se o usuário já possuir conta na plataforma) e switch obrigatório de consentimento.
* **Roles Cobertas:** Visitantes anônimos, novos alunos e usuários já cadastrados.
* **Referência de Design:** `DESIGN.md` §4.13, `_ds/Login e convite - Anatomia.dc.html`.

---

## **2. Estrutura do Layout de Acesso (`layouts.guest` / `x-layout.guest-panel`)**

- **Split Screen Responsivo:**
  - `col-lg-5` (Painel Institucional, ~46%): Fundo `--blue-100` (`.bg-primary-subtle`), marca e nome da organização ativa via `session('tenant_name')`, régua horizontal de 2px, título `h1` *"Acesse a plataforma"*, lead institucional e copyright. Oculto em telas menores que `lg` (`d-none d-lg-flex`).
  - `col-lg-7` (Coluna do Formulário): Largura máxima fixa de **440px** (`--form-max`), centralizada verticalmente, com botão contextual `<x-help-button>` no canto superior direito e flash alerts.

---

## **3. Telas do Módulo de Autenticação e Convite**

### 3.1 Tela de Login (`auth/login.blade.php`)
- Kicker `"Acesso"`, Título `h2` `"Entrar na plataforma"`.
- Formulário POST `route('login')` com seletor `dusk="login-form"`:
  1. `email`: Input com floating label `"E-mail *"`, seletor `dusk="login-email"`.
  2. `password`: Input com floating label `"Senha *"`, seletor `dusk="login-password"`, acompanhado do botão revelador de senha (`PasswordToggle` manipulando classes `.d-none` nos ícones `eye`/`eye-off`).
  3. `remember`: Checkbox tradicional com seletor `dusk="login-remember"`.
  4. `submit`: Botão primário em bloco (100% largura) `"Entrar"` com `dusk="login-submit"`.
  5. `Esqueci minha senha`: Link centralizado com `dusk="forgot-password-link"`.
- Mensagens de erro de credenciais inválidas em alerta `--critical` (sem vermelho).

### 3.2 Convite Inteligente Adaptativo (`convite/show.blade.php` + `SmartInvitationForm.js`)
- Formulário `<form method="POST" action="/convite/{token}" data-check-email-url="/convite/check-email" dusk="invitation-form">`.
- **Módulo JS `SmartInvitationForm.js`:**
  - Dispara POST para `data-check-email-url` no `blur` ou `input` (debounce de 400ms) do campo de e-mail.
  - Servidor responde: `{ "exists": true|false }`.
- **Adaptação Dinâmica da DOM:**

| Campo | Conta Nova (`exists = false`) | Conta Já Existente (`exists = true`) | Seletor Dusk / Data-Attribute |
|---|---|---|---|
| **E-mail** | Visível e Obrigatório | Visível e Obrigatório | `[data-invitation-email]`, `dusk="invitation-email"` |
| **Aviso de Conta** | **Oculto** (`d-none`) | **Visível** | `dusk="invitation-existing-account-hint"` |
| **Nome Completo** | **Visível** e Obrigatório | **Oculto** (`d-none`, `required=false`) | `dusk="invitation-name"` |
| **CPF** | **Visível** e Obrigatório | **Oculto** (`d-none`, `required=false`) | `dusk="invitation-cpf"` |
| **Senha** | Visível e Obrigatório | Visível e Obrigatório | `dusk="invitation-password"` |
| **Confirmar Senha**| **Visível** e Obrigatório | **Oculto** (`d-none`, `required=false`) | `dusk="invitation-password-confirmation"` |
| **Consentimento** | Visível e Obrigatório | Visível e Obrigatório | `name="consent"` |
| **Botão de Envio** | `"Matricular-me"` (Bloco) | `"Matricular-me"` (Bloco) | `dusk="invitation-submit"` |

- **Texto da Dica de Conta Existente:** *"Já encontramos uma conta com este e-mail. Confirme sua senha para se matricular."* (bloco em `--blue-50`, raio 12px).
- **Switch de Consentimento:** Rótulo verbatim: *"Concordo em compartilhar meus dados com a organização responsável por este curso."*.

### 3.3 Tela de Convite Inválido ou Expirado (`convite/invalid.blade.php`)
- Layout `x-layout.public` com status HTTP 404.
- Estado vazio com ícone de cadeado (`lock`), mensagem explicativa (*"Este convite expirou."* / *"Limite de vagas atingido."*) e orientação: *"Peça um novo link ao responsável pelo curso."*.

---

## **4. Seletores Dusk & Contrato E2E**

* `dusk="login-form"`: Formulário de login.
* `dusk="login-email"`: Input de e-mail.
* `dusk="login-password"`: Input de senha.
* `dusk="login-remember"`: Checkbox lembrar-me.
* `dusk="login-submit"`: Botão entrar.
* `dusk="forgot-password-link"`: Link de esqueci a senha.
* `dusk="invitation-form"`: Formulário de convite.
* `dusk="invitation-email"`: Input de e-mail do convite.
* `dusk="invitation-existing-account-hint"`: Alerta de conta já cadastrada.
* `dusk="invitation-name"`: Input de nome do aluno.
* `dusk="invitation-cpf"`: Input de CPF.
* `dusk="invitation-password"`: Input de senha do convite.
* `dusk="invitation-password-confirmation"`: Input de confirmação de senha.
* `dusk="invitation-submit"`: Botão matricular-me.

---

## **5. Checklist de Implementação & Testes**

- [ ] Layout `resources/views/layouts/guest.blade.php` refatorado no split screen Material Bootstrap.
- [ ] View `auth/login.blade.php` com revelador de senha padronizado.
- [ ] View `convite/show.blade.php` e `convite/invalid.blade.php` refatoradas.
- [ ] Módulo JS `SmartInvitationForm.js` testado com respostas assíncronas.
- [ ] Teste Feature: `SmartInvitationTest.php` e `ProcessSmartInvitationActionTest.php`.
- [ ] Teste Dusk: `SmartInvitationAdaptiveDuskTest.php` cobrindo fluxos de nova conta e vinculação de conta existente.
