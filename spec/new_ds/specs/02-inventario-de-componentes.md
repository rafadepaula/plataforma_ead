# 02 — Inventário de componentes (DE → PARA)

30 componentes. Cada linha é uma unidade de trabalho migrável de forma
independente — comportamento e props preservados, casca visual nova.

| Repositório (Blade) | Novo componente | Notas de implementação |
|---|---|---|
| `x-ui.button` | **Button** | Variantes primary / tonal / secondary (outlined) / ghost / success / danger. Tamanhos sm 40px, base 48px, lg 60px. Sempre ícone; sem fill de fundo exige borda obrigatória (regra já aplicada nos decks). Label 14px/700. |
| `x-ui.badge` | **Badge** | Chip pílula, sentence case. accent → primary container, accent-2 → critical container. |
| `x-ui.icon` | **Icon** | Mesmos paths Lucide de icon.blade.php + NavigationRegistry. Nome desconhecido degrada para info, nunca desaparece. |
| `x-ui.card` | **Card** | elevated / outlined / interactive. Faixa de mídia 168px (pastel wash), overline, h4, corpo, meta ancorada embaixo, rodapé em superfície afundada. |
| `x-ui.stat-card` | **StatCard** | Ícone em container pastel 52px, métrica 40px/800, delta em chip menta. Usado no dashboard (4 cards). |
| `x-ui.table / x-ui.data-table` | **Table / DataTable (alias)** | Toolbar opcional, hover com tint azul 6%, ações agrupadas com o mesmo padrão de botão (ícone + borda quando sem fill). |
| `x-ui.pagination` | **Pagination** | Reescrito autônomo, resolve C12 (ausência de CSS em 9 telas). |
| `x-ui.empty-state` | **EmptyState** | Ícone 32px em círculo pastel, borda tracejada 1,5px, sempre com próximo passo em texto + botão. |
| `x-ui.progress` | **Progress** | 10px, pílula. Variante menta para conclusão de curso/aula. |
| `x-ui.alert` | **Alert** | Tonal: primary / success / info / attention / danger. role="alert". |
| `x-ui.modal` | **Modal** | Raio 28px, elev-5, entra subindo 16px com leve escala, scrim 42%. |
| `x-ui.confirm-modal` | **ConfirmModal** | Texto calmo e explícito ("Esta ação não poderá ser desfeita."), botão em --critical. |
| `x-ui.delete-button` | **DeleteButton** | Abre ConfirmModal sempre — nunca remove em um clique. |
| `x-ui.input / textarea / select` | **Input / Textarea / Select** | Outlined, rótulo flutuante, altura 52px, anel de foco 4px a 28%. |
| `x-ui.checkbox` | **Checkbox** | 22px, alvo de toque 48px. |
| `x-ui.field-stack / form-actions / filter-bar` | **FieldStack / FormActions / FilterBar** | Sem mudança de comportamento, só de casca visual. |
| `x-layout.topbar` | **Topbar** | 76px: marca, busca em pílula até 420px, badge da organização ativa, ajuda, sino com contador, usuário com papel. |
| `x-layout.sidebar` | **Sidebar** | Drawer claro 280px, 3 seções do NavigationRegistry, item ativo em pílula --nav-active-bg, badge de pendência em menta, item 52px. |
| `x-layout.page-header` | **PageHeader** | Breadcrumb + overline azul + h1 + lead — presente em toda tela. |
| `x-layout.footer` | **Footer** | Sem mudança de conteúdo. |
| `x-layout.public` | **GuestPanel + shell público** | Painel de marca em --blue-100 46%. |
| `x-help-button` | **HelpButton** | Fallback: "Estamos preparando o conteúdo de ajuda desta tela." |
| `x-notifications-bell` | **NotificationsBell** | Dropdown 380px, item não lido com tint --blue-50. |
| `x-layout.alerts` | **(sem componente próprio)** | Shell de flash de sessão; aparência vive em Alert. |
| `certificates/pdf.blade.php` | **exceção — não migra** | dompdf-safe, mantém CSS atual. |

## Adições intencionais (não existem no Blade atual)

| Componente | Onde entra |
|---|---|
| **Fab** | Ação de criação principal flutuante — usada no fórum ("Novo tópico"). |
| **Chip** | Filtro rápido de escolha, substitui select de 2–3 opções (ex.: período no dashboard). |
| **Switch** | Configuração de efeito imediato — "Publicado" na lição, consentimento no convite. |
| **Tabs** | Pílulas segmentadas — Em andamento / Concluídos / Todos em Meus cursos. |
| **Avatar** | Iniciais — não há fotografia no produto. |

## Regra transversal de botão
Todo botão de ação sempre tem ícone. Quando a variante não tem fill de fundo
(ghost/outline), a borda é obrigatória — sem exceção, inclusive em ações
agrupadas de tabela (Abrir / Editar / Remover).

## Critérios de aceite (por componente)
- Props e eventos do Blade original preservados (ver assinatura na view de
  origem antes de tocar).
- `dusk=` no mesmo nó semântico (ver `18-contrato-dusk-e-testes.md`).
- Sem `style=` inline remanescente.
- Estados de foco/hover/disabled conforme DESIGN.md §6.
