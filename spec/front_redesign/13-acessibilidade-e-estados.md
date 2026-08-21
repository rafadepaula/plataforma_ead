# 13 — Acessibilidade e estados

## Foco
Anel sólido de 3px `--focus-ring` com offset de 2px (`:focus-visible`).
Campos ganham anel de 4px a 28% de opacidade (`--focus-ring-alpha`).
**Nunca `outline: none` sem substituto.**

## Camada de estado (padrão Material)
`currentColor` a **6% no hover** e **12% no press**.
- Botão preenchido sobe 1px no hover e aprofunda a sombra; volta no press com
  sombra menor.
- Card `interactive` sobe 3px e vai para `--elev-3`.
- Linha de tabela recebe tint azul a 6%.
- Item de drawer inativo recebe `--state-hover`.

## Desabilitado
Opacidade `.45` e `pointer-events: none`. Botão desabilitado permanece no DOM
com `aria-disabled`; ação que deixou de existir **some**, não fica cinza.

## Alvo de toque
≥ 48px em todo controle. Item de drawer 52px. Botão `sm` de 40px **só** dentro
de linha de tabela, onde a densidade justifica.

## Contraste
Texto de interface em 16px mínimo. `--text-secondary` apenas em texto de apoio.
Chip **sempre** usa o par container / on-container — nunca cor de marca sólida
sobre pastel. `$min-contrast-ratio` do Bootstrap fica no padrão.

## Cor nunca é o único sinal
- Status tem rótulo em texto além do chip.
- Ação destrutiva tem ícone (`trash` / `shield`) **e** palavra.
- Opção correta na autoria de prova é checkbox, não cor.
- Diff de auditoria destaca por peso e container, não por vermelho/verde.
- Cronômetro de prova comunica urgência por texto, não por matiz.

## Semântica obrigatória
- `role="alert"` nos alerts.
- `role="progressbar"` com `aria-valuenow` / `aria-valuemin` / `aria-valuemax`.
- `aria-current="page"` no item ativo do drawer.
- `aria-selected` nas tabs; `aria-pressed` nos chips de filtro.
- `aria-label` em **todo** botão só-ícone.
- `aria-describedby` ligando campo inválido à sua `.invalid-feedback`.
- Diálogo com `aria-labelledby` apontando para o título e foco preso dentro
  (o `bootstrap.Modal` já faz — não reimplementar).
- Landmark: um `<main>`, um `<nav>` no drawer, `<header>` na app bar.

## Validação Laravel
`.is-invalid` no controle + `.invalid-feedback` abaixo, em `--critical`, **com
ícone**. Nunca só a borda. Erro de formulário longo ganha resumo no topo, em
`x-ui.alert` `danger`, com âncoras para os campos.

## Movimento
Nada anima na entrada de conteúdo, então `prefers-reduced-motion` afeta apenas
**diálogo e drawer** — ambos passam a aparecer sem transição.

## Critérios de aceite
- Navegação completa por teclado em Login, Dashboard, Sala de aula e Prova do Aluno.
- Nenhum botão só-ícone sem `aria-label`.
- Auditoria de contraste sem falha AA em texto e componentes.
