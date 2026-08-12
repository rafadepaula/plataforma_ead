# 00 — Índice da Migração para Bootstrap 5.3

> **Status**: 🟡 Planejamento em andamento. Documentos base 01–06 completos.
> **Início**: 2026-08-12.
> **Objetivo**: Eliminar 634 estilos inline, adotar Bootstrap 5.3 (CSS completo + JS), remover código redundante.

---

## 1. Documentos Técnicos

| # | Arquivo | Linhas | Status | Descrição |
|---|---|---|---:|---|
| 01 | `01-diagnostico-e-mapa-mental.md` | 319 | ✅ | Estado atual medido: 78 views, 634 inline styles, Alpine sem Alpine, 316 dusk selectors. Achados críticos (C1–C13). |
| 02 | `02-component-inventory.md` | 1.803 | ✅ | Inventário de componentes Blade (16 existentes, 34 alvo). Tabela DE↔PARA para Bootstrap. Overrides SCSS completos. |
| 03 | `03-screen-inventory.md` | 1.488 | ✅ | Inventário das 60 telas, agrupadas em 6 waves (A–F). Composição Bootstrap alvo por tela. Seletores dusk por arquivo. |
| 04 | `04-js-and-build-pipeline.md` | 1.283 | ✅ | Camada JS (14 módulos) + pipeline SCSS/Vite alvo. DE↔PARA para Bootstrap JS. Conteúdo exato de app.scss, vite.config.js, package.json. |
| 05 | `05-bootstrap-reference.md` | 4.379 | ✅ | Referência canônica Bootstrap 5.3 (crawler das docs). Em produção. |
| 06 | `06-skills-and-agents.md` | 1.709 | ✅ | Design da harness agêntica: tríade bootstrap-architecture/conventions/maintenance (conteúdo completo de arquivo) + 5 subagentes. |
| 07 | `07-migration-plan.md` | 1.321 | ✅ | Plano de migração faseado (Fases 0–7). Em produção. |

**Totais parciais**: 12.506 linhas de especificação técnica.

---

## 2. Fases da Migração (resumo de 07)

| Fase | Onda | Escopo | Paralelizabilidade | Risco | Entrada | Saída |
|---|---|---|---|---|---|---|
| **0** | Foundation | Pipeline de build, SCSS, fontes, paginator | Serial (7 tarefas) | Baixo | `main` limpo | Build verde, SCSS funcionando |
| **1** | A | Layouts (2) + componentes existentes (16) + 2 avulsos | Alta (18 arquivos, conflito só em app.js) | Médio | Build ok | Layouts master migrados, componentes reescritos |
| **2** | B | Partials compartilhados (15+) + formulários reutilizáveis | Alta | Baixo | Componentes prontos | Partials migrados |
| **3** | C | Telas index only-read (~15) | Alta (nada compartilha arquivos) | Baixo | Partials ok | Listagem de usuários, cursos, etc. |
| **4** | D | Telas CRUD create/edit (~15) | Média (conflitos em _form) | Médio | Index ok | Formulários migrados |
| **5** | E | Telas JS-heavy: classroom, quizzes, forum | Baixa (JS compartilha app.js, alto risco) | **Alto** | CRUD ok | Player, quiz builder, forum funcionando |
| **6** | F | Telas públicas/guest: landing, auth, convite | Alta | Baixo | E ok | Área pública migrada |
| **7** | Polish | 18 componentes novos + verificação final | Alta | Médio | F ok | Biblioteca completa, Dusk verde |

**Estimativa de paralelização máxima**: ~18 tarefas simultâneas nas Fases 1, 2 e 3 (via workflow).

---

## 3. Catálogo de Componentes Alvo (resumo de 02)

### 3.1 Componentes a reescrever (existentes)

`x-ui.alert`, `x-ui.badge`, `x-ui.button`, `x-ui.card`, `x-ui.icon`, `x-ui.input`, `x-ui.modal`, `x-ui.select`, `x-ui.stat-card`, `x-ui.table`, `x-layout.alerts`, `x-layout.footer`, `x-layout.sidebar`, `x-layout.topbar`, `x-help-button`, `x-notifications-bell`.

