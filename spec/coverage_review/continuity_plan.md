# Plano de Continuidade — Implementação das Lacunas de Cobertura

> Escrito em 2026-08-07, com a execução pausada a pedido. Último commit: `6ee0231 docs(spec): add SPEC-18 user profile management`.

---

## 1. Onde as coisas pararam

Cinco frentes tiveram o **código escrito e deixado na árvore de trabalho, sem commit e sem verificação Dusk**. Três agentes morreram no limite de sessão antes de verificar; os outros dois foram instruídos por mim a parar de rodar Dusk por causa da contenção de banco descrita na seção 3.

### Arquivos não commitados (13 modificados + 1 novo)

| Frente | Arquivos | Estado |
|---|---|---|
| **UC15** — edição de tópico do fórum | `app/Http/Controllers/ForumTopicController.php`, `resources/views/forum/edit.blade.php` (novo), `resources/views/forum/show.blade.php`, `routes/web.php`, `tests/Browser/ForumDuskTest.php` | Código completo. Rota `forum.edit` confirmada em `route:list`. **Dusk não verificado.** |
| **UC04** — inativação de usuário | `resources/views/users/edit.blade.php`, `resources/views/users/index.blade.php`, `tests/Browser/UserManagementTest.php` | Código completo. O `markTestSkipped` foi substituído por teste real. **Dusk não verificado.** |
| **UC05** — validação de cabeçalho CSV | `resources/js/modules/CsvImporter.js`, `tests/Browser/MultiTenantStudentImportTest.php` | Código completo. `npm run build` rodado, Pint verde. **Dusk não verificado.** |
| **UC16** — modal de placeholder da ajuda | `resources/views/components/help-button.blade.php`, `tests/Browser/HelpCenterDuskTest.php`, `tests/Feature/HelpCenterTest.php` | Código completo. **PHPUnit verde (24/24, 53 assertions)**. **Dusk não verificado.** |
| **UC12** — estado "Tempo esgotado" | `tests/Browser/StudentQuizAttemptTest.php` | Só teste, sem mudança de comportamento (decisão registrada). **Dusk não verificado.** |

### Métodos de teste novos aguardando verificação

```
ForumDuskTest::test_an_author_can_edit_a_topic_and_the_history_records_the_previous_version
ForumDuskTest::test_a_student_cannot_edit_someone_elses_topic
UserManagementTest::test_gestor_can_deactivate_a_user_via_the_ui        (era markTestSkipped)
UserManagementTest::test_a_deactivated_user_cannot_login
MultiTenantStudentImportTest::test_a_csv_with_an_invalid_header_is_rejected_before_upload
MultiTenantStudentImportTest::test_a_csv_missing_a_required_column_is_rejected
HelpCenterDuskTest::test_the_help_button_shows_a_placeholder_when_no_article_exists
StudentQuizAttemptTest::test_an_expired_quiz_timer_shows_the_time_is_up_state_without_submitting
HelpCenterTest::test_help_button_renders_placeholder_when_no_article_exists_for_the_screen  (renomeado, PHPUnit, já verde)
```

### Não iniciado

* **UC13** — CRUD de regras de conclusão em `/courses/{course}/completion-rules` (decidido: aninhado no curso, `rule_type` já suportados são `all_lessons`, `min_quiz_score`, `specific_module`).
* **UC13** — tela de aluno com "Certificado indisponível. X%".

### Adiado por decisão sua

* **SPEC-18 / UC02** — perfil do usuário. Especificação pronta e commitada; **nenhuma linha de código**, por instrução explícita. Nada da árvore atual toca nisso.

---

## 2. Ordem de retomada

Cada passo termina com um commit próprio. Não agrupar — se um passo falhar, os anteriores já estão salvos.

1. **Sanity check do ambiente** (seção 3) — sempre antes de qualquer coisa.
2. **Verificar UC15**: `vendor/bin/sail artisan dusk tests/Browser/ForumDuskTest.php`. Corrigir o que falhar. Commit.
3. **Verificar UC04**: `vendor/bin/sail artisan dusk tests/Browser/UserManagementTest.php`. Commit.
4. **Verificar UC05**: `vendor/bin/sail npm run build` e depois `vendor/bin/sail artisan dusk tests/Browser/MultiTenantStudentImportTest.php`. Commit.
5. **Verificar UC16**: `vendor/bin/sail artisan dusk tests/Browser/HelpCenterDuskTest.php`. Commit.
6. **Verificar UC12**: `vendor/bin/sail artisan dusk tests/Browser/StudentQuizAttemptTest.php`. Commit.
7. **Implementar UC13** (as duas telas). Commit.
8. **Suíte Dusk completa** para regressão (~18 min). Commit dos ajustes.
9. **Atualizar** `missing_functionalities.md`, `test_results.md` e `loop_summary.md` para refletir as lacunas fechadas. Commit.

