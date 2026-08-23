# **26. Catálogo do Aluno "Meus Cursos", Abas Segmentadas e Cards Ricos (Material Bootstrap)**

---

## **1. Visão Geral & Mapeamento de Requisitos Multitenant**

* **Objetivo:** Refatorar a área do aluno "Meus Cursos" (`student/courses/index.blade.php`) com o padrão Material Bootstrap: filtro superior exclusivo por abas segmentadas (`<x-ui.tabs>`), grid responsivo de cards ricos com cabeçalho de mídia em pastel wash de 168px (ou imagem com véu escuro), overline multiorganização, barra de progresso de 10px e botões de ação contextual (*"Começar curso"*, *"Continuar"*, *"Baixar certificado"*, *"Ver o que você fez"*).
* **Roles Cobertas:** `role:aluno` (agrega matrículas de todas as organizações que o usuário pertence).
* **Referência de Design:** `spec/new_ds/DESIGN.md` §4.7, `spec/new_ds/Meus cursos - Anatomia dos cards.dc.html`.

---

## **2. Estrutura de UI & Componentes (`student.courses.index`)**

```text
student/courses/index.blade.php
├── x-layout.page-header (Kicker "Aprendizado", Título "Meus cursos", Subtítulo)
├── x-ui.tabs (Filtro por status: Em andamento [com badge], Concluídos, Todos)
├── div.ds-course-grid (auto-fill, minmax(300px, 1fr), gap 24px)
│   └── x-course.card [dusk="course-card-{id}"]
│       ├── Header de Mídia (168px · Pastel wash ou Capa com véu · Chip de status)
│       ├── Corpo (Overline Org · Título clamp 3 linhas · Resumo clamp 2 linhas)
│       │   └── x-ui.progress (Rótulo "Progresso" · Percentual 62% · Barra 10px)
│       └── Rodapé (Ação contextual [dusk="course-continue-{id}"] · Metadados de aulas/prazo)
└── x-ui.empty-state [dusk="no-enrollments"] (Estado vazio contextual por aba)
```

---

## **3. Anatomia do Card de Curso (`<x-course.card>`)**

### 3.1 Header de Mídia (168px)
- **Modo Padrão (Sem Capa):** Gradiente **Pastel Wash** do Design System (`linear-gradient(135deg, var(--blue-100), var(--mint-100))`).
- **Modo com Imagem:** `<img>` com `object-fit: cover` + véu gradiente inferior (`linear-gradient(to top, rgba(16, 26, 45, 0.55), transparent 62%)`).
- **Chip de Status (Fixado a 20px no canto inferior esquerdo):**
  - *Não iniciado:* `.ds-chip-neutral` *"Não iniciado"*.
  - *Em andamento:* `.ds-chip-info` *"Em andamento"*.
  - *Concluído:* `.ds-chip-success` *"Concluído"*.
  - *Expirado:* `.ds-chip-critical` com ícone `alert-circle` e texto *"Prazo encerrado"* (em azul-slate, sem vermelho).

### 3.2 Corpo & Progresso
- **Overline:** Nome da Organização em 1 linha com ellipsis (`.ds-overline.ds-truncate`).
- **Título:** `h3` 20px/700, máximo de 3 linhas balanceadas (`-webkit-line-clamp: 3`).
- **Resumo:** Parágrafo de apoio com máximo de 2 linhas (`-webkit-line-clamp: 2`).
- **Barra de Progresso:** Altura 10px fixa, pílula. Azul primário em andamento, menta (`--secondary`) em 100% de conclusão, mínimo de 2% para 0% (evita sensação de bug).

### 3.3 Rodapé & Ações Contextuais
- **Botão de Ação Primária (Bloco, Altura 44px+):**
  - `nao_iniciado` → Botão primário *"Começar curso"* (link primeira aula).
  - `em_andamento` → Botão primário *"Continuar"* + ícone `chevron-right` (link última aula).
  - `concluido` → Botão tonal/secundário *"Baixar certificado"* (download do PDF).
  - `expirado` → Botão tonal *"Ver o que você fez"* (modo leitura).
- **Metadados (`.ds-card-meta`):** Divisor superior de 1px, texto caption: `"{N} aulas · {N}h · Prazo: DD/MM/AAAA"` (ou *"Concluído em DD/MM/AAAA"*).

---

## **4. Seletores Dusk & Contrato E2E**

* `dusk="tab-em-andamento"`: Aba de cursos em andamento.
* `dusk="tab-concluidos"`: Aba de cursos concluídos.
* `dusk="tab-todos"`: Aba de todos os cursos.
* `dusk="course-card-{id}"`: Card individual do curso.
* `dusk="course-progress-{id}"`: Elemento da barra de progresso.
* `dusk="course-continue-{id}"`: Botão de ação contextual do curso.
* `dusk="no-enrollments"`: Container de estado vazio.

---

## **5. Checklist de Implementação & Testes**

- [ ] View `resources/views/student/courses/index.blade.php` refatorada no padrão Material Bootstrap.
- [ ] Componente `x-course.card` criado com header de 168px e ações dinâmicas.
- [ ] Abas segmentadas com alternância via parâmetros GET/AJAX.
- [ ] Teste Feature: `StudentCourseControllerTest.php` cobrindo agrupamento multi-org e filtros por status.
- [ ] Teste Dusk: `StudentCoursesCatalogUiTest.php` cobrindo tabs, renderização dos cards e navegação para a aula.
