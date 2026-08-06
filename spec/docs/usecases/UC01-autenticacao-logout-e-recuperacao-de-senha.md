# **Especificação de Caso de Uso: UC01 — Autenticar, Encerrar Sessão e Recuperar Senha**

---

## **1. Identificação do Caso de Uso**

* **ID:** UC01
* **Nome:** Autenticar, Encerrar Sessão e Recuperar Senha
* **Módulo:** Autenticação e Gestão de Acesso (`Auth`)
* **Atores Principais:** Aluno, Gestor de Organização, Administrador Global
* **Atores Secundários:** Servidor SMTP (Envio de E-mail de Recuperação)
* **Versão:** 2.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RF01** | Permitir login de Administradores, Gestores e Alunos via e-mail e senha com hash bcrypt e sanitização de sessão. |
| **Requisito Funcional** | **RF02** | Enviar e-mail de redefinição de senha com token temporário de uso único via SMTP. |
| **Regra de Negócio** | **RN08** | Restrição Estrita de Matrícula e Org (redirecionamento pós-login por role). |
| **Regra de Negócio** | **RN12** | Regra de Impersonate Org para Admin (sessão inicial sem org ativa). |
| **Regra de Negócio** | **RN13** | Isolamento e Transação no Envio de E-mail (try/catch no serviço SMTP). |
| **Regra de Negócio** | **RN14** | Mascaramento LGPD e Retenção de Auditoria (senha registrada como `[REDACTED]` em `audit_logs`). |

---

## **3. Visão Geral e Objetivo**

Prover a porta de entrada segura da plataforma, permitindo que usuários cadastrados (Admin, Gestor e Aluno) autentiquem-se no sistema com validação de credenciais criptografadas via bcrypt, efetuem logout de forma segura e solicitem a redefinição de senha por e-mail através de tokens temporários de uso único.

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* O usuário deve possuir um cadastro prévio ativo na tabela `users` (`status = 'active'`).
* Para recuperação de senha, o servidor SMTP da aplicação deve estar operacional em `system_settings` ou `.env`.

### **4.2. Pós-condições**
* **Após Login com Sucesso:** Sessão HTTP iniciada com cookie sanitizado CSRF; evento `login.success` gravado na tabela `audit_logs` (com senha `[REDACTED]`); usuário redirecionado para a home do seu perfil.
* **Após Logout:** Sessão invalidada e cookie de sessão destruído; evento `logout` registrado em `audit_logs`.
* **Após Redefinição de Senha:** Hash da senha atualizado em `users.password`, token de redefinição consumido/invalidado e e-mail de confirmação enviado.

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal 1: Autenticação no Sistema (Login)**

1. O usuário acessa a aplicação navegando até a URL `/login` (via botão "Entrar" da Landing Page `/` ou acesso direto).
2. O sistema processa a requisição via `AuthenticatedSessionController::create()` e renderiza a view `auth.login`.
3. A interface apresenta o formulário com os campos:
   - **E-mail** (`type="email"`, `name="email"`, obrigatorio).
   - **Senha** (`type="password"`, `name="password"`, obrigatorio).
   - Checkbox **"Lembrar-me"** (`name="remember"`).
   - Link **"Esqueceu sua senha?"** direcionando para `/forgot-password`.
   - Botão **"Entrar"** (`type="submit"`).
4. O usuário preenche e-mail e senha e clica em **"Entrar"**.
5. O formulário envia uma requisição `POST /login` contendo o token CSRF (`_token`).
6. O backend valida a requisição através do `LoginRequest`:
   - Verifica se o e-mail existe em `users`.
   - Executa `Hash::check()` comparando a senha digitada com a hash bcrypt armazenada.
   - Verifica se `users.status === 'active'`.
7. O backend regenera a sessão (`$request->session()->regenerate()`) para prevenir ataques de Session Fixation.
8. O `AuditService` registra o evento `login.success` em `audit_logs` com `user_id`, `email`, `ip_address` e `password: "[REDACTED]"`.
9. O sistema identifica a Role do usuário e executa o redirecionamento:
   - Se `role:admin`: Redireciona para `/admin/dashboard`.
   - Se `role:gestor`: Redireciona para `/admin/dashboard`.
   - Se `role:aluno`: Redireciona para `/meus-cursos`.

---

