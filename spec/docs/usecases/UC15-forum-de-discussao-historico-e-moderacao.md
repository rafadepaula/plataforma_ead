# **Especificação de Caso de Uso: UC15 — Fórum de Discussão do Curso, Histórico Público de Edições e Moderação**

---

## **1. Identificação do Caso de Uso**

* **ID:** UC15
* **Nome:** Fórum de Discussão do Curso, Histórico Público de Edições e Moderação
* **Módulo:** Fórum e Comunidade (`Course Discussion Forum`)
* **Atores Principais:** Aluno Matriculado, Gestor de Organização, Administrador Global
* **Versão:** 2.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RF22** | Disponibilizar área interativa por curso onde alunos matriculados e professores/admins podem criar tópicos e responder dúvidas. |
| **Requisito Funcional** | **RF25** | Enviar notificação in-app e e-mail para nova resposta no tópico. |
| **Requisito Funcional** | **RF30** | Gravar snapshots em `forum_post_edits` permitindo que os alunos visualizem o histórico de alterações em tópicos e respostas. |
| **Regra de Negócio** | **RN08** | Restrição de Matrícula e Org (`student.enrolled`). |
| **Regra de Negócio** | **RN10** | **Isolamento das Discussões do Fórum:** Apenas alunos matriculados no curso e gestores/admins da Org podem visualizar, criar tópicos ou responder. |
| **Regra de Negócio** | **RN13** | Envio de e-mail de notificação em bloco try/catch. |
| **Regra de Negócio** | **RN14** | Sanitização XSS obrigatória via `ForumContentSanitizerService`. |
| **Regra de Negócio** | **RN15** | **Preservação de Histórico no Fórum:** Snapshots de edição salvos em `forum_post_edits`. |

---

## **3. Visão Geral e Objetivo**

Prover um ambiente seguro de discussão pedagógica por curso, isolado por Organização e restrito a alunos matriculados e gestores. O fórum possui atualização em tempo real via polling AJAX, sanitização rigorosa de texto contra ataques XSS, registro público e imutável do histórico de edições passadas de qualquer publicação (RN15), e uma fila de moderação e denúncias (`forum_reports`) para gestão de conteúdos impróprios.

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* Usuário autenticado e matriculado no curso (`student.enrolled`).

### **4.2. Pós-condições**
* Tópicos/respostas gravados em `forum_topics` e `forum_replies` com HTML sanitizado.
* Edições gravadas na tabela `forum_post_edits`.
* Denúncias gravadas na tabela `forum_reports` e disponibilizadas no painel de moderação (`/forum/moderation`).

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal 1: Criação de Tópico e Sanitização XSS**

1. O aluno matriculado clica na aba **"Fórum de Discussão"** na Sala de Aula do curso (`GET /courses/{course}/forum`).
2. O `ForumTopicController::index()` valida o vínculo em `course_user` via Middleware `student.enrolled` e renderiza a lista de tópicos.
3. O aluno clica em **"+ Novo Tópico"** (`GET /courses/{course}/forum/create`).
4. O formulário exibe:
   - **Título da Dúvida** (`name="title"`, obrigatorio, max 200).
   - **Conteúdo / Descrição Detalhada** (`name="content"`, textarea / rich text).
5. O aluno clica em **"Publicar Tópico"** (`POST /courses/{course}/forum`).
6. O `ForumContentSanitizerService::sanitize()` sanitiza o texto removendo scripts e tags perigosas (`<script>`, `onload`, `javascript:`).
7. O backend insere o registro em `forum_topics` (`org_id`, `course_id`, `user_id`, `title`, `content`) e redireciona para a discussão.

---

### **5.2. Fluxo Principal 2: Edição e Histórico Público de Edições (RN15)**

1. O autor da mensagem (ou um gestor) clica na ação **"Editar"** em seu tópico ou resposta (`PUT /courses/{course}/forum/topics/{topic}`).
2. O usuário altera o conteúdo da mensagem no formulário e clica em **"Salvar Alterações"**.
3. O backend executa em transação:
   - Lê o conteúdo atual (*antigo*) do tópico/resposta.
   - Cria uma linha na tabela `forum_post_edits`:
     - `postable_type => 'forum_topic'` (ou `'forum_reply'`)
     - `postable_id => $id`
     - `editor_user_id => Auth::id()`
     - `previous_content => $conteudoAntigo`
     - `edited_at => now()`
   - Atualiza `content` e marca `edited_at = now()` na publicação original.
4. Na exibição pública da publicação, surge o indicador: *"Editado em DD/MM/AAAA (Ver histórico)"*.
5. Qualquer aluno matriculado clica no link **"Ver histórico"**.
6. O modal `ForumEditHistory.js` carrega e exibe a linha do tempo contendo todas as versões anteriores da mensagem e quem realizou cada edição.

---

### **5.3. Fluxo Principal 3: Denúncia e Fila de Moderação por Gestores**

1. Um aluno visualiza uma resposta inadequada no fórum e clica no botão **"Denunciar"** (`[data-report-btn]`).
2. O modal `ForumReportModal.js` solicita o **Motivo da Denúncia** (`name="reason"`, obrigatorio).
3. Ao confirmar (`POST /courses/{course}/forum/report`), o backend insere a linha em `forum_reports` (`status = 'pending'`).
4. O gestor da Organização navega até a rota `/forum/moderation`.
5. O `ForumModerationController::index()` lista os conteúdos denunciados com os botões **"Ignorar Denúncia"** (`POST /forum/moderation/{id}/dismiss`) e **"Remover Publicação"** (`POST /forum/moderation/{id}/remove`).
6. Ao clicar em **"Remover Publicação"**, o backend exclui o tópico/resposta e atualiza a denúncia para `reviewed_removed`.

---

## **6. Fluxos de Exceção**

### **6.1. Fluxo de Exceção 1: Tentativa de Acesso por Aluno Não Matriculado (RN10)**
* **Gatilho:** Acesso a `/courses/5/forum` por aluno sem matrícula ativa naquele curso.
* **Comportamento:** O Middleware `student.enrolled` bloqueia a requisição e retorna HTTP 403 (Forbidden).

---

## **7. Assinatura Técnica de Rotas e Componentes**

* **Rotas:** `GET /courses/{course}/forum`, `POST /courses/{course}/forum`, `POST /topics/{topic}/replies`, `GET /topics/{topic}/replies/fetch` (`throttle:60,1`), `POST /courses/{course}/forum/report`, `GET /forum/moderation`.
* **Middleware:** `auth`, `student.enrolled` (para consumo); `auth`, `role:admin|gestor` (para moderação).
* **Services:** `App\Services\ForumContentSanitizerService`.
