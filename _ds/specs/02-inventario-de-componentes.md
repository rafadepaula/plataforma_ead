# 02 — Inventário de componentes (DE → PARA)

30 componentes. Cada linha é uma unidade de trabalho migrável de forma
independente — comportamento e props preservados, casca visual nova. Tokens
conforme o `DESIGN.md` da raiz (fonte única de verdade).

| Repositório (Blade) | Novo componente | Notas de implementação |
|---|---|---|
| `x-ui.button` | **Button** | Variantes primary (`#2563EB`, hover `#1D4ED8`) / tonal (`--primary-surface` + `--primary`) / secondary (outlined `--border-subtle`) / ghost / success (`--success`) / danger (`--error`). Tamanhos sm 40px, base 48px, lg 60px. Raio `--radius-md` (6px), transição 200ms. Sempre ícone; sem fill de fundo exige borda obrigatória (regra já aplicada nos decks). Label `label-sm` 14px/500. |
| `x-ui.badge` | **Badge** | Chip `--radius-full`, sentence case, sempre par container / on-container. accent → primary, accent-2 → `--error`. |
| `x-ui.icon` | **Icon** | Mesmos paths Lucide de icon.blade.php + NavigationRegistry. Nome desconhecido degrada para info, nunca desaparece. |
| `x-ui.card` | **Card** | elevated / outlined / interactive. Faixa de mídia 168px (`--primary-surface`), caption, `headline-lg`, corpo, meta ancorada embaixo, rodapé em `--surface-container-low`. Raio `--radius-xl`, borda `--border-subtle`, `--shadow-card`. |
| `x-ui.stat-card` | **StatCard** | Ícone em círculo `--primary-surface` 52px, métrica `headline-2xl` 24/700, delta em chip `--success`. Usado no dashboard (4 cards). |
| `x-ui.table / x-ui.data-table` | **Table / DataTable (alias)** | Toolbar opcional, hover de linha com `--primary-surface`, ações agrupadas com o mesmo padrão de botão (ícone + borda quando sem fill). |
| `x-ui.pagination` | **Pagination** | Reescrito autônomo, resolve C12 (ausência de CSS em 9 telas). |
| `x-ui.empty-state` | **EmptyState** | Ícone 32px em círculo `--primary-surface`, borda tracejada 1,5px `--border-subtle`, sempre com próximo passo em texto + botão. |
| `x-ui.progress` | **Progress** | Pílula `--radius-full`. Track `--bg-interface`. Fill `--success` para conclusão de curso/aula, `#2563EB` para curso em andamento. |
| `x-ui.alert` | **Alert** | Tonal, sempre par container / on-container: primary (`--primary-surface`/`--primary`), success (`--success`), warning (`--warning`), error (`--error-container`/`--on-error-container`). role="alert". |
| `x-ui.modal` | **Modal** | Raio `--radius-xl`, sombra reforçada sobre `--surface-container-lowest`, entra subindo 16px em 200ms, scrim sobre `#0F172A`. |
| `x-ui.confirm-modal` | **ConfirmModal** | Texto calmo e explícito ("Esta ação não poderá ser desfeita."), botão em `--error` com ícone. |
| `x-ui.delete-button` | **DeleteButton** | Abre ConfirmModal sempre — nunca remove em um clique. |
| `x-ui.input / textarea / select` | **Input / Textarea / Select** | Outlined `--border-subtle`, raio `--radius-xl`, label sempre visível e associada via ARIA, foco com anel de 2px `#2563EB` offset 2px. |
| `x-ui.checkbox` | **Checkbox** | 22px, alvo de toque 48px. |
| `x-ui.field-stack / form-actions / filter-bar` | **FieldStack / FormActions / FilterBar** | Sem mudança de comportamento, só de casca visual. |
| `x-layout.topbar` | **Topbar** | Marca, busca, badge da organização ativa, ajuda contextual, sino com contador, usuário com papel. Sobre `--surface-container-lowest`. |
| `x-layout.sidebar` | **Sidebar** | Drawer claro 280px sobre `--surface-container-lowest`, 3 seções do NavigationRegistry, item ativo em `--primary-surface` com texto `--primary`, badge de pendência em `--success`, alvo de item 48px. |
| `x-layout.page-header` | **PageHeader** | Breadcrumb + caption + `headline-2xl` + apoio em `--on-surface-variant` — presente em toda tela. |
| `x-layout.footer` | **Footer** | Sem mudança de conteúdo. |
| `x-layout.public` | **GuestPanel + shell público** | Painel de marca em `--primary-surface` (`#EFF6FF`). |
| `x-help-button` | **HelpButton** | Fallback: "Estamos preparando o conteúdo de ajuda desta tela." |
| `x-notifications-bell` | **NotificationsBell** | Dropdown, item não lido com tint `--primary-surface`. |
| `x-layout.alerts` | **(sem componente próprio)** | Shell de flash de sessão; aparência vive em Alert. |
| `certificates/pdf.blade.php` | **exceção — não migra** | dompdf-safe, mantém CSS atual. |

## Adições intencionais (não existem no Blade atual)

| Componente | Onde entra |
|---|---|
| **Fab** | Ação de criação principal flutuante — usada no fórum ("Novo tópico"). Primary, `--radius-full`. |
| **Chip** | Filtro rápido de escolha, substitui select de 2–3 opções (ex.: período no dashboard). `--radius-full`, `aria-pressed`. |
| **Switch** | Configuração de efeito imediato — "Publicado" na lição, consentimento no convite. `--radius-full`. |
| **Tabs** | Em andamento / Concluídos / Todos em Meus cursos. `aria-selected`, item ativo com tinta primária. |
| **Avatar** | Iniciais, `--radius-full` — não há fotografia no produto. |

## Regra transversal de botão
Todo botão de ação sempre tem ícone. Quando a variante não tem fill de fundo
(ghost/outline), a borda é obrigatória — sem exceção, inclusive em ações
agrupadas de tabela (Abrir / Editar / Remover).

## Critérios de aceite (por componente)
- Props e eventos do Blade original preservados (ver assinatura na view de
  origem antes de tocar).
- `dusk=` no mesmo nó semântico (ver `18-contrato-dusk-e-testes.md`).
- Sem `style=` inline remanescente.
- Estados de foco/hover/disabled conforme `DESIGN.md` na raiz §5.