### **5.2. Fluxo Principal 2: Encerramento de Sessão (Logout)**

1. O usuário autenticado clica no seu nome/avatar no canto superior direito do topbar (`<x-layout.topbar>`).
2. O dropdown de perfil expande exibindo o botão **"Sair da Conta"**.
3. O usuário clica em **"Sair da Conta"**.
4. O JavaScript submete um formulário oculto via `POST /logout` acompanhado do token CSRF.
5. O backend processa via `AuthenticatedSessionController::destroy()`:
   - Grava o evento `logout` em `audit_logs`.
   - Executa `Auth::guard('web')->logout()`.
   - Invalida a sessão atual (`$request->session()->invalidate()`).
   - Regenera o token CSRF (`$request->session()->regenerateToken()`).
6. O sistema redireciona o usuário para a Landing Page pública `/` com a mensagem flash: *"Sessão encerrada com sucesso."*

---

### **5.3. Fluxo Principal 3: Recuperação de Senha por E-mail**

1. Na tela de login `/login`, o usuário clica no link **"Esqueceu sua senha?"**.
2. O sistema carrega a rota `GET /forgot-password` (`PasswordResetLinkController::create()`) exibindo a view `auth.forgot-password`.
3. O usuário digita seu e-mail cadastrado no campo **E-mail** e clica em **"Enviar Link de Redefinição"**.
4. O formulário envia `POST /forgot-password` (`PasswordResetLinkController::store()`).
5. O backend valida a presença do e-mail e gera um token criptográfico único armazenado na tabela `password_reset_tokens`.
6. O sistema envia a notificação por e-mail contendo o link contendo o token: `/reset-password/{token}?email=usuario@dominio.com`.
7. O usuário abre seu leitor de e-mail, clica no link recebido e é direcionado para a tela `GET /reset-password/{token}` (`NewPasswordController::create()`).
8. A view `auth.reset-password` exibe os campos: E-mail (preenchido), Nova Senha, Confirmação de Senha e botão **"Redefinir Senha"**.
9. O usuário preenche a nova senha e clica em **"Redefinir Senha"** (`POST /reset-password`).
10. O backend valida a senha (mínimo 8 caracteres), atualiza `users.password` via `Hash::make()`, deleta o token utilizado e registra o evento `password.reset` em `audit_logs`.
11. O sistema redireciona o usuário para `/login` com o alerta: *"Sua senha foi redefinida com sucesso. Faça login para continuar."*

---

## **6. Fluxos Alternativos e de Exceção**

### **6.1. Fluxo de Exceção 1: Credenciais Inválidas ou Conta Inativa**
* **Gatilho:** E-mail inexistente, senha incorreta ou `users.status = 'inactive'` no passo 6 do Fluxo 5.1.
* **Comportamento do Sistema:**
  1. O backend rejeita a autenticação.
  2. O `AuditService` grava o evento `login.failed` em `audit_logs` registrando o e-mail tentado, IP e `password: "[REDACTED]"`.
  3. O sistema redireciona de volta para `/login` mantendo o e-mail preenchido e exibindo o alerta de erro: *"As credenciais informadas não correspondem aos nossos registros ou a conta está inativa."*

### **6.2. Fluxo de Exceção 2: Token de Redefinição Expirado ou Inválido**
* **Gatilho:** Token na URL `/reset-password/{token}` alterado ou com mais de 60 minutos de criação.
* **Comportamento do Sistema:**
  1. Ao submeter `POST /reset-password`, o backend detecta a invalidade do token.
  2. Redireciona de volta para `/forgot-password` exibindo o erro: *"Este link de redefinição de senha é inválido ou expirou. Solicite um novo link."*

---

## **7. Assinatura Técnica de Rotas e Componentes**

* **Rotas:**
  - `GET /login` (`name: login`)
  - `POST /login` (`AuthenticatedSessionController@store`)
  - `POST /logout` (`name: logout`, `AuthenticatedSessionController@destroy`)
  - `GET /forgot-password` (`name: password.request`)
  - `POST /forgot-password` (`name: password.email`)
  - `GET /reset-password/{token}` (`name: password.reset`)
  - `POST /reset-password` (`name: password.store`)
* **Middleware:** `guest` (para login e forgot-password), `auth` (para logout).
* **Views Blade:** `auth.login`, `auth.forgot-password`, `auth.reset-password`.
