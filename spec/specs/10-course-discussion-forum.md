# **10. Fórum de Discussão do Curso com Isolamento Multitenant**

---

## **1. Visão Geral Multitenant & Requisitos**

* **RF22:** Fórum de Discussão do Curso por Organização (criação de tópicos, respostas, XSS sanitization, fixar tópico pelo Gestor).
* **RN08 / RN10:** Isolamento estrito por `org_id` e por matrícula ativa em `course_user`.
* **RF26 (nova):** Denúncia de post pelo Aluno + fila de revisão pelo Gestor.
* **RF27 (nova):** Histórico público de edição de posts.
* **Roles Cobertas:** `role:admin`, `role:gestor`, `role:aluno`.

---

## **2. Segurança & Polling AJAX**

- `OrgScope` no Model `ForumTopic` e Middleware `EnsureStudentIsEnrolled` garantem isolamento estrito.
- Polling AJAX jQuery a cada 10s buscando novas respostas via `fetchNewReplies` sem dependência de WebSockets. Endpoint pagina por `since_id` (não full-refetch) e é limitado por rate-limit padrão do Laravel (`throttle:60,1`) para conter carga do polling em cursos com muitos alunos simultâneos.

## **2.1. Edição de Post (Sem Prazo, com Histórico Público)**

- Autor de `forum_topics`/`forum_replies` pode editar o próprio post **a qualquer momento**, sem janela de expiração.
- Toda edição grava uma linha em `forum_post_edits` (SPEC-00 §2.1 item 18) com `previous_content` = conteúdo *antes* da edição, e atualiza `edited_at` no post.
- A UI do post exibe selo "Editado em {edited_at}" com link "ver histórico" abrindo modal com todas as versões anteriores (`forum_post_edits` ordenado por `edited_at`) — visível a **qualquer usuário** com acesso ao tópico (não só ao autor/gestor), garantindo transparência editorial.
- Apagar post continua permitido ao autor a qualquer momento (soft-delete lógico via remoção de exibição + preservação em `forum_post_edits` do último estado antes da remoção, para efeito de moderação/denúncia em andamento).

## **2.2. Denúncia & Moderação (`ReportForumPostAction` / Fila do Gestor)**

- Qualquer `role:aluno` matriculado ou `role:gestor` da Org pode denunciar um tópico/resposta via botão "Denunciar", preenchendo `forum_reports.reason` (obrigatório).
- `forum_reports.status` inicia `pending`. Gestor da Org enxerga fila de denúncias pendentes (`GET /forum/moderation`, escopado por `OrgScope`).
- Ações do Gestor sobre uma denúncia:
  - **Descartar:** `status = reviewed_dismissed`, post permanece visível.
  - **Remover post:** `status = reviewed_removed` + remove o post (mesma trilha de "apagar" de §2.1, preservando histórico em `forum_post_edits`).
- `role:gestor` e `role:admin` também podem apagar/fixar qualquer post diretamente (ação direta de moderação), independente de haver denúncia registrada — a fila de denúncia é um canal adicional, não o único caminho de moderação.
- Notificação ao Gestor de nova denúncia via SPEC-13 (Notificações) é **fora do escopo desta versão** (não está na lista de gatilhos definida em SPEC-13 §2) — fica só visível na fila do dashboard.

---

## **3. Checklist de Implementação & Testes (Target: 95%+ Coverage & Dusk E2E)**

- [ ] Migration `forum_topics` com `org_id`
- [ ] Migrations `forum_post_edits` e `forum_reports`
- [ ] Model `ForumTopic` com `OrgScope`
- [ ] Action `ReportForumPostAction` + fila de moderação do Gestor (`GET /forum/moderation`)
- [ ] Histórico público de edição (modal "ver histórico" por post)
- [ ] Harness: Criar/atualizar as 3 skills (`forum-architecture`, `forum-conventions`, `forum-maintenance`)
- [ ] Testes Automatizados Backend & Dusk E2E: `ForumTopicTest.php`, `XssSanitizationTest.php`, `ForumModerationQueueTest.php`, `ForumEditHistoryTest.php` aprovados com 100%.
