# 01 — Direção visual e tokens

Direção: **Material Bootstrap**. Superfícies claras e planas, profundidade por
elevação (não por borda), cantos suaves em tudo, tipografia grande, três
famílias pastel e nenhum tom quente.

Arquivos: `_ds/plataforma-ead-design-system/tokens/*.css`, agregados por
`styles.css`.

## 1. Cor

Três famílias pastel, rampa 50–900 cada. **Vermelho, laranja e amarelo estão
proibidos no sistema.**

| Papel | Token | Valor |
|---|---|---|
| Primário | `--primary` = `--blue-600` | `#4c6fe7` |
| Primário hover / pressed | `--primary-hover` / `--primary-pressed` | `#3d5bd0` / `#3550bc` |
| Secundário (conclusão, sucesso) | `--secondary` = `--mint-600` | `#2e9e6b` |
| Terciário (informação) | `--tertiary` = `--sky-600` | `#2c7bd1` |
| Destrutivo / bloqueado | `--critical` | `#3b4a78` (azul-slate) |
| Atenção neutra | `--attention` = `--grey-600` | `#5b6880` |

Pares container / on-container carregam **chips, alerts e ícones** — nunca cor
de marca sólida sobre pastel:

`--primary-container #e7ecfd` / `--on-primary-container #3550bc` ·
`--secondary-container #e4f5ec` / `--on-secondary-container #1e6a4a` ·
`--info-container #e2f0fd` / `--on-info-container #1f5289` ·
`--critical-container #e8ecf6` / `--on-critical-container #2c3760` ·
`--attention-container #eef1f7` / `--on-attention-container #2b3752`.

Superfícies: corpo `--surface-body #f6f8fc`, card `--surface #ffffff`, afundada
`--surface-sunken #fbfcfe`, alternativa `--surface-alt #eef1f7`.
Texto: `--text-primary #1b2437`, `--text-secondary #5b6880`,
`--text-disabled #8e99af`. Linhas: `--divider #e2e7f0`, `--border-color #dce3ee`.

**Ação destrutiva nunca comunica gravidade pela matiz**: usa `--critical` +
ícone (`shield` ou `trash`) + texto explícito.

## 2. Tipografia

**Nunito Sans** (Google Fonts), pesos 400 / 600 / 700 / 800. Base 16px,
entrelinha 1,6. Nada abaixo de 13px.

| Estilo | Tamanho | Peso |
|---|---|---|
| display | 44px | 700 |
| h1 | 36px | 700 |
| h2 | 30px | 700 |
| h3 | 24px | 700 |
| h4 | 20px | 700 |
| h5 / h6 | 18 / 16px | 700 |
| lead | 19px | 400 |
| body | 16px | 400 |
| body-sm | 15px | 400 |
| label (botões) | 14px | 700 |
| caption / overline | 13px | 400 / 700 |
| métrica | 40px | 800 |

Títulos com `letter-spacing: -.02em`. `overline` é o **único** uso de
caixa-alta (`.08em` de tracking).

Ação necessária ao cliente: se houver família institucional definida, os
binários entram em `public/fonts/` e a troca é de duas linhas em
`tokens/fonts.css`. Os `.woff2` de Archivo com 0 byte em
`public/fonts/archivo/` devem ser deletados nesta rodada.

## 3. Espaço

Grade de 4px: 4 · 8 · 12 · 16 · 20 · 24 · 32 · 40 · 48 · 64 · 80.

Padding de card 28px, de célula 18×20px, de `main` 40px; seções separadas por
40px. Layout: app bar 76px, drawer 280px, conteúdo máx. 1240px, coluna de
leitura 760px, formulário 440px, dropdown 380px. Campo 52px, controle 48px,
alvo de toque mínimo 48px.

## 4. Canto e elevação

Raios: 6 / 10 / 14 / 20 / 28 / 36 / pill. Botões, chips e paginação são
pílulas; campos 14px; cards 20px; diálogos 28px; heros 36px.
**Nenhuma aresta reta no sistema.**

Elevação substitui a borda como recurso de profundidade — cinco níveis de
sombra **azulada** (`rgba(27,36,55,…)`, nunca preto neutro): 1 cards e tabelas,
2 cards em destaque, 3 FAB e hover de card, 4 dropdowns, 5 diálogos.
`--elev-primary: 0 6px 18px rgba(76,111,231,.28)` só no CTA primário e no FAB.

## 5. Movimento

120ms cor e camada de estado · 200ms hover, elevação, chips · 320ms diálogos,
progresso, drawer. `--ease-standard cubic-bezier(.4,0,.2,1)` padrão,
`--ease-emphasized cubic-bezier(.2,0,0,1)` enfatizado.
Sem bounce, sem spring, sem animação de entrada em conteúdo, sem efeito de scroll.

## 6. Imagem e marca

O produto não traz fotografia nem ilustração, e **nada deve ser inventado para
preencher o vazio**. Onde imagem apareceria usa-se o *pastel wash*
`linear-gradient(135deg, var(--blue-100), var(--mint-100))` com um chip de
status sobreposto. `--image-treatment: saturate(1.02)` — se o cliente trouxer
fotos, entram em cor natural.

Não existe logotipo no repositório (`public/favicon.ico` é placeholder de 0
byte). No lugar dele, a **brand mark**: quadrado azul de 44px, raio 14px, 2–3
letras, ao lado do nome em peso 800.

## 7. Ícones

Subconjunto **Lucide** inline: `viewBox="0 0 24 24"`, `fill="none"`,
`stroke="currentColor"`, `stroke-width="2"`, pontas e junções arredondadas.

Tamanhos: 16 (chips, tabelas densas), 18 (botões, listas), 20 (drawer, alerts),
22 (app bar), 24 (FAB, stat cards), 28–32 (estados vazios, dentro de círculo
pastel).

O conjunto é exatamente o de `resources/views/components/ui/icon.blade.php`
mais os glifos do `app/Services/Navigation/NavigationRegistry.php`:
`bell, user, users, search, chevron-up/down/left/right, check, play, lock,
upload, plus, clock, x, book, book-open, award, settings, file-text,
message-square, log-out, home, arrow-left, grip-vertical, filter, eye, eye-off,
edit, trash, menu, help-circle, dashboard, buildings, clipboard, shield, info`.

Nome desconhecido **degrada para `info`**, nunca desaparece.
Emoji e caracteres unicode nunca são ícone.

## Critérios de aceite

- `git grep -nE '#[0-9a-fA-F]{3,6}' resources/scss resources/views` retorna zero
  fora de `_ds/`.
- Nenhuma matiz vermelha/laranja/amarela no CSS final.
- Nenhuma referência a Archivo no build; `public/fonts/archivo/` removido.
- Nenhum `border-radius: 0` sobrevivente.
