# **07. Experiência de Aprendizagem do Aluno e Registro de Progresso Multi-Org**

---

## **1. Visão Geral Multitenant & Requisitos**

* **RF13:** Player de Vídeo Responsivo (YouTube incorporado restrito).
* **RF14:** Leitor de PDF Integrado no navegador (iframe seguro + download validado).
* **RF15:** Registro de Conclusão de Lições e Recálculo Global de progresso via AJAX.
* **RF20 / RN08:** Restrição Estrita por Matrícula (HTTP 403 Forbidden para acessos indevidos).
* **RF24 (nova):** Cálculo e persistência de progresso agregado do curso (`course_user.progress_percentage`).

---

## **1.1. Regra de Conclusão de Lição por Tipo (`lesson_progress.completion_source`)**

A conclusão de uma lição depende do seu `type`/conteúdo — não existe um botão "concluir" único para todos os formatos:

| Tipo de lição | Gatilho de conclusão | `completion_source` |
| :--- | :--- | :--- |
| Vídeo (`youtube_url` preenchido) | Player JS (YouTube IFrame API) reporta `watched_seconds` acumulado ≥ **90%** da duração total do vídeo. Evento `onStateChange`/polling de `getCurrentTime()` a cada 5s grava via AJAX em `lesson_progress.watched_seconds`. Ao cruzar o limiar, `is_completed = true` é setado automaticamente. | `video_threshold` |
| Texto / PDF / Imagem (sem `youtube_url`) | Aluno clica no botão explícito "Marcar como concluída" no rodapé do conteúdo (endpoint `POST /lessons/{lesson}/complete`). | `manual_click` |
| Quiz (`type = quiz`) | `SubmitQuizAttemptAction` (SPEC-08 §2) seta `is_completed = true` automaticamente quando `quiz_attempts.is_passed = true`. Aluno **não** tem botão manual nessa lição. | `quiz_passed` |

- Reabrir/re-assistir uma lição já concluída não desfaz `is_completed` (idempotente).
- `watched_seconds` é o maior valor já reportado (`GREATEST`), nunca decresce — evita que perder o `currentTime` no meio de um replay resete o threshold.

## **1.2. Fórmula de Progresso Agregado do Curso**

- **Peso igual por lição, ignorando módulo/carga horária:** `progress_percentage = ROUND((lições com is_completed=true / total de lições publicadas do curso) * 100)`.
- Recalculado de forma **síncrona** (mesma requisição, sem job assíncrono — `QUEUE_CONNECTION=sync` por padrão em SPEC-00 §1.2) a cada gravação em `lesson_progress`, via listener no evento `LessonMarkedAsCompleted`, e persistido em `course_user.progress_percentage`.
- Somente lições com `lessons.is_published = true` entram no denominador; lições ocultadas após a matrícula (curso/módulo `deleted_at` ou despublicado — SPEC-00 §2.1 nota do item 6) são excluídas do cálculo e não bloqueiam 100%.
- Quando `progress_percentage` atinge o valor necessário definido em `course_completion_rules` (SPEC-00 §2.1 item 15) para `rule_type=all_lessons`, `course_user.status` transiciona para `completed` e `completed_at` é gravado — este é o gatilho que a SPEC-09 (Certificados) escuta via evento `CourseCompletedByStudent`.
* **Navegação Multi-Org do Aluno:** O Aluno (`role:aluno`) enxerga em "Meus Cursos" as matrículas de **todas as Organizações**. Ao entrar na aula, a aplicação resolve o contexto da Org pelo `course_id`.

---

## **2. Middleware de Autorização `EnsureStudentIsEnrolled`**

- Valida se o usuário é Admin (acesso livre) ou Gestor da mesma Org do curso.
- Para o Aluno: Verifica se existe vínculo ativo em `course_user` para o `course_id`.
- Aborta com **HTTP 403 (Forbidden)** caso a matrícula não esteja ativa.

---

## **3. Checklist de Implementação & Testes (Target: 95%+ Coverage & Dusk E2E)**

- [ ] Middleware `EnsureStudentIsEnrolled` adaptado para multi-tenant e validação de `course_user`
- [ ] View "Meus Cursos" agrupando cursos por Organização emissora
- [ ] Listener `RecalculateCourseProgress` (evento `LessonMarkedAsCompleted` -> `course_user.progress_percentage`) e evento `CourseCompletedByStudent` disparado ao atingir 100%/regra
- [ ] Player YouTube com tracking de `watched_seconds` e threshold de 90% (`completion_source = video_threshold`)
- [ ] Endpoint `POST /lessons/{lesson}/complete` para conclusão manual (`completion_source = manual_click`)
- [ ] Harness: Criar/atualizar as 3 skills (`learning-architecture`, `learning-conventions`, `learning-maintenance`)
- [ ] Testes Automatizados Backend & Dusk E2E: `MultiOrgStudentClassroomTest.php`, `EnsureStudentIsEnrolledTest.php`, `CourseProgressCalculationTest.php`, `VideoThresholdCompletionTest.php` aprovados com 100%.