### 3.2 Componentes novos a criar (18)

| Componente | Bootstrap interno | Substitui |
|---|---|---|
| `x-layout.page-header` | d-flex + h4 | Bloco título de 19 telas |
| `x-layout.section-header` | d-flex + h5 | Subseções |
| `x-layout.public` | container | HTML duplicado de landing/public |
| `x-layout.print` | nenhum (dompdf-safe) | `certificates/pdf` |
| `x-ui.data-table` | table + table-responsive | Tabelas de 12 telas |
| `x-ui.empty-state` | text-center | @empty |
| `x-ui.pagination` | paginator + d-flex | 8 telas |
| `x-ui.filter-bar` | row + form | Filtros |
| `x-ui.confirm-modal` | modal + btn-danger | Diálogos de confirmação |
| `x-ui.field` | form-label + form-control | Wrapper de input |
| `x-ui.file-upload` | form-control | Upload |
| `x-ui.list-group` / `x-ui.list-group-item` | list-group | Itens de quiz, forum |
| `x-ui.offcanvas` | offcanvas | Sidebar mobile |
| `x-ui.toast` | toast | Notificações |
| `x-ui.tabs` | nav-tabs | Abas |
| `x-ui.progress` | progress | Progresso |
| `x-ui.breadcrumb` | breadcrumb | Migalha |
| `x-ui.avatar` | rounded-circle + img | Avatares |
| `x-ui.spinner` | spinner-border | Loading |
| `x-ui.checkbox` / `x-ui.radio` | form-check | Checks |
| `x-ui.divider` | hr + text | Divisores |
| `x-ui.dropdown` | dropdown | Dropdowns |

---

## 4. Contrato Dusk (restrição dura)

**316 seletores `dusk="..."`** espalhados por resources/views/. Requisito: sobreviver **verbatim**. Nenhum renomear, nenhum remover.

Documentação por arquivo:
- `03-screen-inventory.md` § 1–18: dusk selectors por tela (todos listados).
- `02-component-inventory.md` § 7: 76 dusk selectors na camada de componente.

Testes afetados: 25 arquivos em `tests/Browser/`. Verificação por fase em `07-migration-plan.md`.

---

## 5. Skills e Agentes Especializados (resumo de 06)

### 5.1 Tríade de skills Bootstrap (conteúdo completo de arquivo em 06)

| Skill | Responsabilidade | Quando usar |
|---|---|---|
| `bootstrap-architecture` | Modelo em camadas, porquê Bootstrap SCSS é a fonte única, decisão record de substituições | Antes de começar a migração |
| `bootstrap-conventions` | Padrões de código: `$attributes->merge()`, nomeações, proibições, padrão Laravel-validação | Durante toda migração |
| `bootstrap-maintenance` | Debug: gotcha do public/build/, falhas comuns, checklist de regressão | Verificação por fase |

### 5.2 Subagentes (system prompt completo em 06)

| Agente | Função | Entrada | Saída |
|---|---|---|---|
| `bootstrap-migrator` | Migra 1 tela ou componente | Caminho do arquivo | Diff receipt |
| `bootstrap-component-author` | Cria novo `<x-ui.*>` | Especificação do componente | Arquivo Blade + uso |
| `bootstrap-design-reviewer` | Review diff de migração | Diff | Lista de findings (1/linha) |
| `bootstrap-js-refactorer` | Refatora módulo JS | Nome do módulo | Código refatorado |
| `bootstrap-visual-verifier` | (opcional) screenshot before/after | Tela | Comparação visual |

---

## 6. Mapa Mental (resumo de 01)

```
[Build Pipeline: Vite + SCSS]
         ↓
[layouts/ app + guest] ← inline styles →
         ↓
[components/layout topbar + sidebar + footer + alerts] ← inline styles →
         ↓
[components/ui alert/badge/button/card/modal/etc] ← inline styles →
         ↓
[60 telas] ← 500 inline styles →
         ↓
[resources/js/ 14 módulos] ← ModalManager/NotificationService/NotificationBell reimplementam Bootstrap →
         ↓
[316 dusk selectors] ← contrato imutável →
```

