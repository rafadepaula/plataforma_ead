# **21. Dashboard Gerencial, Métricas e Exportação CSV (Material Bootstrap)**

---

## **1. Visão Geral & Mapeamento de Requisitos Multitenant**

* **Objetivo:** Refatorar a tela de Dashboard do Gestor/Admin (`dashboard/index.blade.php`) no padrão Material Bootstrap: 4 StatCards com deltas comparativos, grid 2fr/1fr, tabela de matrículas recentes com barra de progresso compacta de 8px, card de atenção a pendências, ranking de cursos mais concluídos e exportação CSV em streaming.
* **Roles Cobertas:** `role:admin`, `role:gestor`.
* **Referência de Design:** `spec/new_ds/DESIGN.md` §4.2, `spec/new_ds/Dashboard - Anatomia.dc.html`.

---

## **2. Estrutura de UI & Layout (Grid 2fr / 1fr)**

### 2.1 Cabeçalho e Filtro de Período
- **Kicker:** Overline azul `"Painel"`.
- **Título (`h1`):** Saudação contextual horária: *"Bom dia / Boa tarde / Boa noite, {Primeiro Nome}"*.
- **Subtítulo:** *"Um panorama da sua organização nos últimos 30 dias."* (ou plataforma em visão Admin global).
- **Ações:**
  - Botão Tonal com ícone `upload`: *"Exportar matrículas (CSV)"* (`dusk="export-enrollments-csv"`).
  - Botão Tonal com ícone `upload`: *"Exportar certificados (CSV)"* (`dusk="export-certificates-csv"`).
  - Botão Primário (se autorizado): *"Novo curso"* com ícone `plus`.
- **Filtro de Período:** Chips segmentados (`.ds-chip.ds-chip-filter` com `aria-pressed`): *"Últimos 7 dias"*, *"Últimos 30 dias"*, *"Este ano"*.

### 2.2 Faixa de 4 StatCards (`.ds-stat-grid`)
Quatro cards brancos com raio 20px, elevação 1, padding 28px:
1. **Alunos Ativos:** Container `--primary-container`, ícone `users` (24px), valor métrico (40px/800), delta em chip menta (`+4,2%`), caption *"vs. anterior"*.
2. **Certificados Emitidos:** Container `--secondary-container`, ícone `award` (24px), valor métrico, delta menta (`+12%`), caption *"no período"*.
3. **Taxa de Conclusão:** Container `--tertiary-container`, ícone `check` (24px), valor percentual (`63%`), caption *"média dos cursos"*.
4. **Cursos Publicados:** Container `--surface-alt`, ícone `book-open` (24px), total de cursos, caption *"2 em rascunho"*.

### 2.3 Coluna Principal (2fr) — Matrículas Recentes (`.ds-table-wrap`)
- Toolbar com título `h3` *"Matrículas recentes"* e caption *"Atualizado recentemente"*.
- Tabela com 4 colunas:
  1. `Aluno`: Avatar 36px com iniciais + Nome (16px/600) + e-mail em caption.
  2. `Curso`: Título do curso (cor `--text-secondary`).
  3. `Progresso`: Barra `.ds-progress` de 8px (compacta), transição 320ms, azul primário em andamento e menta em 100%.
  4. `Status`: Chips em sentence-case (*"Em andamento"* em info, *"Concluída"* em menta, *"Não iniciada"* em neutro).
- Estado Vazio: `.ds-empty-state` tracejado com ícone pastel e CTA tonal *"Convidar alunos"*.

### 2.4 Coluna Lateral (1fr) — Atenção & Ranking
1. **Card "Precisa da sua atenção" (`.ds-card`):**
   - Links diretos com contadores em chip plain para:
     - *Redações aguardando correção* (ícone `file-text`, chip info).
     - *Denúncias no fórum* (ícone `shield`, chip info).
     - *Certificados prontos* (ícone `award`, chip menta).
   - Se todas as contagens forem 0: exibe estado calmo *"Nada aguardando você"*.
2. **Card "Cursos mais concluídos" (`.ds-card.ds-card-outlined`):**
   - Ranking dos 3 principais cursos com barra horizontal de progresso em menta (`--secondary`) e percentual formatado.

---

## **3. Fluxos AJAX & Exportação CSV**

- **Filtro de Período via AJAX:** Alteração nos chips de período recarrega os dados de StatCards e tabela via requisição assíncrona, atualizando a DOM sem reload total.
- **Exportação CSV em Streaming:** Ações de exportação disparam download direto com consumo $O(1)$ de RAM sem travar a interface do gestor.

---

## **4. Seletores Dusk & Contrato E2E**

* `dusk="admin-dashboard"`: Contêiner raiz da página de dashboard.
* `dusk="stat-active-students"`: StatCard de alunos ativos.
* `dusk="stat-certificates-issued"`: StatCard de certificados emitidos.
* `dusk="stat-completion-rate"`: StatCard de taxa de conclusão.
* `dusk="stat-courses-count"`: StatCard de cursos publicados.
* `dusk="recent-enrollments-table"`: Tabela de matrículas recentes.
* `dusk="enrollment-row-{id}"`: Linha individual de matrícula.
* `dusk="export-enrollments-csv"`: Botão de exportação CSV de matrículas.
* `dusk="export-certificates-csv"`: Botão de exportação CSV de certificados.

---

## **5. Checklist de Implementação & Testes**

- [ ] View `resources/views/dashboard/index.blade.php` refatorada no padrão Material Bootstrap.
- [ ] StatCards com ícones pastéis, métricas e deltas formatados em pt-BR.
- [ ] Tabela de matrículas com barra de progresso 8px e status em chips.
- [ ] Card de pendências com contadores reativos.
- [ ] Teste Feature: `DashboardControllerTest.php` validando isolamento multitenant de métricas.
- [ ] Teste Dusk: `DashboardDuskTest.php` cobrindo renderização, filtros e exportações.
