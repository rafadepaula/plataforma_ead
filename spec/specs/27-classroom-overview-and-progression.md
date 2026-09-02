# **27. Sala de Aula: Visão da Trilha, Progresso do Aluno e Certificação (Material Bootstrap)**

---

## **1. Visão Geral & Mapeamento de Requisitos Multitenant**

* **Objetivo:** Refatorar a Sala de Aula do curso (`classroom/show.blade.php`) com o padrão Material Bootstrap: layout em grade 8/4 responsiva, grupos de módulos com linhas de aula em 4 zonas horizontais, ícones circulares de estado de 44px (menta com check para concluídas; azul com ícone de mídia para pendentes), card lateral de progresso com barra compacta, card permanente de status do certificado e atalho inteligente para a próxima aula não assistida.
* **Roles Cobertas:** `role:aluno` (e preview por `role:admin|gestor`). Protegido pelo middleware `student.enrolled`.
* **Referência de Design:** `DESIGN.md` §4.8, `_ds/Sala de aula - Anatomia.dc.html`.

---

## **2. Estrutura de UI & Grid Responsivo 8 / 4**

### 2.1 Cabeçalho da Página (`x-layout.page-header`)
- **Breadcrumb:** `Meus cursos` (link) → `{Nome do Curso}` (texto).
- **Kicker:** Overline `"Sala de aula"`.
- **Título (`h1`):** Título do curso verbatim.
- **Subtítulo:** `"Acompanhe os módulos e as lições deste curso e continue de onde parou."`
- **Ações:** Botão tonal com ícone de mensagem *"Fórum do curso"* (`route('forum.index', $course)`).

### 2.2 Coluna Principal (8 colunas) — Módulos e Lições
- **Grupo de Módulos (`x-classroom.module`):**
  - Card branco, raio 20px, elevação 1, padding 24px/28px.
  - Overline *"Módulo N"*, Título `h2` 20px/700, Caption com contagem *"N de M aulas concluídas"*.
  - Apenas lições **publicadas** são listadas.
- **Linha de Aula (72px de altura · `x-classroom.lesson-row`):**
  - Linha inteira é um link `<a>` envolto pelo `<li>`.
  - **4 Zonas Horizontais:**
    1. **Ícone de Estado (Círculo de 44px):**
       - *Concluída:* Círculo em `--mint-100`, ícone `check` em menta `--secondary` (`dusk="lesson-completed-{id}"`).
       - *Pendente:* Círculo em `--primary-container` (azul), glifo em `--on-primary-container` conforme tipo (`play` para vídeo, `file-text` para PDF, `clipboard` para prova, `book-open` para texto).
    2. **Título da Lição:** Peso 600. Se concluída, assume cor `--text-secondary`.
    3. **Chip de Tipo:** `.ds-chip-outline` *"Conteúdo"* ou `.ds-chip-primary` *"Prova"*.
    4. **Chevron:** Ícone Lucide `chevron-right` 18px em `--text-disabled`.

### 2.3 Coluna Lateral (4 colunas) — Progresso & Certificado
- **Regra de Ouro do DOM:** A coluna principal (`col-lg-8`) é declarada **PRIMEIRO** no DOM e a lateral (`col-lg-4`) em **SEGUNDO**. Em telas menores (<1024px), a lateral empilha naturalmente no fim.
1. **Card de Progresso do Curso (`ProgressCard`):**
   - Título `h3` *"Progresso do curso"*.
   - Rótulo esquerdo: *"N de M aulas concluídas"*.
   - Rótulo direito: `dusk="course-progress-label"` com valor percentual (`"62%"`).
   - Barra de progresso de 8px: azul em andamento, menta em 100%.
   - Valor alimentado exclusivamente pelo backend (`course_user.progress_percentage`).
2. **Card de Certificado (`CertificateCard`):**
   - *Emitido:* Quadrado menta de 44px com ícone `award`, texto com código do certificado e botão primário *"Baixar certificado"* (`dusk="download-certificate"`).
   - *Indisponível:* Superfície rebaixada com título *"Certificado ainda não disponível"* e texto explicativo em tom neutro (`dusk="certificate-unavailable"`), sem alertas vermelhos.
3. **Card de Próxima Aula (`NextLessonCard`):**
   - Aponta a primeira lição pendente na ordem sequencial. Se o curso estiver 100% concluído, o card é suprimido do DOM.

---

## **3. Seletores Dusk & Contrato E2E**

* `dusk="module-{id}"`: Card do módulo.
* `dusk="lesson-{id}"`: Elemento `<li>` da lição.
* `dusk="open-lesson-{id}"`: Link `<a>` de navegação da lição.
* `dusk="lesson-completed-{id}"`: Ícone de check menta de lição concluída.
* `dusk="no-modules"`: Estado vazio quando não há módulos publicados.
* `dusk="course-progress-label"`: Texto da porcentagem do curso.
* `dusk="course-progress-bar"`: Wrapper da barra de progresso.
* `dusk="download-certificate"`: Botão de download do certificado emitido.
* `dusk="certificate-unavailable"`: Bloco indicativo de certificado pendente.

---

## **4. Checklist de Implementação & Testes**

- [ ] View `resources/views/classroom/show.blade.php` refatorada no padrão Material Bootstrap.
- [ ] Componentes de módulo, linha de aula e cards laterais padronizados.
- [ ] Separação estrita dos seletores Dusk (`lesson-{id}` no `<li>` e `open-lesson-{id}` no `<a>`).
- [ ] Teste Feature: `MultiOrgStudentClassroomTest.php` cobrindo isolamento de matrícula e cálculo de progresso.
- [ ] Teste Dusk: `ClassroomOverviewDuskTest.php` cobrindo visualização da trilha, navegação e download de certificados.