**Princípios de migração:**
1. Bootstrap SCSS = fonte única de verdade de design.
2. Zero `style=` inline no estado final.
3. Zero JS artesanal onde Bootstrap já entrega.
4. Componentização máxima (2+ ocorrências = componente).
5. `dusk=` imutável.
6. Toda tarefa termina com build + Dusk filtrado.

---

## 7. Riscos Principais (resumo dos 13 achados críticos em 01)

| # | Risco | Impacto |
|---|---|---|
| **C1** | 634 inline styles vencem qualquer utility | Migração deve **deletar** inline |
| **C2** | Skills `frontend-*` documentam classes inexistentes | Documentação imprecisa |
| **C3** | Bootstrap JS ausente ⇒ `data-bs-*` inerte | Ordem de fases importa |
| **C4** | `BUG-004` (alert dismiss inerte) | Resolvido de graça por `bootstrap.Alert` |
| **C5** | `certificates/pdf.blade.php` usa dompdf | Exceção: não recebe Bootstrap |
| **C6** | Tailwind + bunny font são peso morto | Remover na Fase 0 |
| **C7** | Mandato Modernist (radius 0, Archivo, etc.) | Codificar como override SCSS |
| **C8** | `app.js` é compartilhado | Serializa refatorações JS |
| **C9** | 3 módulos JS reimplementam Modal/Toast/Dropdown | ~14 KB deletável |
| **C10** | Diretivas Alpine sem Alpine instalado | Markup reativo inerte → BUG-004 + menu mobile morto |
| **C11** | Arquivos `.woff2` de Archivo têm 0 bytes | Tipografia nunca funcionou |
| **C12** | Paginação sem estilo em 9 telas | Tema Tailwind sem CSS carregado |
| **C13** | Drawer mobile do sidebar nunca abre | `sidebarOpen` não existe |

---

## 8. Como Usar Este Plano

### 8.1 Para a equipe de execução
1. Leia **`01-diagnostico`** para entender o estado atual.
2. Leia **`02-component-inventory`** e **``03-screen-inventory`** para o que existe e o que almejar.
3. Leia **`04-js-and-build-pipeline`** para o plano de build.
4. Consulte **`05-bootstrap-reference`** durante a implementação (API, classes, eventos).
5. Execute por fases conforme **`07-migration-plan`**.
6. Use **`bootstrap-conventions`** como guia de referência rápido.
7. Em caso de dúvida de debug, consulte **`bootstrap-maintenance`**.

### 8.2 Para o agente orquestrador (workflow)
O plano em `07` define um workflow natural:
- **Fase 0**: 7 tarefas seriais → 1 agente sequencial.
- **Fases 1, 2, 3**: 30+ arquivos disjuntos → **paralelização máxima via workflow** (fan-out de `bootstrap-migrator`).
- **Fase 5**: alto risco, módulos JS compartilhados → serialização com verificação Dusk rigorosa.

### 8.3 Para verificação de qualidade
- Cada fase em `07` define critérios de entrada/saída.
- `bootstrap-design-reviewer` revisa diffs.
- `bootstrap-maintenance` lista regressões conhecidas.

---

## 9. Checklist de Pré-Migração

- [ ] Backup de `public/build/` (para rollback instantâneo)
- [ ] Branch `bootstrap-migration` criada a partir de `main`
- [ ] `vendor/bin/sail artisan dusk --compact` executado e passando (baseline)
- [ ] Dependências Node atualizadas (`vendor/bin/sail npm install`)
- [ ] Arquivos de fonte Archivo obtidos (não são 0 bytes)
- [ ] Skills `bootstrap-*` criadas em `.agents/skills/` e `.claude/skills/`
- [ ] Subagentes criados em `.agents/agents/` e `.claude/agents/`

---

## 10. Histórico de Revisões

| Data | Versão | Alteração |
|---|---|---|
| 2026-08-12 | 0.1 | Criação. Documentos 01–06 completos. |
| 2026-08-12 | 0.2 | Índice criado. 05 e 07 em produção. |

---

**Próximo passo**: Aguardar conclusão de `05-bootstrap-reference.md` e `07-migration-plan.md`.
