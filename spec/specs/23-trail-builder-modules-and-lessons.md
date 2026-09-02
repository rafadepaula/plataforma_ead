# **23. Montador de Trilhas de Aprendizado: Módulos, Lições e Drag-and-Drop (Material Bootstrap)**

---

## **1. Visão Geral & Mapeamento de Requisitos Multitenant**

* **Objetivo:** Refatorar as telas de montagem da trilha do curso (`courses/modules/index.blade.php`, `modules/lessons/index.blade.php` e `modules/lessons/_form.blade.php`) com o padrão Material Bootstrap: listas arrastáveis com `ModuleReorder.js`, alças Lucide `grip-vertical`, persistência AJAX assíncrona, formulário de lição com floating labels, dropzone de múltiplos arquivos, pré-visualização ao vivo de vídeo do YouTube em moldura 16:9 pastel wash e switch toggle para publicação.
* **Roles Cobertas:** `role:admin`, `role:gestor`.
* **Referência de Design:** `DESIGN.md` §4.4, `_ds/Montar a trilha - Anatomia.dc.html`.

---

## **2. Estrutura de Listagens Arrastáveis (Módulos & Lições)**

### 2.1 Cabeçalhos de Página (`x-layout.page-header`)
- **Módulos:** Breadcrumb `Cursos / {Curso}`, Kicker `"{Curso}"`, Título `"Módulos"`, Subtítulo: *"Arraste os módulos para reordená-los. A nova ordem é salva automaticamente."*, Ações: Botão tonal *"Voltar aos cursos"* e Botão primário *"Novo módulo"* (`dusk="new-module"`).
- **Lições:** Breadcrumb `Cursos / {Curso} / {Módulo}`, Kicker `"{Curso} / {Módulo}"`, Título `"Lições"`, Subtítulo: *"Arraste as lições para reordená-las. A nova ordem é salva automaticamente."*, Ações: Botão tonal *"Voltar aos módulos"* e Botão primário *"Nova lição"* (`dusk="new-lesson"`).

### 2.2 Anatomia da Lista e Linha Arrastável (`x-sortable-list`, `x-sortable-row`)
- **Contrato DOM:** `<ul data-reorder-url="..." dusk="module-list|lesson-list" class="ds-sortable-list">` contendo `<li data-id="{id}" draggable="true" dusk="module-row-{id}|lesson-row-{id}">`.
- **4 Zonas Horizontais na Linha:**
  1. **Alça de Arraste:** Ícone Lucide `grip-vertical` (20px) em `--text-disabled`, classe `drag-handle`, cursor `grab`.
  2. **Título:** Peso 600, uma linha com ellipsis. Se despublicada: cor `--text-secondary`.
  3. **Chips de Metadados:**
     - Módulo: `.ds-chip.ds-chip-outline.ds-chip-plain` com `"{N} lições"`.
     - Lição: `.ds-chip-outline` *"Conteúdo"* ou `.ds-chip-primary` *"Quiz"*, mais chip neutro *"Não publicada"* se oculta.
  4. **Ações:**
     - Módulo: Botão tonal *"Lições"* (`dusk="manage-lessons-{id}"`), ghost *"Editar"* (`dusk="edit-module-{id}"`), e lixeira ghost para exclusão.
     - Lição: Botão ghost *"Editar"* (`dusk="edit-lesson-{id}"`) e lixeira ghost para exclusão.

### 2.3 Exclusão Destrutiva com Confirmação em Cascata
- Modal `ConfirmModal` com tom `--critical`, alertando explicitamente o efeito em cascata (*"As {N} lições deste módulo também serão removidas. Esta ação não poderá ser desfeita."*).
- Seletores: `dusk="delete-module-form-{id}"`, `dusk="delete-module-{id}"`, `dusk="delete-lesson-form-{id}"`, `dusk="delete-lesson-{id}"`.

---

## **3. Formulário de Lição (`modules/lessons/_form.blade.php`)**

