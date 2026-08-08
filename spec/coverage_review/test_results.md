# Resultados de Execução — Loop de Cobertura E2E

Cada método foi executado isoladamente com `vendor/bin/sail artisan dusk --filter=<metodo>`.

| # | UC | Cenario | Metodo | Arquivo | Status |
|---|---|---|---|---|---|
| 1 | UC02 | Editar nome/email/CPF | — | — | ❌ FEATURE AUSENTE |
| 2 | UC02 | Atualizar senha | — | — | ❌ FEATURE AUSENTE |
| 3 | UC02 | E-mail/CPF duplicado 422 | — | — | ❌ FEATURE AUSENTE |
| 4 | UC02 | Senha atual incorreta | — | — | ❌ FEATURE AUSENTE |
| 5 | UC02 | Não-autenticado redirect /login | — | — | ❌ FEATURE AUSENTE |
| 6 | UC04 | Criar usuário | `test_gestor_can_create_a_user_via_the_ui` | UserManagementTest.php | ✅ PASS |
| 7 | UC04 | Editar usuário | `test_gestor_can_edit_a_user_via_the_ui` | UserManagementTest.php | ✅ PASS |
| 8 | UC04 | Inativar usuário | `test_gestor_can_deactivate_a_user_via_the_ui` | UserManagementTest.php | ✅ PASS — UI implementada |
| 9 | UC04 | Matricular aluno | `test_gestor_can_manually_enroll_a_student_in_a_course` | UserManagementTest.php | ✅ PASS |
| 10 | UC04 | Remover matrícula | `test_gestor_can_revoke_a_student_enrollment` | UserManagementTest.php | ✅ PASS |
| 11 | UC04 | E-mail duplicado rejeitado | `test_creating_a_user_with_a_duplicate_email_is_rejected` | UserManagementTest.php | ✅ PASS |
| 12 | UC04 | Cross-tenant 403 | `test_gestor_cannot_edit_a_user_from_another_organization` | UserManagementTest.php | ✅ PASS |
| 13 | UC01 | Conta inativa | `test_inactive_user_cannot_login` | Auth/LoginTest.php | ✅ PASS |
| 14 | UC01 | Fluxo recuperação de senha | `test_user_can_reset_the_password_through_the_forgot_password_flow` | Auth/LoginTest.php | ✅ PASS |
| 15 | UC01 | Token de reset inválido | `test_invalid_password_reset_token_is_rejected` | Auth/LoginTest.php | ✅ PASS |
| 16 | UC01 | Login Admin → /admin/dashboard | `test_admin_is_redirected_to_the_admin_dashboard_after_login` | Auth/LoginTest.php | ✅ PASS |
| 17 | UC01 | Login Gestor → /admin/dashboard | `test_gestor_is_redirected_to_the_admin_dashboard_after_login` | Auth/LoginTest.php | ✅ PASS |
| 18 | UC06 | Novo usuário via convite | `test_a_new_user_can_register_and_enroll_through_an_invitation_link` | MultiOrgEnrollmentTest.php | ✅ PASS |
| 19a | UC06 | Link expirado | `test_an_expired_invitation_link_is_rejected` | MultiOrgEnrollmentTest.php | ✅ PASS |
| 19b | UC06 | Link revogado | `test_a_revoked_invitation_link_is_rejected` | MultiOrgEnrollmentTest.php | ✅ PASS |
| 20 | UC06 | Limite de usos atingido | `test_an_exhausted_invitation_link_is_rejected` | MultiOrgEnrollmentTest.php | ✅ PASS |
| 21 | UC06 | Senha incorreta (usuário existente) | `test_an_existing_user_with_a_wrong_password_is_not_enrolled` | MultiOrgEnrollmentTest.php | ✅ PASS |
| 22 | UC08 | Lição com YouTube + sanitização | `test_gestor_can_create_a_lesson_with_a_sanitized_youtube_url` | LessonMultimediaTest.php | ✅ PASS |
| 23 | UC08 | Upload de PDF | `test_gestor_can_create_a_lesson_with_a_pdf_upload` | LessonMultimediaTest.php | ✅ PASS |
| 24 | UC08 | URL YouTube inválida | `test_an_invalid_youtube_url_is_rejected` | LessonMultimediaTest.php | ✅ PASS |
| 25 | UC08 | Edição de lição | `test_gestor_can_edit_an_existing_lesson` | LessonMultimediaTest.php | ✅ PASS |
| 26 | UC07 | Guard RN11 exclusão de curso | `test_a_course_with_active_enrollments_cannot_be_deleted` | CourseManagementTest.php | ✅ PASS |
| 27 | UC05 | Cabeçalho inválido → alerta JS | `test_a_csv_with_an_invalid_header_is_rejected_before_upload` | MultiTenantStudentImportTest.php | ✅ PASS — validação implementada |
| 28 | UC05 | Sem colunas obrigatórias → "Cabeçalho inválido" | `test_a_csv_missing_a_required_column_is_rejected` | MultiTenantStudentImportTest.php | ✅ PASS — validação implementada |
| 29 | UC05 | E-mail duplicado no CSV | `test_a_duplicate_email_in_the_csv_reuses_the_existing_user` | MultiTenantStudentImportTest.php | ✅ PASS |
| 30 | UC05 | Admin sem impersonate (RN12) | `test_an_admin_without_an_impersonated_org_cannot_open_the_csv_import_screen` | MultiTenantStudentImportTest.php | ✅ PASS |
| 31 | UC03 | UnresolvedOrgContextException (RN12) | `test_an_admin_without_an_impersonated_org_cannot_create_a_course` | ImpersonateOrgTest.php | ✅ PASS |
| 32 | UC11 | Criação de questionário via UI | `test_gestor_can_create_a_quiz_via_the_ui` | EssayGradingScreenTest.php | ✅ PASS |
| 33 | UC11 | Quiz sem opção correta | `test_a_single_choice_question_without_a_correct_option_is_rejected` | EssayGradingScreenTest.php | ✅ PASS |
| 34 | UC12 | Cronômetro expirado | `test_an_expired_quiz_timer_shows_the_time_is_up_state_without_submitting` | StudentQuizAttemptTest.php | ✅ PASS — auto-submit descartado por decisão; estado "Tempo esgotado" coberto |
| 35 | UC12 | Gabarito (RN04) | `test_the_answer_key_is_shown_when_show_correct_answers_is_enabled` | StudentQuizAttemptTest.php | ✅ PASS |
| 36 | UC13 | Regras de conclusão via UI | `CourseCompletionRuleTest` (Dusk + Feature) | CourseCompletionRuleTest.php | ✅ PASS — CRUD implementado |
| 37 | UC13 | "Certificado indisponível. X%" | `test_student_without_a_certificate_sees_the_unavailable_banner_with_progress` | CertificateVerificationTest.php | ✅ PASS — aviso implementado |
| 38 | UC15 | Edição de tópico + histórico | `test_an_author_can_edit_a_topic_and_the_history_records_the_previous_version` | ForumDuskTest.php | ✅ PASS — tela de edição implementada |
| 39 | UC15 | Aluno não matriculado 403 | `test_a_student_who_is_not_enrolled_cannot_access_the_course_forum` | ForumDuskTest.php | ✅ PASS |
| 40 | UC15 | Sanitização XSS (RN14) | `test_script_tags_submitted_through_the_forum_ui_are_sanitized` | ForumDuskTest.php | ✅ PASS |
| 41 | UC16 | Chave sem artigo → "Estamos preparando..." | `test_the_help_button_shows_a_placeholder_when_no_article_exists` | HelpCenterDuskTest.php | ✅ PASS — placeholder implementado |
| 42 | UC16 | Fallback org → global (RN05) | `test_the_help_button_falls_back_to_the_global_article` | HelpCenterDuskTest.php | ✅ PASS (após 1 correção) |
| 43 | UC16 | Landing page com assertions | `test_the_landing_page_renders_its_sections_and_calls_to_action` | ExampleSmokeTest.php | ✅ PASS |
| 44 | UC17 | Tipo de relatório inválido → 404 | `test_an_unknown_report_type_returns_404` | DashboardDuskTest.php | ✅ PASS |
| 45 | UC18 | Admin sem impersonate vs. gestor (RN12) | `test_settings_are_scoped_to_the_global_row_for_an_admin_and_to_the_org_row_for_a_gestor` | DashboardDuskTest.php | ✅ PASS |
| 46 | UC09 | Aluno não matriculado 403 | `test_a_student_who_is_not_enrolled_cannot_access_the_classroom` | MultiOrgStudentClassroomTest.php | ✅ PASS |
| 47 | UC10 | Re-clique idempotente | `test_completing_an_already_completed_lesson_is_idempotent` | MultiOrgStudentClassroomTest.php | ✅ PASS |
