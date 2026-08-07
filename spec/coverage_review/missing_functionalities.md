# Funcionalidades Ausentes — Bloqueadores de Teste E2E

| UC | Cenario | Arquivo de Teste | Endpoint/Classe Esperada | Erro Observado | Prioridade |
|---|---|---|---|---|---|
| UC02 | Editar nome/email/CPF com sucesso | ProfileTest.php | `GET/PUT /profile` · `ProfileController` | `route:list` sem nenhuma rota `profile`/`perfil`; `app/Http/Controllers/ProfileController.php` inexistente | 🔴 CRÍTICO |
| UC02 | Atualizar senha com sucesso | ProfileTest.php | `PUT /profile/password` · `PasswordController` | Rota e controller inexistentes | 🔴 CRÍTICO |
| UC02 | E-mail/CPF duplicado retorna HTTP 422 | ProfileTest.php | `ProfileUpdateRequest` (unique email/cpf) | Form Request inexistente (feature ausente) | 🔴 CRÍTICO |
| UC02 | Senha atual incorreta retorna erro de validação | ProfileTest.php | `current_password` rule em `PasswordUpdateRequest` | Feature ausente | 🔴 CRÍTICO |
| UC02 | Usuário não-autenticado retorna redirect /login | ProfileTest.php | middleware `auth` em rota `/profile` | Rota inexistente — nada a proteger | 🔴 CRÍTICO |
| UC04 | Inativar usuário (Admin/Gestor) | UserManagementTest.php | controle `status` em `resources/views/users/edit.blade.php` | `UpdateUserRequest` aceita `status` (`in:active,inactive`) e `UserController::update()` audita `user.status_changed`, mas a view não expõe nenhum controle — 0 ocorrências de "status" em `resources/views/users/`. Único caminho de UI é "Remover" (soft delete). | 🟡 IMPORTANTE |
