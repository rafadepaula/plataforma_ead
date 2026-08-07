# **18. Gestão de Perfil do Usuário com Isolamento Multitenant**

---

## **1. Visão Geral & Mapeamento de Requisitos Multitenant**

Fecha a lacuna do UC02: hoje não existe nenhuma rota, controller ou view de perfil no sistema (`route:list` não retorna nada para `profile`/`perfil`). O usuário autenticado — qualquer role — não consegue alterar os próprios dados nem a própria senha; a única forma de mudar um cadastro é um Gestor/Admin editá-lo pela tela de Gestão de Usuários (RF04), o que é inaceitável para a senha.

* **RF01:** Permitir que o próprio usuário autenticado atualize seus dados cadastrais (nome, e-mail, CPF).
* **RF34:** Permitir que o próprio usuário autenticado altere sua senha mediante confirmação da senha atual, invalidando as demais sessões ativas.
* **RN08:** Restrição de acesso por contexto — o usuário só edita o próprio registro. Não existe rota de perfil que aceite `{user}`; o alvo é sempre `Auth::user()`.
* **RN12:** Impersonate Org preservado — a edição de perfil age sobre a linha `users` individual e **nunca** lê ou grava `org_id`. Um Admin impersonando uma Org edita o próprio cadastro global, não o da Org impersonada.
* **RN14:** Auditoria de alterações cadastrais via `AuditableTrait` já presente em `User`, com senha sempre redigida como `[REDACTED]`.
* **RN17 (nova):** Validação de CPF por dígito verificador, aplicada de forma uniforme em todos os pontos de entrada de CPF do sistema.
* **Roles Cobertas:** `role:admin`, `role:gestor`, `role:aluno` — o perfil é universal, sem diferença de comportamento por role.
* **Casos de Uso:** UC02.

### **1.1. Escopo Explícito — Fora de Alcance**

Decisões tomadas na sessão de grill-me, registradas para que não sejam reabertas por engano:

* **Re-verificação de e-mail:** ao trocar o e-mail, `email_verified_at` **não** é zerado e nenhum link de verificação é enviado. O projeto não usa `MustVerifyEmail` (está comentado em `App\Models\User`) e não possui fluxo de verificação em lugar nenhum — ativá-lo só no perfil criaria um caminho órfão. Se a verificação for desejada, vira um UC próprio.
* **Upload de foto/avatar:** fora de escopo. Exigiria coluna nova em `users`, `FileUploadService` e alteração do topbar.
* **Exclusão da própria conta:** fora de escopo. Conflita com soft-delete, matrículas e certificados já emitidos — precisa de decisão de produto antes de virar requisito.

---

## **2. Modelo do Banco de Dados & Segurança**

**Nenhuma migration nova.** Todas as colunas envolvidas já existem em `users`: `name`, `email`, `cpf`, `password`, `email_verified_at`, `org_id`, `status`.

* **Isolamento & Segurança**:
  * `User` intencionalmente **não** usa a Trait `OrgScope` (ver docblock de `App\Models\User`). O isolamento aqui não é por tenant e sim por identidade: o controller opera exclusivamente sobre `$request->user()`, então não existe superfície cross-tenant a proteger. Nenhuma Policy é necessária — não há `{user}` na rota para autorizar.
  * `org_id` é imutável por este endpoint. Os Form Requests não o declaram e o controller nunca o repassa a `update()`, mesmo padrão de `UpdateUserRequest`.
  * `status` também é imutável por este endpoint — um usuário não pode se reativar sozinho.
  * Middleware `auth` em todas as rotas. Um convidado recebe redirect para `/login` (comportamento padrão do Laravel).
  * A troca de senha invalida as demais sessões via `Auth::logoutOtherDevices()`. `SESSION_DRIVER=database` já está configurado, então o mecanismo funciona. Protege contra sessão sequestrada sobreviver à troca de senha.
  * Rate limiting: `throttle:6,1` na rota de atualização de senha, para que `current_password` não vire um oráculo de força bruta contra a senha da sessão ativa.

---

## **3. Domain Services & Regras de Negócio**

* **`ProfileController`** (`app/Http/Controllers/ProfileController.php`):
  * `edit()` — renderiza `profile.edit` com `$request->user()`.
  * `update(ProfileUpdateRequest)` — grava nome/e-mail/CPF, redireciona para `profile.edit` com flash `success` = *"Perfil atualizado com sucesso."*
* **`PasswordController`** (`app/Http/Controllers/PasswordController.php`):
  * `update(PasswordUpdateRequest)` — grava `Hash::make()`, chama `Auth::logoutOtherDevices()`, redireciona para `profile.edit` com flash `success` = *"Senha alterada com sucesso."*
* **`ProfileUpdateRequest`**:
  * `name` → `required|string|max:255`
  * `email` → `required|string|email|max:255|unique:users,email` ignorando o próprio id
  * `cpf` → `nullable|string|max:14|` + `App\Rules\Cpf` + `unique:users,cpf` ignorando o próprio id
