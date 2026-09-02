# **30. Fórum de Discussão do Curso, FAB e Polling Incremental AJAX (Material Bootstrap)**

---

## **1. Visão Geral & Mapeamento de Requisitos Multitenant**

* **Objetivo:** Refatorar o fórum de discussão do curso (`forum/index.blade.php`, `forum/show.blade.php`, `partials/_topic.blade.php`, `partials/_reply.blade.php`) com o padrão Material Bootstrap: cards interativos de tópicos com avatares de iniciais e badges de fixação, botão FAB flutuante de criação no mobile (<lg), modal elevado de publicação rápida, fallback de página inteira (`forum/create.blade.php`) e polling incremental assíncrono a cada 10 segundos via `ForumPolling.js` utilizando `since_id`.
* **Roles Cobertas:** `role:aluno`, `role:gestor`, `role:admin`, middleware `student.enrolled`.
* **Referência de Design:** `DESIGN.md` §4.11, `_ds/Forum - Anatomia.dc.html`.

---

## **2. Estrutura de UI & Componentes (`forum/index.blade.php`)**

### 2.1 Cabeçalho da Página (`x-layout.page-header`)
- **Breadcrumb (3 níveis):** `Meus cursos` → `{Nome do Curso}` → `Fórum`.
- **Kicker:** Overline `"Fórum"`.
- **Título (`h1`):** Título do curso verbatim.
- **Subtítulo:** `"Tire dúvidas e converse com a turma sobre este curso."`
- **Ação Primária:** Botão `"Novo tópico"` (`dusk="new-topic-button"`), visível apenas em telas `≥ lg`. Abaixo de `lg`, é substituído pelo FAB.

### 2.2 Lista e Card de Tópico (`TopicCard` — `_topic.blade.php`)
- Coluna única de leitura de até 880px com espaçamento de 8px entre cards.
- **Card Interativo (`.ds-card.ds-card-interactive` · `dusk="topic-row-{id}"`):**
  - **Avatar (`x-ui.avatar`):** 44x44px com iniciais geradas no servidor sobre fundo pastel.
  - **Miolo do Tópico:**
    - Chip *"Fixado"* (`.ds-chip.ds-chip-info` com `dusk="pinned-badge-{id}"`) se `is_pinned == true`.
    - Título `h4` (17px/700) + Trecho do conteúdo (clamp de 2 linhas escapado).
    - Autoria e Data: `"{Nome do Autor} — {dd/mm/aaaa hh:mm}"`.
    - Contagem de Respostas: Ícone `message-square` (16px) + texto pluralizado (*"N respostas"* ou *"Nenhuma resposta ainda"*).
  - **Ação de Fixar (Gestor/Admin):** `<form>` de fixar (`dusk="pin-form-{id}"`) e link do tópico (`dusk="open-topic-{id}"`) são **irmãos no DOM**, nunca aninhados.

### 2.3 Diálogo de Criação (`#new-topic-modal`) & FAB Mobile
- **Modal de Criação:** Modal `md` (raio 28px, elev-5).
  - Contrato de Associação: `<form id="new-topic-form" dusk="new-topic-form">` no corpo, e botão no rodapé com `form="new-topic-form"`, texto *"Publicar tópico"* e `dusk="new-topic-submit"`.
  - Campos: `title` (`dusk="new-topic-title"`), `content` (`dusk="new-topic-content"`).
- **FAB Mobile (`x-ui.fab`):** Pílula de 56px no canto inferior direito, renderizado apenas abaixo de `lg`, abrindo o modal de criação.

---

## **3. Polling Incremental AJAX (`ForumPolling.js`)**

- **Tela de Threads (`forum/show.blade.php`):** Contêiner com atributos `[data-forum-polling]`, `data-fetch-url="{{ route('forum-replies.fetch', [$course, $topic]) }}"`, e `data-last-id="{{ $highestReplyId }}"`.
- **Intervalo:** Polling a cada 10 segundos (`10000ms`).
- **Requisição:** `GET {fetch-url}?since_id={lastId}`.
- **Resposta JSON:** Retorna apenas as novas respostas criadas após o último ID, clonando o partial `_reply.blade.php` no DOM sem refazer reload completo.
- **Resiliência:** Tratamento transparente de 429 (rate limit) sem quebrar o loop.

---

## **4. Seletores Dusk & Contrato E2E**

* `dusk="new-topic-button"`: Botão novo tópico no cabeçalho desktop.
* `dusk="topic-row-{id}"`: Card do tópico na listagem.
* `dusk="open-topic-{id}"`: Link para abrir o tópico.
* `dusk="pinned-badge-{id}"`: Badge indicando tópico fixado.
* `dusk="pin-form-{id}"`: Formulário de fixar/desafixar tópico.
* `dusk="pin-topic-{id}"`: Botão submit de fixar/desafixar.
* `dusk="no-topics"`: Estado vazio do fórum.
* `dusk="new-topic-form"`: Formulário de criação de tópico.
* `dusk="new-topic-title"`: Input de título do tópico.
* `dusk="new-topic-content"`: Textarea de conteúdo do tópico.
* `dusk="new-topic-submit"`: Botão publicar tópico.
* `dusk="reply-{id}"`: Container de resposta.
* `dusk="reply-content-{id}"`: Conteúdo da resposta.

---

## **5. Checklist de Implementação & Testes**

- [ ] Views `resources/views/forum/*` refatoradas no padrão Material Bootstrap.
- [ ] Modal de criação com vínculo HTML5 `form="new-topic-form"`.
- [ ] FAB flutuante para mobile com espaçamento de segurança no fim da lista.
- [ ] Módulo JS `ForumPolling.js` com polling incremental a cada 10s via `since_id`.
- [ ] Teste Feature: `ForumTopicControllerTest.php` e `ForumReplyControllerTest.php`.
- [ ] Teste Dusk: `ForumPollingAndInteractionDuskTest.php` cobrindo criação, fixação e polling em tempo real.
