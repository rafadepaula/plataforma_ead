# **Especificação de Caso de Uso: UC06 — Auto-cadastro e Convite Inteligente Adaptativo Multi-Org**

---

## **1. Identificação do Caso de Uso**

* **ID:** UC06
* **Nome:** Auto-cadastro e Convite Inteligente Adaptativo Multi-Org
* **Módulo:** Convites e Matrículas (`Invitations & Adaptive Auth`)
* **Atores Principais:** Eletricista / Aluno (Novo ou Já Cadastrado)
* **Atores Secundários:** Administrador, Gestor (Criadores do Link)
* **Versão:** 2.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RF03** | Permitir auto-cadastro de novos alunos e vínculo de alunos existentes via `invitation_links` por Org sem duplicar a conta do usuário. |
| **Requisito Funcional** | **RF21** | Realizar a matrícula automática em `course_user` após a conclusão do convite. |
| **Requisito Funcional** | **RF25** | Disparar notificação de confirmação de matrícula. |
| **Regra de Negócio** | **RN08** | Restrição Estrita de Acesso por Matrícula e Org. |
| **Regra de Negócio** | **RN09** | **Fluxo Adaptativo de Convite sem Duplicidade:** Se o e-mail informado no convite já existir no sistema, solicita a senha e vincula o usuário ao novo curso daquela Organização sem duplicar o registro na tabela `users`. |
| **Regra de Negócio** | **RN13** | Envio seguro de e-mails em bloco try/catch. |
| **Regra de Negócio** | **RN14** | Mascaramento LGPD e Auditoria. |

---

## **3. Visão Geral e Objetivo**

Permitir que um aluno entre em um curso específico através de um link público de convite (`/convite/{token}`). O sistema oferece uma experiência adaptativa inteligente: se for um novo usuário, solicita o cadastro completo; se o e-mail já possuir conta registrada na plataforma (mesmo que em outra Organização), a interface se adapta para solicitar apenas a confirmação da senha existente, realizando a nova matrícula em `course_user` sem duplicar a conta de usuário (RN09).

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* Um link de convite válido deve ter sido gerado na tabela `invitation_links` contendo um `token` de 64 caracteres, vinculado a um `course_id` e `org_id` ativo, com `revoked_at IS NULL` e contagem de usos `current_uses < max_uses` (ou `max_uses NULL`).

### **4.2. Pós-condições**
* **Novo Usuário:** Registro criado em `users` com Role `aluno`; registro criado em `course_user` com status `active`; sessão autenticada automaticamente; aluno redirecionado para a Sala de Aula `/courses/{course}/classroom`.
* **Usuário Existente:** Registro existente em `users` mantido; nova linha criada em `course_user` associando o `user_id` ao novo `course_id`; aluno autenticado e redirecionado para a Sala de Aula.
* Contador `current_uses` incrementado na tabela `invitation_links`.

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal 1: Auto-cadastro de Novo Usuário via Convite**

1. O aluno acessa a URL pública do convite `/convite/{token}` no navegador.
2. O `InvitationController::show()` valida o token na tabela `invitation_links`:
   - Verifica se o token existe e não está revogado (`revoked_at IS NULL`).
   - Verifica se não expirou (`expires_at >= now()`).
   - Verifica limite de uso (`current_uses < max_uses`).
3. O sistema renderiza a página adaptativa `invitations.show` exibindo:
   - Card com Título e Imagem do Curso.
   - Campo **E-mail** (`id="email"`, obrigatorio).
   - Botão **"Verificar E-mail"** ou continuação fluida via AJAX.
4. O aluno digita seu e-mail (ex: `novo.aluno@provedor.com`) no campo E-mail.
5. Ao perder o foco do campo ou clicar em prosseguir, o script `SmartInvitationForm.js` envia uma requisição AJAX `POST /convite/check-email` contendo `{email}`.
6. O backend verifica que o e-mail **não existe** na tabela `users` e retorna JSON `{exists: false}`.
7. O formulário expande suavemente exibindo os campos adicionais de cadastro:
   - **Nome Completo** (`name="name"`, obrigatorio).
   - **CPF** (`name="cpf"`, opcional).
   - **Senha** (`name="password"`, obrigatorio, min 8).
   - **Confirmação de Senha** (`name="password_confirmation"`).
   - Botão **"Criar Conta e Acessar Curso"**.
8. O aluno preenche os dados e clica em **"Criar Conta e Acessar Curso"** (`POST /convite/{token}`).
9. O `ProcessSmartInvitationAction` executa dentro de uma transação com `lockForUpdate`:
   - Cria o usuário em `users` com `org_id` resolvido do convite.
   - Atribui a Role `aluno`.
   - Inseri o registro em `course_user` (`status => 'active'`).
   - Incrementa `current_uses` em `invitation_links`.
10. O sistema efetua o login automático do aluno (`Auth::login($user)`) e redireciona para a Sala de Aula do curso: `/courses/{course}/classroom`.

---

### **5.2. Fluxo Principal 2: Aluno Já Cadastrado Acessando Novo Convite (RN09)**

1. O aluno acessa a URL do convite `/convite/{token}` e digita um e-mail que **já existe** na tabela `users` (ex: `aluno.existente@provedor.com`).
2. O script `SmartInvitationForm.js` dispara o check de e-mail via `POST /convite/check-email`.
3. O backend identifica que o e-mail já está cadastrado e retorna `{exists: true, user_name: "Nome do Aluno"}`.
4. A interface se adapta instantaneamente:
   - Oculta os campos de Nome, CPF e Confirmação de Senha.
   - Exibe a mensagem personalizada: *"Identificamos sua conta, [Nome]! Digite sua senha para se matricular neste curso."*
   - Exibe apenas o campo **Senha** (`name="password"`).
   - O botão altera o rótulo para **"Confirmar Senha e Entrar no Curso"**.
5. O aluno digita sua senha atual e clica em **"Confirmar Senha e Entrar no Curso"**.
6. O backend valida as credenciais via `Hash::check()`:
   - Não cria uma nova linha na tabela `users`.
   - Registra a nova matrícula na tabela `course_user` para aquele `course_id` (caso ainda não matriculado).
   - Incrementa `current_uses` no link de convite.
7. O aluno é autenticado e redirecionado diretamente para `/courses/{course}/classroom` com feedback: *"Matrícula realizada com sucesso!"*

---

## **6. Fluxos de Exceção**

### **6.1. Fluxo de Exceção 1: Link de Convite Invalido ou Expirado**
* **Gatilho:** Token inexistente, revogado ou com `current_uses >= max_uses`.
* **Comportamento:** Ao acessar `/convite/{token}`, o sistema bloqueia o formulário e exibe a tela de erro `invitations.invalid`: *"Este link de convite é inválido, expirou ou atingiu o limite máximo de utilizações. Entre em contato com o suporte."*

---

## **7. Assinatura Técnica de Rotas e Componentes**

* **Rotas:** `GET /convite/{token}`, `POST /convite/check-email`, `POST /convite/{token}`.
* **Middleware:** `guest`.
* **Action:** `App\Actions\ProcessSmartInvitationAction`.
* **JS Module:** `public/js/modules/SmartInvitationForm.js`.
