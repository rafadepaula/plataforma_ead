# 02 — Camada de tema e build

## Estado atual

`resources/scss/app.scss` é uma linha: `@import "bootstrap/scss/bootstrap";`.
Bootstrap de fábrica, sem tema. `vite.config.js` já compila
`resources/scss/app.scss` + `resources/js/app.js`. `app.js` já importa o bundle
completo do Bootstrap (com Popper) e expõe `window.bootstrap`.

## Arquitetura alvo — 5 camadas

```
1. tokens          _ds/plataforma-ead-design-system/tokens/*.css   (CSS custom properties)
2. ponte           resources/scss/_bridge.scss                     (token -> $variável Sass do Bootstrap)
3. Bootstrap core  bootstrap/scss/bootstrap                        (compilado COM as variáveis da ponte)
4. componentes     resources/scss/components/_*.scss               (só o que o Bootstrap não entrega)
5. telas           nenhum CSS por tela                             (composição de utilities + componentes)
```

Regra de ouro: **variável Sass do Bootstrap é a fonte de verdade de
implementação; o token CSS é a fonte de verdade de design.** A ponte é o único
lugar onde os dois se encontram. Não existe um set paralelo `--color-*` criado
à mão.

## `resources/scss/app.scss` alvo

```scss
// 1. Tokens do design system (custom properties, disponíveis em runtime)
@import "../../_ds/plataforma-ead-design-system/styles.css";

// 2. Ponte: valores literais alimentam as variáveis Sass do Bootstrap
@import "bridge";

// 3. Bootstrap completo, já temático
@import "bootstrap/scss/bootstrap";

// 4. Componentes que o Bootstrap não entrega
@import "components/index";
```

> Sass não resolve `var(--x)` em tempo de compilação. A ponte repete os
> **valores literais** dos tokens; os tokens continuam existindo em runtime
> para o que precisa deles (JS, sombras compostas, gradiente pastel wash).
> Divergência entre os dois é bug de build — cobrir com o teste do §Critérios.

## `resources/scss/_bridge.scss` — mapeamento obrigatório

| Variável Bootstrap | Valor | Token de origem |
|---|---|---|
| `$primary` | `#4c6fe7` | `--blue-600` |
| `$secondary` | `#2e9e6b` | `--mint-600` |
| `$success` | `#2e9e6b` | `--mint-600` |
| `$info` | `#2c7bd1` | `--sky-600` |
| `$warning` | `#5b6880` | `--attention` (**nunca** amarelo) |
| `$danger` | `#3b4a78` | `--critical` (**nunca** vermelho) |
| `$light` | `#f6f8fc` | `--grey-50` |
| `$dark` | `#1b2437` | `--grey-900` |
| `$body-bg` | `#f6f8fc` | `--surface-body` |
| `$body-color` | `#1b2437` | `--text-primary` |
| `$border-color` | `#dce3ee` | `--border-color` |
| `$font-family-base` | `"Nunito Sans", system-ui, …` | `--font-sans` |
| `$font-size-base` | `1rem` | `--font-size-base` |
| `$line-height-base` | `1.6` | `--line-height-base` |
| `$headings-font-weight` | `700` | `--font-heading-weight` |
| `$border-radius-sm` | `10px` | `--radius-sm` |
| `$border-radius` | `14px` | `--radius-md` |
| `$border-radius-lg` | `20px` | `--radius-lg` |
| `$border-radius-xl` | `28px` | `--radius-xl` |
| `$border-radius-pill` | `999px` | `--radius-pill` |
| `$box-shadow-sm` | `--elev-1` | `--elev-1` |
| `$box-shadow` | `--elev-2` | `--elev-2` |
| `$box-shadow-lg` | `--elev-4` | `--elev-4` |
| `$btn-border-radius` (+ `-sm`/`-lg`) | `999px` | botões são pílulas |
| `$btn-font-weight` | `700` | label 14px/700 |
| `$btn-padding-y` / `-x` | `12px` / `24px` | `--pad-btn-*` |
| `$input-height` | `52px` | `--field-height` |
| `$input-border-radius` | `14px` | `--radius-md` |
| `$input-focus-box-shadow` | `0 0 0 4px rgba(76,111,231,.28)` | `--focus-ring-alpha` |
| `$card-border-radius` | `20px` | `--radius-lg` |
| `$card-spacer-y` / `-x` | `28px` | `--pad-card` |
| `$modal-content-border-radius` | `28px` | `--radius-xl` |
| `$modal-backdrop-opacity` | `.42` | `--overlay-scrim` |
| `$table-cell-padding-y` / `-x` | `18px` / `20px` | `--pad-cell-*` |
| `$table-hover-bg` | `rgba(76,111,231,.06)` | `--state-hover` |
| `$dropdown-border-radius` | `20px` | `--radius-lg` |
| `$dropdown-box-shadow` | `--elev-4` | `--elev-4` |
| `$transition-base` | `all 200ms cubic-bezier(.4,0,.2,1)` | `--duration-base` + `--ease-standard` |

`$enable-shadows: true`, `$enable-negative-margins: true`.
`$min-contrast-ratio` fica no padrão — não afrouxar para caber cor.

## `resources/scss/components/` — o que o Bootstrap não entrega

Um partial por peça, todos importados por `_index.scss`:

`_appbar.scss`, `_drawer.scss`, `_page-header.scss`, `_stat-card.scss`,
`_empty-state.scss`, `_fab.scss`, `_chip.scss`, `_tabs.scss`, `_avatar.scss`,
`_brand-mark.scss`, `_pastel-wash.scss`, `_state-layer.scss`,
`_floating-label.scss`, `_reorder-list.scss`.

Nada aqui pode redefinir cor/raio/sombra em literal — só consumir `var(--*)`.

## Fontes

1. `tokens/fonts.css` já carrega Nunito Sans do Google Fonts.
2. Deletar `public/fonts/archivo/` (binários de 0 byte) e qualquer `@font-face`
   de Archivo remanescente.
3. Se o cliente entregar a família institucional, trocar o `@import` do Google
   por `@font-face` local — duas linhas, sem tocar em mais nada.

## JavaScript

`app.js` e `modules/index.js` **não mudam de arquitetura**: o registry auto-init
já está correto e o bundle do Bootstrap já está carregado. O redesign toca JS
apenas onde a casca visual muda o DOM que um módulo consulta — ver o documento
de tela correspondente. Os 13 módulos:

`AuditLogDiffModal`, `CsvImporter`, `ForumPolling`, `ForumReportModal`,
`HttpClient`, `LessonPlayer`, `ModuleReorder`, `NotificationBell`,
`NotificationService`, `QuizBuilder`, `QuizTimer`, `SmartInvitationForm`, `index`.

`NotificationService` deve ser reduzido a um wrapper fino sobre
`bootstrap.Toast` se ainda reimplementar fila/animação própria.

## Critérios de aceite

- `vendor/bin/sail npm run build` verde, sem warning de depreciação Sass novo.
- `resources/scss/app.scss` tem exatamente os 4 `@import` acima.
- Nenhum literal de cor/raio/sombra fora de `_bridge.scss` e `_ds/`.
- `window.bootstrap` continua exposto (contrato da suíte Dusk).
- Suíte Dusk completa verde após a fase de fundação.
