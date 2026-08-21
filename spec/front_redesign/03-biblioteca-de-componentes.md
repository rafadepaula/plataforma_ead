# 03 — Biblioteca de componentes

30 componentes existentes reescritos + 5 adições. Cada linha é uma unidade de
trabalho independente: **props e comportamento preservados, casca visual nova.**

## Regra transversal de botão

Todo botão de ação tem ícone. Quando a variante não tem preenchimento de fundo
(`ghost`, `outline`), **a borda é obrigatória** — sem exceção, inclusive nas
ações agrupadas de linha de tabela (Abrir / Editar / Remover).

## 1. Ações

### `x-ui.button`
Props atuais preservadas: `variant`, `size`, `block`, `icon`, `type`, `href`,
`disabled`.

Mapa de variantes alvo (o `match` interno muda; a API não):

| `variant` | Classe alvo | Aparência |
|---|---|---|
| `primary` | `btn btn-primary` | fill azul, `--elev-primary`, sobe 1px no hover |
| `tonal` *(novo valor)* | `btn btn-tonal` | `--primary-container` + `--on-primary-container`, sem sombra |
| `secondary` | `btn btn-outline-secondary` | outlined, borda `--border-color` |
| `ghost` | `btn btn-ghost` | sem fill **com borda obrigatória** |
| `success` *(novo valor)* | `btn btn-success` | menta, só para conclusão |
| `danger` | `btn btn-danger` | `--critical` + ícone `trash`/`shield` |

Tamanhos: `sm` 40px (**só dentro de linha de tabela**), `md` 48px, `lg` 60px.
Raio pílula, label 14px/700, tracking `.01em`.

`ghost` hoje resolve para `btn-link text-body text-decoration-none` — sem
borda. Isso viola a regra transversal e precisa mudar.

### `x-ui.badge`
Props: `variant` (`accent`, `accent-2`, `neutral`, `outline`).
Chip pílula, **sentence case** (hoje é caixa-alta — muda).
`accent` → par `--primary-container` / `--on-primary-container`;
`accent-2` → par `--critical-container` / `--on-critical-container`;
`neutral` → par `--attention-container` / `--on-attention-container`;
`outline` → borda `--border-color`, texto `--text-secondary`.
Hoje `accent-2` resolve para `text-bg-danger` (vermelho do Bootstrap) — proibido.

### `x-ui.icon`
Props: `name`, `size`. Mesmos paths Lucide. Nome desconhecido degrada para
`info`. Único componente que emite SVG.

### `x-ui.delete-button`
Nunca remove em um clique: sempre abre `x-ui.confirm-modal`.
Variante `danger`, ícone `trash`, palavra explícita.

### Adições
- **`x-ui.chip`** — filtro rápido com `aria-pressed`; substitui select de 2–3 opções.
- **`x-ui.avatar`** — iniciais em círculo pastel; 32 / 44 / 64px. Não há fotografia no produto.
- **`x-ui.fab`** — ação de criação flutuante, 56px, `--elev-3` + `--elev-primary`.

## 2. Formulários

### `x-ui.input` / `x-ui.textarea` / `x-ui.select`
Outlined, **rótulo flutuante** (`.form-floating` do Bootstrap), altura 52px,
raio 14px, anel de foco de 4px a 28%.
Validação: `.is-invalid` no controle + `.invalid-feedback` abaixo **com ícone** —
nunca só a borda. Mensagem em `--critical`.

### `x-ui.checkbox`
Caixa de 22px dentro de alvo de toque de 48px.

### `x-ui.field-stack` / `x-ui.form-actions` / `x-ui.filter-bar`
Sem mudança de comportamento. `field-stack` usa `--gap-stack` 20px;
`form-actions` ancora à direita com `--gap-inline`; `filter-bar` vira card
`outlined` com raio 20px.

### Adição
- **`x-ui.switch`** — `.form-switch` do Bootstrap, efeito imediato. Entra em
  "Publicado" na lição e no consentimento do convite.

## 3. Dados

