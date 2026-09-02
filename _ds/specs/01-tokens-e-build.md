# 01 — Tokens e pipeline de build

## Objetivo
Substituir `resources/scss/_tokens.scss` e `app.scss` pelos tokens do design
system, sem deixar nenhum valor de cor/espaço/raio hardcoded sobrevivendo fora
da camada de tokens.

## De → Para
| Hoje | Depois |
|---|---|
| `_tokens.scss` (tema Modernist: cinza quente, `#ec3013`, raio 0) | `tokens/colors.css`, `typography.css`, `spacing.css`, `elevation.css`, `motion.css`, `primitives.css` do design system — valores conforme `DESIGN.md` na raiz (fonte única de verdade) |
| Archivo v19, `.woff2` de 0 byte (`public/fonts/archivo/`) | **Inter** via `tokens/fonts.css` (Google Fonts), pesos 400 / 500 / 600 / 700 — ver raiz `DESIGN.md` §2 |
| Tema Tailwind do paginador (ausente em 9 telas, C12) | `Pagination` autônomo do design system, sem dependência de tema externo |

## Passos
1. Importar os 6 arquivos de `tokens/` + `components/ui.css` via `styles.css`
   do design system em `app.scss` (ou como `<link>` se o build sair do pipeline
   Sass).
2. Remover `_tokens.scss` e qualquer `$variable` Sass de cor/raio/espaço do
   restante do SCSS — todo valor passa a vir de `var(--*)` conforme a seção 6
   do `DESIGN.md` da raiz.
3. Trocar a declaração de fonte: apontar `@font-face`/link para **Inter**
   (400 / 500 / 600 / 700, fallback `system-ui, sans-serif`); deletar os
   `.woff2` de 0 byte de Archivo.
4. Resolver as diretivas Alpine sem Alpine instalado (C10, C13 do diagnóstico)
   antes de qualquer componente que dependa de toggle (drawer mobile, dismiss
   de alert) — sem isso o componente novo herda o mesmo bug.

## Critérios de aceite
- Nenhum hex ou px de cor/espaço/raio fora da camada de tokens em
  `git grep` no CSS final.
- `npm run build` (ou equivalente) sem referência a Archivo.
- Paginação renderiza com CSS em todas as 9 telas listadas em C12.
