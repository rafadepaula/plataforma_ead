# Relatório de Cobertura E2E Laravel Dusk — Use Cases

> Gerado em: 2026-08-05 | Auditoria de 23 UCs × 22 arquivos Dusk

---

## 📊 Resumo Executivo

| Categoria | Qtd | % |
|---|---|---|
| ✅ Totalmente Cobertos | 3 | 13% |
| ⚠️ Parcialmente Cobertos | 15 | 65% |
| ❌ Sem Cobertura | 2 | 9% |
| ➖ N/A (meta/CLI) | 3 | 13% |

> [!CAUTION]
> Apenas **3 de 23 UCs** têm cobertura completa (sucesso + todas as exceções relevantes).

---

## ✅ Use Cases Totalmente Cobertos

| UC | Nome | Arquivos de Teste |
|---|---|---|
| UC14 | Validação Pública e Revogação de Certificados | `CertificateVerificationTest.php`, `CertificateRevocationTest.php` |
| UC20 | Logs de Auditoria, Monitoramento e Expurgo | `AuditLogUiTest.php` |
| UC23 | Menu de Navegação Dinâmico | `NavigationMenuDuskTest.php` |

---

## ❌ Use Cases Sem Cobertura Alguma (Crítico)

### UC02 — Gestão de Perfil do Usuário
**Arquivo de teste:** NENHUM

Cenários ausentes:
- Editar nome/email/CPF com sucesso
- Atualizar senha com sucesso
- E-mail/CPF duplicado → HTTP 422
- Senha atual incorreta → erro de validação
- Usuário não-autenticado → redirect `/login`

### UC04 — Gestão de Usuários e Matrículas Manuais
**Arquivo de teste:** NENHUM

Cenários ausentes:
- Criar/editar/inativar usuário (Admin/Gestor)
- Matricular aluno via `POST /courses/{course}/enrollments`
- Remover matrícula
- E-mail/CPF duplicado → HTTP 422
- Acesso cross-tenant → HTTP 403/404

---

## ⚠️ Use Cases Parcialmente Cobertos

### UC01 — Autenticar, Encerrar Sessão e Recuperar Senha
**Arquivo:** `Auth/LoginTest.php`

| Cenário | Status |
|---|---|
| Login com sucesso (aluno) | ✅ |
| Logout com sucesso | ✅ |
| Credenciais erradas | ✅ |
| Conta inativa (status=inactive) | ❌ FALTANDO |
| Fluxo recuperação de senha (forgot → reset) | ❌ FALTANDO |
| Token de reset expirado/inválido | ❌ FALTANDO |
| Login como Admin → redirect /admin/dashboard | ❌ FALTANDO |
| Login como Gestor → redirect /admin/dashboard | ❌ FALTANDO |

### UC03 — Gestão de Organizações e Impersonate Org
**Arquivos:** `OrganizationCrudTest.php`, `ImpersonateOrgTest.php`

| Cenário | Status |
|---|---|
| CRUD completo de org (criar/editar/delete) | ✅ |
| Impersonate + exit + flash | ✅ |
| Gestor não acessa organizations index (403) | ✅ |
| UnresolvedOrgContextException (RN12) | ❌ FALTANDO |

### UC05 — Importação em Lote via CSV
**Arquivo:** `MultiTenantStudentImportTest.php`

| Cenário | Status |
|---|---|
| Upload CSV válido + progress bar + conclusão | ✅ |
| CSV com cabeçalho inválido → alerta JS imediato | ❌ FALTANDO |
| CSV sem colunas obrigatórias → "Cabeçalho inválido" | ❌ FALTANDO |
| E-mail duplicado no CSV → pula/atualiza | ❌ FALTANDO |
| Admin sem impersonate tentando importar (RN12) | ❌ FALTANDO |

### UC06 — Auto-cadastro e Convite Adaptativo
**Arquivo:** `MultiOrgEnrollmentTest.php`

| Cenário | Status |
|---|---|
| Usuário existente re-convidado → form senha-only → 2ª org (RN09) | ✅ |
| Novo usuário — cadastro completo via convite | ❌ FALTANDO |
| Link expirado/revogado → tela `invitations.invalid` | ❌ FALTANDO |
| Limite de usos atingido (current_uses >= max_uses) | ❌ FALTANDO |
| Senha incorreta para usuário existente | ❌ FALTANDO |

### UC07 — Gestão de Cursos, Módulos e Reordenação
**Arquivos:** `CourseManagementTest.php`, `ModuleReorderTest.php`

| Cenário | Status |
|---|---|
| Criar/editar/soft-delete curso | ✅ |
| Criar módulo e lição | ✅ |
| Reordenação drag-and-drop | ✅ |
| Aluno não acessa courses index (403) | ✅ |
| Guard exclusão de curso com alunos ativos (RN11) | ❌ FALTANDO |

### UC08 — Gestão de Lições Multimídia e Sanitização
**Arquivo:** `CourseManagementTest.php` (parcial)

| Cenário | Status |
|---|---|
| Criar lição com content_text | ✅ |
| Criar lição com URL YouTube + sanitização | ❌ FALTANDO |
| Upload de PDF | ❌ FALTANDO |
| URL YouTube inválida → HTTP 422 | ❌ FALTANDO |
| Edição de lição existente | ❌ FALTANDO |

### UC09 — Consumo de Aulas, Sala de Aula e Players
**Arquivo:** `MultiOrgStudentClassroomTest.php`

| Cenário | Status |
|---|---|
| Acesso à sala de aula + rich-text + mark-complete | ✅ |
| Progress bar 0% → 100% | ✅ |
| Aluno não matriculado → HTTP 403 com mensagem específica | ❌ FALTANDO |