### `x-ui.card`
Variantes `elevated` (`--elev-2`) / `outlined` (borda, sem sombra) /
`interactive` (sobe 3px, vai a `--elev-3` no hover).
Raio 20px, padding 28px, gap interno 12px. Pode ter faixa de mídia de 168px em
*pastel wash*, overline, título h4, corpo, linha de meta ancorada acima de um
divisor, e rodapé em `--surface-sunken`.

### `x-ui.stat-card`
Props atuais preservadas: `kicker`, `value`, `delta`, `deltaVariant`.
Alvo: ícone em container pastel de 52px (raio 14px) + overline + métrica
**40px/800** + delta em chip menta. Hoje é `bg-body-secondary` sem ícone —
adicionar prop `icon`, mantendo default nulo para não quebrar chamadas.

### `x-ui.table` / `x-ui.data-table`
`data-table` é alias de `table`, mantido por continuidade.
Toolbar opcional, `.table-responsive` obrigatório, hover com tint azul 6%,
célula 18×20px, cabeçalho em overline, divisores `--divider`.

### `x-ui.progress`
Trilha de 10px, pílula. Variante menta para conclusão.
`role="progressbar"` com `aria-valuenow` / `aria-valuemin` / `aria-valuemax`.

### `x-ui.empty-state`
Ícone de 32px em círculo pastel, borda tracejada 1,5px, raio 20px.
**Sempre com próximo passo**: frase + botão.

### `x-ui.pagination`
Pílulas, estado ativo em `--primary`, `aria-current="page"`. Autônomo — não
depende de tema externo do paginador.

### Adição
- **`x-ui.tabs`** — pílulas segmentadas com `aria-selected`; Em andamento /
  Concluídos / Todos em Meus cursos.

## 4. Feedback

### `x-ui.alert`
Tonal, `role="alert"`, raio 14px, ícone à esquerda em 20px.
Variantes: `primary` / `success` / `info` / `attention` / `danger` — sempre pelo
par container / on-container.

### `x-ui.modal`
Raio 28px, `--elev-5`, entra subindo 16px com leve escala em 320ms, scrim 42%.
Dirigido por `bootstrap.Modal` — zero JS artesanal.

### `x-ui.confirm-modal`
Texto calmo e explícito: "Esta ação não poderá ser desfeita. Deseja continuar?"
Botão de confirmação em `--critical` com ícone.

## 5. Layout e app

| Componente | Alvo |
|---|---|
| `x-layout.topbar` | 76px: brand mark, busca em pílula até 420px, badge da organização ativa, `x-help-button`, `x-notifications-bell`, usuário com papel |
| `x-layout.sidebar` | Drawer **claro** de 280px, seções do `NavigationRegistry`, item ativo em pílula `--nav-active-bg`, badge de pendência menta, item 52px |
| `x-layout.page-header` | Breadcrumb + overline azul + h1 + lead — presente em **toda** tela |
| `x-layout.footer` | Organização ativa; conteúdo inalterado |
| `x-layout.public` | Shell público + painel de marca em `--blue-100` |
| `x-layout.alerts` | Shell de flash de sessão; sem superfície própria — aparência vive em `x-ui.alert` |
| `x-help-button` | Fallback: "Estamos preparando o conteúdo de ajuda desta tela." |
| `x-notifications-bell` | Dropdown de 380px, item não lido com tint `--blue-50`, contador em menta |

### Adição
- **`x-layout.guest-panel`** — painel de marca de 46% usado por login, convite
  e recuperação de senha.

## Exceção

`resources/views/certificates/pdf.blade.php` **não** recebe a camada nova:
dompdf não suporta custom properties, flexbox ou grid. Mantém CSS próprio,
inline, em tabela. Isolar num layout `x-layout.print` para deixar a exceção
explícita e impedir que alguém "corrija" o arquivo.

## Critérios de aceite por componente

- Assinatura `@props` preservada; valores novos são **adicionados**, nunca renomeados.
- `$attributes->merge()` mantido — a convenção de componente anônimo do projeto.
- `dusk=` no mesmo nó semântico de antes (ver `14-contrato-dusk-e-testes.md`).
- Zero `style=` inline.
- Estados de foco / hover / press / disabled conforme `13-acessibilidade-e-estados.md`.
- Nenhuma classe CSS inventada onde existe utility do Bootstrap.
