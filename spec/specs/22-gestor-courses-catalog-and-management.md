# **22. Catálogo de Cursos do Gestor e Proteção de Matrículas (Material Bootstrap)**

---

## **1. Visão Geral & Mapeamento de Requisitos Multitenant**

* **Objetivo:** Refatorar a listagem e gestão de cursos (`courses/index.blade.php`) do Gestor/Admin com o padrão Material Bootstrap: `DataTable` estilizada, `FilterBar` com busca e abas de status, 4 ações de linha com hierarquia visual clara, cards responsivos para mobile (<md) sem duplicação de seletores Dusk, e proteção rígida contra exclusão de cursos com matrículas ativas.
* **Roles Cobertas:** `role:admin`, `role:gestor`.
* **Referência de Design:** `spec/new_ds/DESIGN.md` §4.3, `spec/new_ds/Cursos - Anatomia.dc.html`, `spec/new_ds/Tabelas.dc.html`.

---

## **2. Estrutura de UI & Componentes da Tela (`courses.index`)**

### 2.1 Cabeçalho da Página (`x-layout.page-header`)
- **Kicker:** Overline azul `"Gestão"`.
- **Título (`h1`):** `"Cursos"`.
- **Subtítulo:** `"Gerencie os cursos da sua Organização, seus módulos e as regras de conclusão."`
- **Ação:** Botão Primário com ícone `plus`: `"Novo curso"` (`dusk="new-course"`, rota `courses.create`).

### 2.2 Barra de Filtros (`x-ui.filter-bar`)
- Input de busca textual com floating label por título de curso (`Enter` ou debounce).
- Abas de status: *"Todos"*, *"Publicados"*, *"Rascunhos"*.
- Botão ghost *"Limpar filtros"*.

### 2.3 Tabela de Cursos Desktop (`x-ui.data-table`) — `≥ md`
- Wrapper `.ds-table-wrap` (fundo branco, raio 20px, elevação 1, sem bordas verticais, linhas pares com zebrado azul sutil `--blue-50`, hover com tint azul a 6%).
- **5 Colunas Estruturadas:**
  1. `Título`: Título do curso (16px/600) + caption com `"N módulos · N aulas"` (ou *"Sem módulos cadastrados"*).
  2. `Carga horária` (150px): Formato `"{N} horas"` (ou `"{N} hora"`).
  3. `Alunos` (110px, alinhado à direita): Inteiro com `tabular-nums`.
  4. `Status` (130px): Chip `.ds-chip-success` *"Publicado"* ou `.ds-chip-neutral` *"Rascunho"*.
  5. `Ações` (470px, alinhadas à direita em gap de 8px):
     - **Módulos:** Botão variante `tonal sm`, rota `courses.modules.index`, seletor `dusk="manage-modules-{{ $course->id }}"`.
     - **Regras de conclusão:** Botão variante `ghost sm`, rota `courses.completion-rules.index`, seletor `dusk="manage-completion-rules-{{ $course->id }}"`.
     - **Editar:** Botão variante `ghost sm`, rota `courses.edit`, seletor `dusk="edit-course-{{ $course->id }}"`.
     - **Remover:** Botão ícone de lixeira (36x36px) em tom `--critical`, seletor `dusk="delete-course-{{ $course->id }}"`.

### 2.4 Guarda de Matrícula Ativa & Modal de Exclusão
- **Proteção:** Se `$course->students_count > 0`, o botão de lixeira é renderizado como `disabled` (`opacity: .45`) acompanhado da legenda: `<span class="ds-caption">{N} aluno(s) matriculado(s)</span>`.
- **Modal de Confirmação (`ConfirmModal`):** Se desprotegido, abre diálogo com tom `--critical`, título *"Remover curso"*, mensagem explicativa: *"Remover “{Título do Curso}” é uma ação permanente. Cursos com matrículas ativas não podem ser removidos."*, e botão de submissão do formulário DELETE com `dusk="delete-form-{{ $course->id }}"`.

### 2.5 Cards Responsivos para Mobile (`< md`)
- Em telas menores que 768px, a tabela é substituída por lista de cards (`x-ui.card`) contendo chip de status no topo, título, linha de metadados e rodapé com as 4 ações.
- **Regra Dusk:** Nenhum seletor `dusk` é duplicado nos cards mobile para garantir a integridade dos testes de navegador.

---

## **3. Paginação & Estados Vazios**

- **Paginação (`x-ui.pagination`):** Pílulas circulares de 40px, contador à esquerda (*"Mostrando 1–10 de 24 cursos"*), renderizada apenas com 2+ páginas.
- **Estado Vazio (`EmptyState`):** Ocupa o corpo da tabela com ícone `book-open` (32px), título *"Nenhum curso cadastrado"*, descrição explicativa e botão primário *"Criar curso"*.

---

## **4. Seletores Dusk & Contrato E2E**

* `dusk="new-course"`: Botão de criar novo curso no cabeçalho.
* `dusk="course-row-{id}"`: Linha `<tr>` do curso na tabela.
* `dusk="manage-modules-{id}"`: Botão tonal para gerenciar módulos.
* `dusk="manage-completion-rules-{id}"`: Botão ghost para gerenciar regras de conclusão.
* `dusk="edit-course-{id}"`: Botão ghost para editar dados do curso.
* `dusk="delete-course-{id}"`: Botão de lixeira para abrir modal de exclusão.
* `dusk="delete-form-{id}"`: Formulário/botão de confirmação de exclusão no modal.

---

## **5. Checklist de Implementação & Testes**

- [ ] View `resources/views/courses/index.blade.php` refatorada no padrão Material Bootstrap.
- [ ] Componente `title-cell.blade.php` e `row-actions.blade.php` padronizados.
- [ ] Guarda de matrículas ativas validada no backend e na UI.
- [ ] Modal de exclusão com tom `--critical` e texto explícito.
- [ ] Teste Feature: `MultiTenantCourseManagementTest.php` cobrindo CRUD e bloqueio de exclusão com matrículas.
- [ ] Teste Dusk: `CourseManagementUiTest.php` cobrindo listagem, paginação, modal de confirmação e ações.