* **`PasswordUpdateRequest`**:
  * `current_password` → `required|current_password` (regra nativa do Laravel, valida contra o guard autenticado)
  * `password` → `required|confirmed|` + `Illuminate\Validation\Rules\Password::defaults()`
* **`App\Rules\Cpf`** (`app/Rules/Cpf.php`) — nova Rule de validação:
  * Normaliza removendo pontuação, rejeita comprimento diferente de 11, rejeita sequências de dígitos idênticos (`00000000000`…`99999999999`) e valida os dois dígitos verificadores pelo módulo 11.
  * **Aplicada de forma uniforme**, não só no perfil: `StoreUserRequest`, `UpdateUserRequest` e `ProcessInvitationRequest` passam a usá-la. Hoje os três só fazem `nullable|string|max:14`, e deixar o perfil mais rigoroso que as telas de admin criaria a situação absurda de um CPF aceito na criação e rejeitado na própria edição.
  * `ImportUsersChunkRequest` fica **de fora** por decisão explícita: uma linha de CSV com CPF inválido deve ser pulada com motivo registrado pelo `UserImportService`, nunca derrubar o lote inteiro de 50 registros com um 422.

### **3.1. Tratamento de Exceções**

| Gatilho | Resposta |
| :--- | :--- |
| E-mail já usado por outra conta | Erro de validação no campo `email`, formulário recarrega via `back()->withErrors()` |
| CPF já usado por outra conta | Erro de validação no campo `cpf` |
| CPF com dígito verificador inválido | Erro de validação no campo `cpf`: *"O CPF informado é inválido."* |
| `current_password` incorreta | Erro de validação no campo `current_password`, senha **não** é alterada |
| Convidado acessando `/profile` | Redirect para `/login` (middleware `auth`) |

> **Nota sobre "HTTP 422":** o UC02 fala em 422 para duplicidade. Estes são formulários Blade, não JSON — o Laravel responde com redirect + erros na sessão, renderizados inline por `components/ui/input.blade.php`. O 422 literal só ocorreria em requisição JSON. Os testes devem asserir a mensagem, não o status.

---

## **4. UI/UX**

* **`resources/views/profile/edit.blade.php`** — estende `layouts.app`, dois `<x-ui.card>` independentes, cada um com seu próprio `<form>`:
  * **Bloco 1 — Informações do Perfil**: `name`, `email`, `cpf`, botão "Salvar Alterações". `dusk="profile-form"`, `dusk="profile-submit"`.
  * **Bloco 2 — Atualizar Senha**: `current_password`, `password`, `password_confirmation`, botão "Atualizar Senha". `dusk="password-form"`, `dusk="password-submit"`.
* **`<x-help-button key="profile.edit" />`** obrigatório no cabeçalho da tela, por RN05 (100% das telas).
* **Acesso**: item "Meu Perfil" no dropdown do usuário em `components/layout/topbar.blade.php`, apontando para `route('profile.edit')`, visível para qualquer usuário autenticado. Deve ser registrado no `NavigationRegistry` (SPEC-17) se a topbar consumir dele.
* Nenhum módulo JS novo. Os dois formulários são POST tradicionais.

### **4.1. Contrato de Rotas**

| Método | URI | Nome | Ação |
| :--- | :--- | :--- | :--- |
| `GET` | `/profile` | `profile.edit` | `ProfileController@edit` |
| `PATCH` | `/profile` | `profile.update` | `ProfileController@update` |
| `PUT` | `/profile/password` | `password.update` | `PasswordController@update` |

> `password.update` e não `password.store`: `password.store` já está ocupado pela rota de reset público (`Auth\NewPasswordController`), e colidir quebraria o fluxo de recuperação de senha do UC01.

---

## **5. Checklist de Implementação & Testes (Target: 95%+ Coverage & Dusk E2E)**

- [ ] `App\Rules\Cpf` com validação de dígito verificador
- [ ] Aplicar `Cpf` em `StoreUserRequest`, `UpdateUserRequest` e `ProcessInvitationRequest` (não em `ImportUsersChunkRequest`)
- [ ] `ProfileUpdateRequest` e `PasswordUpdateRequest`
- [ ] `ProfileController` e `PasswordController`
- [ ] Rotas `profile.edit`, `profile.update`, `password.update` sob `auth`, com `throttle:6,1` na troca de senha
- [ ] View `profile/edit.blade.php` com os dois blocos, seletores dusk e `<x-help-button key="profile.edit" />`
- [ ] Link "Meu Perfil" na topbar
- [ ] Artigo de ajuda `profile.edit` no `HelpArticleSeeder`
- [ ] Harness: criar as 3 skills (`profile-architecture`, `profile-conventions`, `profile-maintenance`)
- [ ] Testes PHPUnit: `tests/Unit/Rules/CpfTest.php`, `tests/Feature/ProfileTest.php`, `tests/Feature/PasswordUpdateTest.php`
- [ ] Teste Dusk: `tests/Browser/ProfileTest.php` cobrindo os 5 cenários do UC02 — editar dados com sucesso, atualizar senha com sucesso, e-mail/CPF duplicado rejeitado, senha atual incorreta rejeitada, convidado redirecionado para `/login`
