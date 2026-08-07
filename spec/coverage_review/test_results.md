# Resultados de Execução — Loop de Cobertura E2E

| # | UC | Cenario | Metodo | Arquivo | Status |
|---|---|---|---|---|---|
| 1 | UC02 | Editar nome/email/CPF | — | — | ❌ FEATURE AUSENTE |
| 2 | UC02 | Atualizar senha | — | — | ❌ FEATURE AUSENTE |
| 3 | UC02 | E-mail/CPF duplicado 422 | — | — | ❌ FEATURE AUSENTE |
| 4 | UC02 | Senha atual incorreta | — | — | ❌ FEATURE AUSENTE |
| 5 | UC02 | Não-autenticado redirect /login | — | — | ❌ FEATURE AUSENTE |
| 6 | UC04 | Criar usuário | `test_gestor_can_create_a_user_via_the_ui` | UserManagementTest.php | ✅ PASS |
| 7 | UC04 | Editar usuário | `test_gestor_can_edit_a_user_via_the_ui` | UserManagementTest.php | ✅ PASS |
| 8 | UC04 | Inativar usuário | `test_gestor_can_deactivate_a_user_via_the_ui` | UserManagementTest.php | ⏭️ SKIPPED — UI ausente |
| 9 | UC04 | Matricular aluno | `test_gestor_can_manually_enroll_a_student_in_a_course` | UserManagementTest.php | ✅ PASS |
| 10 | UC04 | Remover matrícula | `test_gestor_can_revoke_a_student_enrollment` | UserManagementTest.php | ✅ PASS |
| 11 | UC04 | E-mail duplicado rejeitado | `test_creating_a_user_with_a_duplicate_email_is_rejected` | UserManagementTest.php | ✅ PASS |
| 12 | UC04 | Cross-tenant 403 | `test_gestor_cannot_edit_a_user_from_another_organization` | UserManagementTest.php | ✅ PASS |
| 13 | UC01 | Conta inativa | `test_inactive_user_cannot_login` | Auth/LoginTest.php | ✅ PASS |
| 14 | UC01 | Fluxo recuperação de senha | `test_user_can_reset_the_password_through_the_forgot_password_flow` | Auth/LoginTest.php | ✅ PASS |
| 15 | UC01 | Token de reset inválido | `test_invalid_password_reset_token_is_rejected` | Auth/LoginTest.php | ✅ PASS |
| 16 | UC01 | Login Admin → /admin/dashboard | `test_admin_is_redirected_to_the_admin_dashboard_after_login` | Auth/LoginTest.php | ✅ PASS |
| 17 | UC01 | Login Gestor → /admin/dashboard | `test_gestor_is_redirected_to_the_admin_dashboard_after_login` | Auth/LoginTest.php | ✅ PASS |