### 3.1 Estrutura e Campos
Coluna única centrada (max 640px) com floating labels:
1. **Título (`title`):** Obrigatório, asterisco azul, validação com `.is-invalid` e feedback em `--critical`.
2. **Tipo de Conteúdo (`type`):** Select (`dusk="lesson-type-select"`). Opções: `content` (*"Conteúdo"*) e `quiz` (*"Quiz (em breve)"*). Ao selecionar quiz, oculta dinamicamente os campos de conteúdo multimídia.
3. **Área de Conteúdo Textual:** Textarea com rich text/markdown.
4. **Zona de Upload de Arquivos (`x-file-drop`):**
   - Dropzone com borda tracejada, ícone `upload` (28px), suporte a drag-and-drop e seleção manual.
   - Imagens: `name="images[]"`, `accept="image/*"`, limite 2MB, `dusk="lesson-image-input"`.
   - PDFs: `name="pdfs[]"`, `accept="application/pdf"`, limite 10MB, `dusk="lesson-pdf-input"`.
   - Lista de anexos com ícone, nome truncado, tamanho em KB/MB, barra de progresso de upload e botão de remoção individual (`dusk="remove-file-{{ $id }}"`).
5. **URL do YouTube & Preview ao Vivo (`x-youtube-field`):**
   - Input `name="youtube_url"`, seletor `dusk="lesson-youtube-input"`.
   - Estado vazio: Bloco 16:9 em **Pastel Wash** (`linear-gradient(135deg, var(--blue-100), var(--mint-100))`) com botão circular e ícone de play.
   - URL preenchida: Renderiza `iframe` com `dusk="youtube-preview"` dinamicamente.
6. **Interruptor Publicado (`x-ui.switch`):**
   - Toggle com trilha menta (`--secondary`) e hint contextual reativo (*"A lição fica visível para os alunos..."* / *"A lição continua oculta..."*).
7. **Ações:** Botão primário *"Criar lição"* / *"Salvar alterações"* e botão ghost *"Cancelar"*.

---

## **4. Fluxos AJAX & Reordenação (`ModuleReorder.js`)**

- **Contrato de Payload:** Evento `drop` envia requisição POST para a URL configurada em `data-reorder-url`:
  ```json
  {
    "ordered_ids": [1, 3, 2, 5]
  }
  ```
- **Feedback Visual:** Toast instantâneo *"Ordem atualizada com sucesso."* em caso de HTTP 200, ou reversão com toast de erro em caso de falha.
- **Acessibilidade:** Botões de mover para cima/baixo para navegação por teclado postando no mesmo endpoint.

---

## **5. Seletores Dusk & Contrato E2E**

* `dusk="new-module"`: Botão de novo módulo no cabeçalho.
* `dusk="module-list"`: Lista `<ul data-reorder-url="...">` de módulos.
* `dusk="module-row-{id}"`: Item `<li>` do módulo.
* `dusk="manage-lessons-{id}"`: Botão de abrir lições do módulo.
* `dusk="edit-module-{id}"`: Botão de editar módulo.
* `dusk="delete-module-{id}"`: Botão submit de exclusão no modal de confirmação.
* `dusk="new-lesson"`: Botão de nova lição no cabeçalho.
* `dusk="lesson-list"`: Lista `<ul data-reorder-url="...">` de lições.
* `dusk="lesson-row-{id}"`: Item `<li>` da lição.
* `dusk="edit-lesson-{id}"`: Botão de editar lição.
* `dusk="delete-lesson-{id}"`: Botão submit de exclusão no modal de confirmação.
* `dusk="lesson-type-select"`: Select de tipo de lição.
* `dusk="lesson-image-input"`: Input de upload de imagens.
* `dusk="lesson-pdf-input"`: Input de upload de PDFs.
* `dusk="lesson-youtube-input"`: Input de URL do YouTube.
* `dusk="youtube-preview"`: Iframe da pré-visualização do vídeo.

---

## **6. Checklist de Implementação & Testes**

- [ ] Views `courses/modules/*` e `modules/lessons/*` refatoradas no padrão Material Bootstrap.
- [ ] Módulo JS `ModuleReorder.js` integrado e testado com alças Lucide `grip-vertical`.
- [ ] Dropzone multi-arquivos com preview e remoção no cliente.
- [ ] Campo YouTube com pastel wash 16:9 e iframe responsivo.
- [ ] Teste Feature: `ModuleReorderTest.php` e `LessonMultimediaTest.php`.
- [ ] Teste Dusk: `ModuleAndLessonManagementDuskTest.php` validando drag-and-drop e uploads.