### UC10 — Registro e Rastreamento de Progresso
**Arquivos:** `MultiOrgStudentClassroomTest.php`, `VideoThresholdCompletionTest.php`

| Cenário | Status |
|---|---|
| Conclusão manual de lição + progress recalculado | ✅ |
| Video threshold 90% → badge de conclusão | ✅ |
| Re-clique em lição já concluída (idempotência explícita) | ❌ FALTANDO |

### UC11 — Gestão de Questionários e Correção Manual
**Arquivo:** `EssayGradingScreenTest.php`

| Cenário | Status |
|---|---|
| Gestor avalia dissertativa → status=graded | ✅ |
| Fila vazia após todas correções | ✅ |
| Criação de questionário pelo Gestor via UI | ❌ FALTANDO |
| Quiz sem opção correta marcada → HTTP 422 | ❌ FALTANDO |

### UC12 — Realização de Questionários pelo Aluno
**Arquivo:** `StudentQuizAttemptTest.php`

| Cenário | Status |
|---|---|
| Quiz single_choice → graded + is_passed=true | ✅ |
| Submissão dissertativa → awaiting_manual_grading | ✅ |
| Tentativas esgotadas → form oculto (RN03) | ✅ |
| Cronômetro expirado → submissão automática (QuizTimer.js) | ❌ FALTANDO |
| Exibição de gabarito (show_correct_answers=true, RN04) | ❌ FALTANDO |

### UC13 — Configuração de Regras e Emissão de Certificado
**Arquivos:** `VideoThresholdCompletionTest.php`, `CertificateVerificationTest.php`

| Cenário | Status |
|---|---|
| course_user.status=completed + progress=100% | ✅ |
| Download de certificado (hash gerado e persistido) | ✅ |
| Configuração de regras pelo Gestor via UI | ❌ FALTANDO |
| Pré-requisitos incompletos → "Certificado indisponível. X%" | ❌ FALTANDO |

### UC15 — Fórum de Discussão, Histórico e Moderação
**Arquivo:** `ForumDuskTest.php`

| Cenário | Status |
|---|---|
| Criar tópico e reply | ✅ |
| Denúncia → forum_reports com status=pending | ✅ |
| Moderação/pin pelo Gestor | ✅ |
| Edição de tópico + histórico de edições (forum_post_edits) | ❌ FALTANDO |
| Aluno não matriculado tentando acessar → 403 | ❌ FALTANDO |
| Sanitização XSS com `<script>` via UI (RN14) | ❌ FALTANDO |

### UC16 — Landing Page e Central de Ajuda
**Arquivos:** `ExampleSmokeTest.php` (superficial), `HelpCenterDuskTest.php` (parcial)

| Cenário | Status |
|---|---|
| Landing page visível (smoke test) | ⚠️ (sem assertions específicas) |
| Botão de ajuda → abre modal com artigo | ✅ |
| Chave sem artigo → modal "Estamos preparando..." | ❌ FALTANDO |
| Fallback org → global (RN05) | ❌ FALTANDO |

### UC17 — Dashboard Gerencial e Exportação CSV
**Arquivo:** `DashboardDuskTest.php`

| Cenário | Status |
|---|---|
| Admin vê KPI cards + enrollments table | ✅ |
| Gestor isolamento org | ✅ |
| Link export enrollments CSV presente | ✅ |
| Tipo de relatório inválido → 404 | ❌ FALTANDO |

### UC18 — Configurações do Sistema
**Arquivo:** `DashboardDuskTest.php` (1 método)

| Cenário | Status |
|---|---|
| Gestor edita configuração → persiste no DB | ✅ |
| Admin sem impersonate vs. gestor com org (RN12) | ❌ FALTANDO |

### UC19 — Central de Notificações In-App e E-mail
**Arquivo:** `NotificationBellTest.php`

| Cenário | Status |
|---|---|
| Bell visível para Gestor/Aluno, oculta para Admin | ✅ |
| Badge + dropdown (10 mostrados de 12) | ✅ |
| Marcar todas como lidas → badge desaparece | ✅ |
| Clique individual → redirect + read_at preenchido | ✅ |
| Isolamento SMTP — falha não reverte transação (RN13) | ❌ (não testável via UI — aceitável) |

---

## ➖ Use Cases N/A (Infraestrutura / Meta)

| UC | Nome | Motivo |
|---|---|---|
| UC21 | Suíte de Testes / CI-CD | Meta-UC auto-referencial — cobrir via CI pipeline |
| UC22 | Seeders | CLI only — cobrir via PHPUnit Feature tests |

---

## 🔴 Prioridade de Novos Testes

| Prioridade | Ação |
|---|---|
| 🔴 CRÍTICO | Criar `ProfileTest.php` (UC02) — zero cobertura |
| 🔴 CRÍTICO | Criar `UserManagementTest.php` (UC04) — zero cobertura |
| 🔴 CRÍTICO | Completar `Auth/LoginTest.php` (UC01) — forgot-password + conta inativa |
| 🟡 IMPORTANTE | Completar `MultiOrgEnrollmentTest.php` (UC06) — novo usuário + link inválido |
| 🟡 IMPORTANTE | Criar `LessonMultimediaTest.php` (UC08) — YouTube + PDF + sanitização |
| 🟡 IMPORTANTE | Adicionar guard RN11 em `CourseManagementTest.php` (UC07) |
| 🟢 MENOR | Completar UC11 — criação de quiz via UI |
| 🟢 MENOR | Completar UC15 — edição/histórico de posts + XSS via UI |
| 🟢 MENOR | Completar UC16 — fallback de ajuda + chave sem artigo |
