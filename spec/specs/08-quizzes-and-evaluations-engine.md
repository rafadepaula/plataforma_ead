# **08. Motor Multitenant de Questionários, Provas e Avaliações Interativas**

---

## **1. Visão Geral Multitenant & Requisitos**

* **RF08:** Gestão de Questionários pelo Gestor da Organização (`role:gestor`).
* **RF09:** Execução de Provas pelo Aluno (`role:aluno`).
* **RN02 / RN03 / RN04:** Nota percentual, controle de retries e gabarito condicional.
* **RN11 (nova):** Questões `type=essay` exigem correção manual — não entram na correção automática.
* **Isolamento Multitenant:** O quiz herda `org_id` através da lição/curso. Tentativas do aluno registradas em `quiz_attempts`.

---

## **1.2. Tipos de Questão (`quiz_questions.type`)**

| Tipo | Correção | Comportamento |
| :--- | :--- | :--- |
| `single_choice` | Automática | Exatamente 1 `quiz_options.is_correct=true`; `selected_option_ids` deve conter 1 id. |
| `multiple_choice` | Automática | N ≥ 1 opções corretas; acerto exige `selected_option_ids` ser **exatamente** o conjunto de ids corretos (sem parcial — resposta parcialmente certa conta como errada, evita ambiguidade de crédito parcial). |
| `true_false` | Automática | Caso particular de `single_choice` com 2 opções fixas (Verdadeiro/Falso). |
| `essay` | **Manual** | `quiz_options` não se aplica. Grava `essay_answer` (texto livre) e `is_correct = null` até um Gestor corrigir. |

## **1.3. Limites de Tentativa e Tempo**

- `quizzes.max_attempts` (nullable): quando preenchido, bloqueia submissão além do N-ésimo attempt mesmo com `allow_retries=true`. `null` = ilimitado.
- `quizzes.time_limit_minutes` (nullable): quando preenchido, o timer inicia em `quiz_attempts.started_at`; submissão após o limite é **aceita mas marcada** com `is_passed=false` automaticamente (não bloqueia envio — evita perda de resposta por race condition de rede) e um aviso "Tempo excedido" é registrado no attempt.
- Múltiplas tentativas: `course_user`/certificado consideram sempre a **melhor nota** (`MAX(score_percentage)` entre attempts `status=graded`) para fins de `is_passed` exibido ao aluno, mas cada attempt é preservado individualmente no histórico.

---

## **2. Correct Engine (`SubmitQuizAttemptAction`)**

1. Verifica se o aluno possui matrícula ativa no curso da Org.
2. Bloqueia se `allow_retries == false` e o aluno já submeteu a prova, ou se `max_attempts` foi atingido (§1.3).
3. Corrige automaticamente as questões `single_choice`/`multiple_choice`/`true_false` e grava `quiz_attempts`.
4. Se o quiz contém ao menos 1 questão `essay`, o attempt fica `status = awaiting_manual_grading`, `score_percentage = null`, `is_passed = null` até correção — **lição NÃO é marcada concluída ainda**.
5. Se não há questão `essay` pendente (ou após a correção manual completar todas as pendentes via `GradeEssayAnswerAction`), `status = graded` e, se aprovado (`is_passed == true`), registra lição concluída em `lesson_progress` com `completion_source = quiz_passed` (dispara `LessonMarkedAsCompleted`, ver SPEC-07 §1.2).

## **2.1. Correção Manual (`GradeEssayAnswerAction`)**

- Tela do Gestor lista `quiz_attempts.status = awaiting_manual_grading` da sua Org, com as respostas `essay_answer` pendentes.
- Gestor atribui `is_correct` (true/false) por resposta essay; ação grava `graded_by`/`graded_at` em `quiz_answers`.
- Quando todas as questões essay do attempt estão corrigidas, `SubmitQuizAttemptAction::finalizeGrading()` recalcula `score_percentage` (incluindo as essay corrigidas no percentual), seta `status = graded` e dispara o mesmo fluxo do passo 5 acima.

---

## **3. Checklist de Implementação & Testes (Target: 95%+ Coverage & Dusk E2E)**

- [ ] Migrations e Models com `OrgScope` herdado
- [ ] Action `SubmitQuizAttemptAction` (com ramificação `essay` -> `awaiting_manual_grading`)
- [ ] Action `GradeEssayAnswerAction` + tela de correção manual do Gestor
- [ ] Enforcement de `max_attempts` e `time_limit_minutes`
- [ ] Harness: Criar/atualizar as 3 skills (`quizzes-architecture`, `quizzes-conventions`, `quizzes-maintenance`)
- [ ] Testes Automatizados Backend & Dusk E2E: `QuizManagementTest.php`, `SubmitQuizAttemptTest.php`, `EssayManualGradingTest.php`, `QuizAttemptLimitsTest.php` aprovados com 100%.