Passos 2 a 6 são independentes entre si — mas **nunca em paralelo** (seção 3).

---

## 3. Restrições de ambiente que causaram os problemas desta sessão

> Isto é o que mais custou tempo. Ler antes de disparar qualquer agente.

* **Um único banco `testing` compartilhado.** Todo processo Dusk roda `DatabaseMigrations` contra o mesmo MySQL. Duas execuções simultâneas produzem `SQLSTATE[42S01] table already exists`, `42S02 doesn't exist` e deadlocks — que **parecem** bugs de aplicação e não são. Consequência prática: **nunca rodar Dusk em mais de um processo ao mesmo tempo**, e portanto nunca delegar verificação Dusk a agentes paralelos. Foi o erro de planejamento desta sessão.
* **Uma execução Dusk interrompida deixa o `.env` trocado** para `APP_ENV=dusk` / `DB_DATABASE=testing`, com um `.env.backup` órfão ao lado. Se isso acontecer, todo comando não-Dusk passa a mirar silenciosamente o banco de testes. **Checar antes de começar:**
  ```bash
  rtk grep -n "APP_ENV\|DB_DATABASE" .env    # esperado: local / plataforma_ead
  rtk proxy ls -la .env.backup               # esperado: não existe
  rtk proxy ps aux | grep -c "[d]usk"        # esperado: 0
  ```
* **A suíte Dusk completa leva ~18 min** (1.050s para 93 testes). Rodar em background e aguardar notificação, não em foreground com timeout.
* **Agentes estão batendo no limite de sessão.** Três morreram por isso. Tarefas delegadas devem ser curtas e não conter loops de retry.

---

## 4. Armadilhas específicas já conhecidas

* **Formulário Blade nunca devolve 422.** O Laravel redireciona com os erros na sessão, renderizados inline por `resources/views/components/ui/input.blade.php`. Asserte a mensagem, nunca o status.
* **`assertSee` logo após submit é corrida.** Usar `waitForText`/`waitFor`. Foi exatamente isso que tornou `test_an_existing_user_with_a_wrong_password_is_not_enrolled` flaky.
* **`SmartInvitationForm` tem debounce de 400ms** além do handler de `blur`. Qualquer teste que colapse aquele formulário precisa de `pause(700)` antes de submeter, senão o `toggleFields` atrasado restaura o `required` de um campo oculto e bloqueia o submit silenciosamente.
* **`HelpCenterDuskTest` exige `waitUntilMissing('.dialog-backdrop')`** antes de clicar no trigger do modal.
* **Flake pré-existente, não introduzido por este trabalho:** `DashboardDuskTest::test_gestor_persists_a_settings_override_via_the_edit_screen` falha na suíte completa com "Waited 5 seconds for page reload" mas passa isolado. Ainda não investigado.

---

## 5. Decisões tomadas que não devem ser reabertas

| Tema | Decisão | Motivo |
|---|---|---|
| Cronômetro do quiz (UC12) | **Não** implementar auto-submit | O docblock de `quiz-timer.js` documenta a escolha oposta de propósito: submit por timer de cliente descarta respostas em rede lenta ou aba em segundo plano. Enforcement é server-side accept-but-fail (SPEC-08 §1.3). |
| Botão de ajuda sem artigo (UC16) | Modal de placeholder "Estamos preparando", substituindo o botão `disabled` | O botão inerte não dava feedback nenhum. Um placeholder definido satisfaz o "never a broken modal" original melhor que um botão morto. |
| Regras de conclusão (UC13) | Rota aninhada `/courses/{course}/completion-rules` | Mesmo padrão de `courses.modules` e `courses.enrollments`; autoriza via `CoursePolicy::update`, sem Policy nova. |
| CPF (SPEC-18) | `App\Rules\Cpf` com dígito verificador, aplicada também a `StoreUserRequest`, `UpdateUserRequest` e `ProcessInvitationRequest`; **fora** de `ImportUsersChunkRequest` | Perfil mais rigoroso que as telas de admin criaria CPF aceito na criação e rejeitado na edição. No CSV, a linha inválida deve ser pulada, não derrubar o lote de 50. |
| Troca de e-mail (SPEC-18) | Sem re-verificação | O projeto nunca habilitou `MustVerifyEmail`; ativar só no perfil criaria caminho órfão. |
| Troca de senha (SPEC-18) | `Auth::logoutOtherDevices()` | `SESSION_DRIVER=database` já configurado; sessão comprometida não deve sobreviver à troca. |
| Codificação da SPEC-18 | **Adiada** | Instrução explícita do usuário nesta sessão. |
