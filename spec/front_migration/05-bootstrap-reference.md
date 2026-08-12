# 05 — Referência Canônica Bootstrap 5.3 (crawl da documentação oficial)

**Fonte principal:** [Bootstrap 5.3 Documentation](https://getbootstrap.com/docs/5.3/)

---

## Índice

1. [Getting Started](#getting-started)
2. [Customize](#customize)
3. [Layout](#layout)
4. [Forms](#forms)
5. [Components](#components)
6. [Helpers](#helpers)
7. [Utilities](#utilities)
8. [Padrão Laravel + Bootstrap](#padrão-laravel--bootstrap)
9. [Armadilhas / Pitfalls](#armadilhas--pitfalls)

---

## Getting Started

### Introdução e Quick Start

**Fonte:** [Introduction](https://getbootstrap.com/docs/5.3/getting-started/introduction/)

**Template HTML mínimo:**

```html
<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bootstrap demo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  </head>
  <body>
    <h1>Olá, mundo!</h1>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BMnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
  </body>
</html>
```

**Links CDN:**
- CSS: `https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css`
- JS Bundle (com Popper): `https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js`

**Requisitos obrigatórios:**
1. Doctype HTML5: `<!doctype html>`
2. Viewport meta tag: `<meta name="viewport" content="width=device-width, initial-scale=1">`
3. Atributos `integrity` e `crossorigin="anonymous"` em CDN links

**Quando usar neste projeto:** Use este template como base para todas as páginas Blade do projeto EAD, substituindo o CDN por assets locais em produção.

---

### JavaScript

**Fonte:** [JavaScript](https://getbootstrap.com/docs/5.3/getting-started/javascript/)

**Importação como módulo ES:**

```html
<script type="importmap">
{
  "imports": {
    "@popperjs/core": "https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/esm/popper.min.js",
    "bootstrap": "https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.esm.min.js"
  }
}
</script>
<script type="module">
  import * as bootstrap from 'bootstrap'
  new bootstrap.Popover(document.getElementById('popoverButton'))
</script>
```

**Popper é necessário para:** Dropdowns, Popovers e Tooltips.

**Data Attributes API:**

```html
<!-- Padrão: data-bs-* -->
<button data-bs-toggle="popover" title="ESM" data-bs-content="Bang!">Custom popover</button>

<!-- Config via JSON (v5.2.0+) -->
<button data-bs-toggle="popover" data-bs-config='{"content":"..."}'>Popover</button>
```

**Convenção de naming:**
- `camelCase` em JavaScript → `kebab-case` em data attributes
- Ex: `customClass` → `data-bs-custom-class`

**Quando usar neste projeto:** Para modais de cadastro, dropdowns de navegação, tooltips de ajuda, toasts de notificação, e offcanvas de menu mobile.

---

### Webpack/Vite

**Fonte:** [Webpack](https://getbootstrap.com/docs/5.3/getting-started/webpack/)

**Instalação:**

```bash
npm i --save bootstrap @popperjs/core
```

**Importação completa em SCSS:**

```scss
// styles.scss
@import "bootstrap/scss/bootstrap";
```

**Importação JS em Vite:**

```javascript
// main.js
import * as bootstrap from 'bootstrap'
```

**Quando usar neste projeto:** O projeto Laravel 13 usa Vite por padrão. Configure `resources/css/app.scss` para importar Bootstrap e `resources/js/app.js` para os componentes JS necessários.

---

### Accessibility

**Fonte:** [Accessibility](https://getbootstrap.com/docs/5.3/getting-started/accessibility/)

**Requisitos estruturais:**
- HTML semântico obrigatório
- Contraste mínimo: 4.5:1 para texto, 3:1 para não-texto (WCAG 2.2)

**Visually Hidden (screen readers only):**

```html
<p class="text-danger">
  <span class="visually-hidden">Perigo: </span>
  Esta ação não é reversível
</p>
```

**Skip Links (foco visível):**

```html
<a class="visually-hidden-focusable" href="#content">
  Pular para o conteúdo principal
</a>
```

> **Importante:** `.visually-hidden-focusable` agora é uma classe standalone (não combine com `.visually-hidden`)

**Reduced Motion:**
- Bootstrap respeita `prefers-reduced-motion`
- Transições são desabilitadas
- Spinners ficam mais lentos

**Quando usar neste projeto:**
- `.visually-hidden` em labels de formulários acessíveis (ex: "Buscar" em campo de busca)
- Skip links em todas as páginas com `navbar` fixa
- Alternativas textuais para ícones em botões de ação

---

## Customize

### Sass

**Fonte:** [Sass](https://getbootstrap.com/docs/5.3/customize/sass/)

**Ordem exata de @import (recomendada):**

```scss
// 1. Functions primeiro
@import "../node_modules/bootstrap/scss/functions";

// 2. Variable overrides aqui
$primary: #ec3013;
$enable-rounded: false;

// 3. Required Bootstrap stylesheets
@import "../node_modules/bootstrap/scss/variables";
@import "../node_modules/bootstrap/scss/variables-dark";
@import "../node_modules/bootstrap/scss/maps";
@import "../node_modules/bootstrap/scss/mixins";
@import "../node_modules/bootstrap/scss/root";

// 4. Optional components
@import "../node_modules/bootstrap/scss/reboot";
@import "../node_modules/bootstrap/scss/type";
@import "../node_modules/bootstrap/scss/images";
@import "../node_modules/bootstrap/scss/containers";
@import "../node_modules/bootstrap/scss/grid";
@import "../node_modules/bootstrap/scss/helpers";

// 5. Utilities API last
@import "../node_modules/bootstrap/scss/utilities";
@import "../node_modules/bootstrap/scss/utilities/api";

// 6. Custom code here
```

**Variáveis globais importantes:**

```scss
// Em _variables.scss
$enable-rounded: true;     // Border-radius nos componentes
$enable-shadows: false;    // Box-shadow decorativos
$enable-gradients: false;  // Gradientes via background-image
$enable-transitions: true; // Transições CSS
```

**Quando usar neste projeto:** Criar `resources/css/bootstrap.scss` com `$enable-rounded: false` e `$primary: #ec3013` (cor de acento do projeto).

---

### Color

**Fonte:** [Color](https://getbootstrap.com/docs/5.3/customize/color/)

**Palette de cores ($theme-colors):**

```scss
$theme-colors: (
  "primary":    $primary,
  "secondary":  $secondary,
  "success":    $success,
  "info":       $info,
  "warning":    $warning,
  "danger":     $danger,
  "light":      $light,
  "dark":       $dark
);
```

**CSS variables para cores:**

```css
/* Base */
--bs-body-color, --bs-body-color-rgb
--bs-body-bg, --bs-body-bg-rgb

/* Semânticas */
--bs-secondary-color, --bs-secondary-color-rgb
--bs-secondary-bg, --bs-secondary-bg-rgb
--bs-tertiary-color, --bs-tertiary-color-rgb
--bs-emphasis-color, --bs-emphasis-color-rgb
--bs-border-color, --bs-border-color-rgb

/* Temas com variantes */
--bs-primary, --bs-primary-rgb
--bs-primary-bg-subtle
--bs-primary-border-subtle
--bs-primary-text-emphasis
```

**Escala de cores (100-900):**
- Cada cor base tem 9 níveis: `$blue-100` through `$blue-900`
- Gray scale: `$gray-100` through `$gray-900`

**Quando usar neste projeto:** Usar `--bs-primary-bg-subtle` para backgrounds de cards de curso, `--bs-primary-text-emphasis` para CTAs em landing pages.

---

### Color Modes

**Fonte:** [Color Modes](https://getbootstrap.com/docs/5.3/customize/color-modes/)

**Atributo data-bs-theme:**

```html
<!-- Dark mode global -->
<html lang="pt-BR" data-bs-theme="dark">

<!-- Component-specific theme -->
<div class="dropdown" data-bs-theme="dark">
  <ul class="dropdown-menu">
    <!-- Menu estilizado em dark -->
  </ul>
</div>
```

**Variáveis CSS por modo:**

```scss
[data-bs-theme=dark] {
  --bs-body-color: #dee2e6;
  --bs-body-bg: #212529;
  --bs-emphasis-color: #fff;
  --bs-link-color: #6ea8fe;
}
```

**Custom color modes:**

```scss
[data-bs-theme="blue"] {
  --bs-body-color: var(--bs-white);
  --bs-body-bg: var(--bs-blue);
  --bs-tertiary-bg: #{$blue-600};
}
```

**Quando usar neste projeto:** Sidebar administrativa com `data-bs-theme="dark"` em neutral-900, dropdowns de perfil em dark mode.

---

### Options

**Fonte:** [Options](https://getbootstrap.com/docs/5.3/customize/options/)

**Variáveis de options globais:**

```scss
$spacer: 1rem;                              // Default spacer
$enable-dark-mode: true;                    // Dark mode support
$enable-rounded: true;                      // Border-radius
$enable-shadows: false;                     // Box-shadow
$enable-gradients: false;                   // Gradients
$enable-transitions: true;                  // Transitions
$enable-grid-classes: true;                 // Grid system classes
$enable-container-classes: true;            // Container classes
$enable-caret: true;                         // Dropdown caret
$enable-button-pointers: true;              // Hand cursor em buttons
$enable-validation-icons: true;              // Form validation icons
$enable-negative-margins: false;            // Negative margins
$enable-important-utilities: true;         // !important em utilities
```

**Quando usar neste projeto:** Set `$enable-rounded: false` para bordas quadradas system-wide.

---

### Utility API

**Fonte:** [Utility API](https://getbootstrap.com/docs/5.3/utilities/api/)

**Estrutura do $utilities map:**

```scss
$utilities: (
  "utility-name": (
    property: css-property,
    class: class-prefix,      // opcional
    values: list-or-map,
    // other options
  )
);
```

**Adicionar nova utility:**

```scss
@import "bootstrap/scss/functions";
@import "bootstrap/scss/variables";
@import "bootstrap/scss/variables-dark";
@import "bootstrap/scss/maps";
@import "bootstrap/scss/mixins";
@import "bootstrap/scss/utilities";

$utilities: map-merge(
  $utilities,
  (
    "cursor": (
      property: cursor,
      class: cursor,
      responsive: true,
      values: auto pointer grab,
    )
  )
);

@import "bootstrap/scss/utilities/api";
```

**Opções disponíveis:**
- `property`: CSS property name (obrigatório)
- `values`: Valores a gerar (obrigatório)
- `class`: Prefixo de classe (opcional, default = property)
- `css-var`: Gerar CSS variables (default false)
- `state`: Pseudo-class variants (`:hover`, `:focus`)
- `responsive`: Gerar classes responsivas
- `rfs`: Enable fluid rescaling

**Quando usar neste projeto:** Adicionar utilities específicas do projeto EAD como `.font-archivo` para a fonte Archivo.

---

### CSS Variables

**Fonte:** [CSS Variables](https://getbootstrap.com/docs/5.3/customize/css-variables/)

**Variáveis root (light):**

```css
:root, [data-bs-theme=light] {
  --bs-blue: #0d6efd;
  --bs-primary: #0d6efd;
  --bs-body-font-family: var(--bs-font-sans-serif);
  --bs-body-color: #212529;
  --bs-body-bg: #fff;
  --bs-link-color: #0d6efd;
  --bs-border-color: #dee2e6;
  --bs-border-radius: 0.375rem;
}
```

**Variáveis dark mode:**

```css
[data-bs-theme=dark] {
  color-scheme: dark;
  --bs-body-color: #dee2e6;
  --bs-body-bg: #212529;
  --bs-emphasis-color: #fff;
}
```

**Variáveis de foco (v5.3.0+):**

```css
:root {
  --bs-focus-ring-width: 0.25rem;
  --bs-focus-ring-opacity: 0.25;
  --bs-focus-ring-color: rgba(13, 110, 253, 0.25);
}
```

**Sobrescrever:**

```css
:root {
  --bs-primary: #ec3013;  /* Cor de acento EAD */
  --bs-border-radius: 0;   /* Sistema sem bordas arredondadas */
}
```

> **Importante:** Grid breakpoint variables NÃO funcionam em media queries (limitação da especificação CSS)

**Quando usar neste projeto:** Definir `--bs-primary: #ec3013` e `--bs-border-radius: 0` em `resources/css/app.scss` para aplicar system-wide.

---

### Components (CSS Variables por Componente)

**Fonte:** [Components](https://getbootstrap.com/docs/5.3/customize/components/)

**Padrão de component-level CSS variables:**

```scss
// Example: Alert
@each $state in map-keys($theme-colors) {
  .alert-#{$state} {
    --#{$prefix}alert-color: var(--#{$prefix}#{$state}-text-emphasis);
    --#{$prefix}alert-bg: var(--#{$prefix}#{$state}-bg-subtle);
    --#{$prefix}alert-border-color: var(--#{$prefix}#{$state}-border-subtle);
  }
}
```

**Variables para Buttons:**

```css
--bs-btn-color
--bs-btn-bg
--bs-btn-border-color
--bs-btn-hover-color
--bs-btn-hover-bg
--bs-btn-hover-border-color
--bs-btn-focus-shadow-rgb
--bs-btn-active-color
--bs-btn-active-bg
--bs-btn-active-border-color
```

**Variables para Cards:**

```css
--bs-card-spacer-y
--bs-card-spacer-x
--bs-card-title-spacer-y
--bs-card-border-width
--bs-card-border-color
--bs-card-border-radius
--bs-card-box-shadow
--bs-card-inner-border-radius
```

**Quando usar neste projeto:** Customizar `.card` de cursos com `--bs-card-border-radius: 0` e `.navbar` admin com `--bs-navbar-bg: var(--bs-gray-900)`.

---

## Layout

### Breakpoints

**Fonte:** [Breakpoints](https://getbootstrap.com/docs/5.3/layout/breakpoints/)

**Breakpoints disponíveis:**

| Breakpoint | Class Infix | Dimensions |
|------------|-------------|------------|
| Extra small | None | <576px |
| Small | `sm` | ≥576px |
| Medium | `md` | ≥768px |
| Large | `lg` | ≥992px |
| Extra large | `xl` | ≥1200px |
| Extra extra large | `xxl` | ≥1400px |

**Sass variables:**

```scss
$grid-breakpoints: (
  xs: 0,
  sm: 576px,
  md: 768px,
  lg: 992px,
  xl: 1200px,
  xxl: 1400px
);
```

**Media query syntax:**

```css
/* Min-width (mobile-first) */
@media (min-width: 576px) { ... }

/* Max-width */
@media (max-width: 575.98px) { ... }

/* Single breakpoint */
@media (min-width: 768px) and (max-width: 991.98px) { ... }
```

**Quando usar neste projeto:** `.d-md-none` para esconder sidebar em mobile, `.col-lg-3` para grid de cursos em desktop.

---

### Containers

**Fonte:** [Containers](https://getbootstrap.com/docs/5.3/layout/containers/)

**Classes de container:**

```html
<!-- Default responsive -->
<div class="container">...</div>

<!-- Fluid (100% width) -->
<div class="container-fluid">...</div>

<!-- Responsive 100% width até breakpoint -->
<div class="container-sm">...</div>  <!-- 100% até sm, depois 540px -->
<div class="container-md">...</div>  <!-- 100% até md, depois 720px -->
<div class="container-lg">...</div>  <!-- 100% até lg, depois 960px -->
<div class="container-xl">...</div>  <!-- 100% até xl, depois 1140px -->
<div class="container-xxl">...</div> <!-- 100% até xxl, depois 1320px -->
```

**Max-widths por breakpoint:**

| Breakpoint | Container Max-Width |
|------------|---------------------|
| xs | 100% |
| sm | 540px |
| md | 720px |
| lg | 960px |
| xl | 1140px |
| xxl | 1320px |

**Sass variables:**

```scss
$container-max-widths: (
  sm: 540px,
  md: 720px,
  lg: 960px,
  xl: 1140px,
  xxl: 1320px
);
```

**Quando usar neste projeto:** `.container-fluid` para dashboard administrativo, `.container-xxl` para landing pages.

---

### Grid

**Fonte:** [Grid](https://getbootstrap.com/docs/5.3/layout/grid/)

**Estrutura básica:**

```html
<div class="container">
  <div class="row">
    <div class="col">Column 1</div>
    <div class="col">Column 2</div>
    <div class="col">Column 3</div>
  </div>
</div>
```

**Prefixos de breakpoint:**

| Breakpoint | Class Prefix |
|------------|--------------|
| xs | `.col-` |
| sm | `.col-sm-` |
| md | `.col-md-` |
| lg | `.col-lg-` |
| xl | `.col-xl-` |
| xxl | `.col-xxl-` |

**Colunas de largura fixa:**

```html
<div class="row">
  <div class="col-4">Span 4/12 (33.33%)</div>
  <div class="col-8">Span 8/12 (66.66%)</div>
</div>
```

**Auto-layout (igual):**

```html
<div class="row">
  <div class="col">Todas iguais</div>
  <div class="col">Todas iguais</div>
</div>
```

**Row columns (shorthand):**

```html
<div class="row row-cols-2">  <!-- Força 2 colunas por row -->
<div class="row row-cols-3">  <!-- Força 3 colunas por row -->
<div class="row row-cols-auto">  <!-- Largura natural -->
```

**Responsive patterns:**

```html
<!-- Stacked → horizontal -->
<div class="col-sm-8">col-sm-8</div>

<!-- Mix and match -->
<div class="col-6 col-md-4">6 em xs, 4 em md+</div>
```

**Nesting:**

```html
<div class="container">
  <div class="row">
    <div class="col-sm-3">Level 1</div>
    <div class="col-sm-9">
      <div class="row">
        <div class="col-8 col-sm-6">Level 2</div>
      </div>
    </div>
  </div>
</div>
```

**Gutter classes:**

```html
<div class="g-0">Remove all gutters</div>
<div class="gx-3">Horizontal gutters only</div>
<div class="gy-3">Vertical gutters only</div>
<div class="g-3">All gutters</div>
```

**Sass variables:**

```scss
$grid-columns: 12;
$grid-gutter-width: 1.5rem;
$grid-row-columns: 6;
```

**Quando usar neste projeto:**
- Grid de cursos: `.row.row-cols-1.row-cols-md-3`
- Layout admin: `.col-md-3` sidebar + `.col-md-9` content
- Cards de quiz: `.col-12.col-lg-6`

---

### Columns

**Fonte:** [Columns](https://getbootstrap.com/docs/5.3/layout/columns/)

**Ordering:**

```html
<div class="col-3 order-md-12">Primeiro visualmente em md+</div>
<div class="col-9 order-md-1">Segundo visualmente em md+</div>
```

**Offset:**

```html
<div class="col-md-6 offset-md-3">Centralizado em md+</div>
```

**Margin utilities (auto):**

```html
<div class="col-md-6 ms-auto">Empurrado para direita em md+</div>
```

**Nesting independent:**

```html
<div class="row">
  <div class="col-sm-9">
    <div class="row">
      <div class="col-7 col-sm-5">Nested</div>
    </div>
  </div>
</div>
```

**Quando usar neste projeto:** Offsets para centralizar modais, `.order-md-last` para mobile-first layout.

---

## Forms

### Overview

**Fonte:** [Overview](https://getbootstrap.com/docs/5.3/forms/overview/)

**Estrutura básica:**

```html
<form>
  <div class="mb-3">
    <label for="exampleInputEmail1" class="form-label">Email</label>
    <input type="email" class="form-control" id="exampleInputEmail1" aria-describedby="emailHelp">
    <div id="emailHelp" class="form-text">Nunca compartilharemos seu email.</div>
  </div>
  <div class="mb-3 form-check">
    <input type="checkbox" class="form-check-input" id="exampleCheck1">
    <label class="form-check-label" for="exampleCheck1">Concordo</label>
  </div>
  <button type="submit" class="btn btn-primary">Enviar</button>
</form>
```

**Classes chave:**
- `.form-control` - Inputs e textareas
- `.form-label` - Labels estilizados
- `.form-text` - Texto de ajuda
- `.form-check`, `.form-check-input`, `.form-check-label` - Checkboxes/radios
- `.mb-3` - Margin bottom para spacing

**Disabled forms:**

```html
<!-- Individual -->
<input class="form-control" type="text" placeholder="Disabled" disabled>

<!-- Fieldset -->
<form>
  <fieldset disabled>
    <div class="mb-3">
      <label for="disabledTextInput" class="form-label">Disabled input</label>
      <input type="text" id="disabledTextInput" class="form-control" disabled>
    </div>
  </fieldset>
</form>
```

**Quando usar neste projeto:** Padrão para todos os formulários CRUD (usuários, cursos, aulas).

---

### Form Control

**Fonte:** [Form Control](https://getbootstrap.com/docs/5.3/forms/form-control/)

**Classes básicas:**

```html
<div class="mb-3">
  <label for="exampleFormControlInput1" class="form-label">Email</label>
  <input type="email" class="form-control" id="exampleFormControlInput1" placeholder="name@example.com">
</div>
<div class="mb-3">
  <label for="exampleFormControlTextarea1" class="form-label">Mensagem</label>
  <textarea class="form-control" id="exampleFormControlTextarea1" rows="3"></textarea>
</div>
```

**Sizing:**

```html
<input class="form-control form-control-lg" type="text" placeholder="Large">
<input class="form-control form-control-sm" type="text" placeholder="Small">
```

**Plaintext:**

```html
<div class="mb-3 row">
  <label for="staticEmail" class="col-sm-2 col-form-label">Email</label>
  <div class="col-sm-10">
    <input type="text" readonly class="form-control-plaintext" id="staticEmail" value="email@example.com">
  </div>
</div>
```

**Readonly/Disabled:**

```html
<input class="form-control" type="text" value="Readonly" readonly>
<input class="form-control" type="text" placeholder="Disabled" disabled>
```

**Input types especiais:**

```html
<!-- File -->
<input class="form-control" type="file" id="formFile">

<!-- Color -->
<input type="color" class="form-control form-control-color" id="exampleColorInput" value="#563d7c">

<!-- Datalist -->
<input class="form-control" list="datalistOptions" id="exampleDataList">
<datalist id="datalistOptions">
  <option value="San Francisco">
  <option value="New York">
</datalist>
```

**Quando usar neste projeto:** `.form-control-plaintext` para campos readonly de CPF, `.form-control-lg` para formulários de quiz.

---

### Select

**Fonte:** [Select](https://getbootstrap.com/docs/5.3/forms/select/)

**Select básico:**

```html
<select class="form-select" aria-label="Default select">
  <option selected>Selecione</option>
  <option value="1">Um</option>
  <option value="2">Dois</option>
</select>
```

**Sizing:**

```html
<select class="form-select form-select-lg">Large</select>
<select class="form-select form-select-sm">Small</select>
```

**Multiple/Size:**

```html
<!-- Multiple -->
<select class="form-select" multiple>
  <option selected>Open this select menu</option>
</select>

<!-- Fixed size -->
<select class="form-select" size="3">
  <option selected>Open this select menu</option>
</select>

<!-- Disabled -->
<select class="form-select" disabled>
  <option selected>Disabled select</option>
</select>
```

**Quando usar neste projeto:** Select de categorias de curso, select de perfil de usuário.

---

### Checks & Radios

**Fonte:** [Checks & Radios](https://getbootstrap.com/docs/5.3/forms/checks-radios/)

**Classes básicas:**

```html
<div class="form-check">
  <input class="form-check-input" type="checkbox" value="" id="checkDefault">
  <label class="form-check-label" for="checkDefault">Default checkbox</label>
</div>

<div class="form-check">
  <input class="form-check-input" type="radio" name="radioDefault" id="radioDefault1">
  <label class="form-check-label" for="radioDefault1">Default radio</label>
</div>
```

**Switches (toggle):**

```html
<div class="form-check form-switch">
  <input class="form-check-input" type="checkbox" role="switch" id="switchCheckDefault">
  <label class="form-check-label" for="switchCheckDefault">Switch</label>
</div>
```

> **Acessibilidade:** Use `role="switch"` para switches. Para haptics nativos iOS (Safari 17.4+), adicione atributo `switch` ao input.

**Inline checks/radios:**

```html
<div class="form-check form-check-inline">
  <input class="form-check-input" type="checkbox" id="inlineCheckbox1">
  <label class="form-check-label" for="inlineCheckbox1">1</label>
</div>
```

**Reverse layout:**

```html
<div class="form-check form-check-reverse">
  <input class="form-check-input" type="checkbox" value="" id="reverseCheck1">
  <label class="form-check-label" for="reverseCheck1">Reverse</label>
</div>
```

**Toggle buttons:**

```html
<input type="checkbox" class="btn-check" id="btn-check" autocomplete="off">
<label class="btn btn-primary" for="btn-check">Single toggle</label>

<input type="radio" class="btn-check" name="options" id="option1" autocomplete="off" checked>
<label class="btn btn-secondary" for="option1">Checked</label>
```

**Quando usar neste projeto:** `.form-switch` para configurações de notificação, toggle buttons para seleção de categoria em filtros.

---

### Range

**Fonte:** [Range](https://getbootstrap.com/docs/5.3/forms/range/)

**Range básico:**

```html
<label for="customRange1" class="form-label">Example range</label>
<input type="range" class="form-range" id="customRange1">
```

**Disabled/Step:**

```html
<input class="form-range" type="range" id="disabledRange" disabled>

<input class="form-range" type="range" min="0" max="5" step="0.5" id="customRange2">
```

**Quando usar neste projeto:** Controles de volume no player de vídeo, slider de progresso de aula.

---

### Input Group

**Fonte:** [Input Group](https://getbootstrap.com/docs/5.3/forms/input-group/)

**Classes básicas:**

```html
<div class="input-group mb-3">
  <span class="input-group-text">@</span>
  <input type="text" class="form-control" placeholder="Username">
</div>
```

**Sizing:**

```html
<div class="input-group input-group-sm mb-3">
  <span class="input-group-text">Small</span>
  <input type="text" class="form-control">
</div>
```

**Multiple inputs:**

```html
<div class="input-group mb-3">
  <span class="input-group-text">Nome e sobrenome</span>
  <input type="text" aria-label="First name" class="form-control">
  <input type="text" aria-label="Last name" class="form-control">
</div>
```

**Button addons:**

```html
<div class="input-group mb-3">
  <button class="btn btn-outline-secondary" type="button">Button</button>
  <input type="text" class="form-control">
</div>
```

**Dropdowns:**

```html
<div class="input-group mb-3">
  <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">Dropdown</button>
  <ul class="dropdown-menu">
    <li><a class="dropdown-item" href="#">Action</a></li>
  </ul>
  <input type="text" class="form-control">
</div>
```

**Segmented buttons:**

```html
<div class="input-group mb-3">
  <button type="button" class="btn btn-outline-secondary">Action</button>
  <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown">
    <span class="visually-hidden">Toggle Dropdown</span>
  </button>
  <input type="text" class="form-control">
</div>
```

**Quando usar neste projeto:** Input groups para CPF (xxx.xxx.xxx-xx), busca com ícone, preços com R$ prefixo.

---

### Floating Labels

**Fonte:** [Floating Labels](https://getbootstrap.com/docs/5.3/forms/floating-labels/)

**Estrutura básica:**

```html
<div class="form-floating mb-3">
  <input type="email" class="form-control" id="floatingInput" placeholder="name@example.com">
  <label for="floatingInput">Email address</label>
</div>
```

> **Requisitos:** `<input>` primeiro, placeholder não-vazio obrigatório.

**Textarea:**

```html
<div class="form-floating">
  <textarea class="form-control" placeholder="Leave a comment here" id="floatingTextarea"></textarea>
  <label for="floatingTextarea">Comments</label>
</div>
```

**Selects:**

```html
<div class="form-floating">
  <select class="form-select" id="floatingSelect">
    <option selected>Open this select menu</option>
  </select>
  <label for="floatingSelect">Works with selects</label>
</div>
```

> **Nota:** Selects com `size` e `multiple` NÃO são suportados.

**Input groups:**

```html
<div class="input-group mb-3">
  <span class="input-group-text">@</span>
  <div class="form-floating">
    <input type="text" class="form-control" id="floatingInputGroup1" placeholder="Username">
    <label for="floatingInputGroup1">Username</label>
  </div>
</div>
```

**Quando usar neste projeto:** Login forms, busca no header, formulários de cadastro compactos.

---

### Validation

**Fonte:** [Validation](https://getbootstrap.com/docs/5.3/forms/validation/)

**Server-side validation:**

```html
<!-- Invalid field -->
<div class="col-md-6">
  <label for="validationServer03" class="form-label">City</label>
  <input type="text" class="form-control is-invalid" 
         id="validationServer03" 
         aria-describedby="validationServer03Feedback" 
         required>
  <div id="validationServer03Feedback" class="invalid-feedback">
    Please provide a valid city.
  </div>
</div>

<!-- Valid field -->
<div class="col-md-4">
  <label for="validationServer01" class="form-label">First name</label>
  <input type="text" class="form-control is-valid" 
         id="validationServer01" 
         value="Mark" 
         required>
  <div class="valid-feedback">
    Looks good!
  </div>
</div>
```

**Input groups (require .has-validation):**

```html
<div class="input-group has-validation">
  <span class="input-group-text">@</span>
  <input type="text" class="form-control is-invalid" 
         id="validationServerUsername" 
         aria-describedby="inputGroupPrepend3 validationServerUsernameFeedback" 
         required>
  <div id="validationServerUsernameFeedback" class="invalid-feedback">
    Please choose a username.
  </div>
</div>
```

**Classes suportadas:**
- `.is-invalid` - Campo inválido
- `.is-valid` - Campo válido
- `.invalid-feedback` - Mensagem de erro
- `.valid-feedback` - Mensagem de sucesso

**Quando usar neste projeto:** Integração direta com `$errors->has()` do Laravel Blade (ver seção Padrão Laravel).

---

## Components

### Accordion

**Fonte:** [Accordion](https://getbootstrap.com/docs/5.3/components/accordion/)

**Classes e estrutura:**

```html
<div class="accordion" id="accordionExample">
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
        Accordion Item #1
      </button>
    </h2>
    <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#accordionExample">
      <div class="accordion-body">Content</div>
    </div>
  </div>
</div>
```

**Classes:**
- `.accordion` - Wrapper principal
- `.accordion-item` - Item individual
- `.accordion-header` - Header do item
- `.accordion-button` - Botão toggle
- `.accordion-button.collapsed` - Estado colapsado
- `.accordion-collapse` - Wrapper do conteúdo
- `.accordion-collapse.collapse` - Estado colapsado
- `.accordion-collapse.collapse.show` - Estado expandido
- `.accordion-body` - Conteúdo interno

**Data attributes:**
- `data-bs-toggle="collapse"` - Habilita toggle
- `data-bs-target="#selector"` - Elemento alvo
- `data-bs-parent="#selector"` - Accordion mutual exclusivity

**Variantes:**

```html
<!-- Flush (sem bordas) -->
<div class="accordion accordion-flush">

<!-- Always open (sem data-bs-parent) -->
<div class="accordion" id="accordionPanelsStayOpenExample">
  <div class="accordion-item">
    <div id="panelsStayOpen-collapseOne" class="accordion-collapse collapse show">
      <!-- Sem data-bs-parent -->
    </div>
  </div>
</div>
```

**Events:**

```javascript
myCollapsible.addEventListener('hidden.bs.collapse', event => {
  // do something...
})
```

| Event | Description |
|-------|-------------|
| `hide.bs.collapse` | Imediatamente quando hide() é chamado |
| `hidden.bs.collapse` | Após colapso completar (após transição CSS) |
| `show.bs.collapse` | Imediatamente quando show() é chamado |
| `shown.bs.collapse` | Após expansão completar (após transição CSS) |

**Quando usar neste projeto:** FAQ de cursos, módulos colapsáveis de aulas, filtros avançados em dashboards.

---

### Alerts

**Fonte:** [Alerts](https://getbootstrap.com/docs/5.3/components/alerts/)

**Classes:**

```html
<div class="alert alert-primary" role="alert">
  A simple primary alert!
</div>
<div class="alert alert-success" role="alert">
  Success alert!
</div>
```

**Classes base:**
- `.alert` - Classe base
- `.alert-{primary,secondary,success,danger,warning,info,light,dark}` - Variantes

**Dismissible:**

```html
<div class="alert alert-warning alert-dismissible fade show" role="alert">
  <strong>Holy guacamole!</strong> You should check in on those fields below.
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
```

**Classes adicionais:**
- `.alert-dismissible` - Padding extra para close button
- `.fade`, `.show` - Animação
- `.alert-link` - Links coloridos dentro de alert
- `.alert-heading` - Styling para headings

**CSS variables (v5.2.0+):**

```css
--#{$prefix}alert-bg
--#{$prefix}alert-padding-x
--#{$prefix}alert-padding-y
--#{$prefix}alert-margin-bottom
--#{$prefix}alert-color
--#{$prefix}alert-border-color
--#{$prefix}alert-link-color
```

**Events:**
- `close.bs.alert` - Imediatamente quando close() é chamado
- `closed.bs.alert` - Quando alert é fechado (após transição CSS)

**Quando usar neste projeto:** Flash messages do Laravel (`session('success')`), mensagens de erro de formulário.

---

### Badge

**Fonte:** [Badge](https://getbootstrap.com/docs/5.3/components/badge/)

**Classes:**

```html
<span class="badge text-bg-primary">Primary</span>
<span class="badge text-bg-secondary">Secondary</span>
<span class="badge rounded-pill text-bg-primary">Primary Pill</span>
```

**Classes base:**
- `.badge` - Badge base
- `.text-bg-{color}` - Background + foreground (v5.2.0+)

**Variantes de cor:**
- `text-bg-primary`, `text-bg-secondary`, `text-bg-success`
- `text-bg-danger`, `text-bg-warning`, `text-bg-info`
- `text-bg-light`, `text-bg-dark`

**Pill badges:**

```html
<span class="badge rounded-pill text-bg-primary">Primary</span>
```

**Em headings (auto-scaling):**

```html
<h3>Example heading <span class="badge text-bg-secondary">New</span></h3>
```

**Em buttons (counters):**

```html
<button type="button" class="btn btn-primary">
  Notifications <span class="badge text-bg-secondary">4</span>
</button>
```

**Positioned badges:**

```html
<button type="button" class="btn btn-primary position-relative">
  Inbox
  <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
    99+
    <span class="visually-hidden">unread messages</span>
  </span>
</button>
```

**Quando usar neste projeto:** Contador de notificações, badge de progresso de curso, status de conclusão.

---

### Breadcrumb

**Fonte:** [Breadcrumb](https://getbootstrap.com/docs/5.3/components/breadcrumb/)

**Classes:**

```html
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="#">Home</a></li>
    <li class="breadcrumb-item"><a href="#">Library</a></li>
    <li class="breadcrumb-item active" aria-current="page">Data</li>
  </ol>
</nav>
```

**Classes:**
- `.breadcrumb` - `<ol>` container
- `.breadcrumb-item` - Cada `<li>`
- `.active` - Item atual

**ARIA requirements:**
- `aria-label="breadcrumb"` no `<nav>`
- `aria-current="page"` no item `.active`

**Customizar divisor:**

```html
<nav style="--bs-breadcrumb-divider: '>';" aria-label="breadcrumb">
```

**Quando usar neste projeto:** Navegação hierárquica (Home > Cursos > Curso X > Aula Y).

---

### Buttons

**Fonte:** [Buttons](https://getbootstrap.com/docs/5.3/components/buttons/)

**Classes base:**

```html
<button type="button" class="btn btn-primary">Primary</button>
<button type="button" class="btn btn-secondary">Secondary</button>
<button type="button" class="btn btn-success">Success</button>
<button type="button" class="btn btn-danger">Danger</button>
<button type="button" class="btn btn-warning">Warning</button>
<button type="button" class="btn btn-info">Info</button>
<button type="button" class="btn btn-light">Light</button>
<button type="button" class="btn btn-dark">Dark</button>
<button type="button" class="btn btn-link">Link</button>
```

**Outline buttons:**

```html
<button type="button" class="btn btn-outline-primary">Primary</button>
<button type="button" class="btn btn-outline-secondary">Secondary</button>
```

**Sizing:**

```html
<button type="button" class="btn btn-primary btn-lg">Large button</button>
<button type="button" class="btn btn-primary btn-sm">Small button</button>
```

**Block buttons (full-width):**

```html
<div class="d-grid gap-2">
  <button class="btn btn-primary" type="button">Button</button>
</div>

<!-- Responsive -->
<div class="d-grid gap-2 d-md-block">
  <button class="btn btn-primary" type="button">Button</button>
</div>
```

**Disabled state:**

```html
<button type="button" class="btn btn-primary" disabled>Primary button</button>
<a class="btn btn-primary disabled" role="button" aria-disabled="true">Primary link</a>
```

**Button tags:**

```html
<a class="btn btn-primary" href="#" role="button">Link</a>
<button class="btn btn-primary" type="submit">Button</button>
<input class="btn btn-primary" type="button" value="Input">
<input class="btn btn-primary" type="submit" value="Submit">
```

**Quando usar neste projeto:** CTAs em landing pages (`.btn-primary`), ações secundárias (`.btn-outline-secondary`), botões de exclusão (`.btn-danger`).

---

### Button Group

**Fonte:** [Button Group](https://getbootstrap.com/docs/5.3/components/button-group/)

**Classes:**

```html
<div class="btn-group" role="group" aria-label="Basic example">
  <button type="button" class="btn btn-primary">Left</button>
  <button type="button" class="btn btn-primary">Middle</button>
  <button type="button" class="btn btn-primary">Right</button>
</div>
```

**Toolbar:**

```html
<div class="btn-toolbar" role="toolbar" aria-label="Toolbar with button groups">
  <div class="btn-group me-2" role="group">
    <button type="button" class="btn btn-primary">1</button>
  </div>
</div>
```

**Sizing:**

```html
<div class="btn-group btn-group-lg">Large</div>
<div class="btn-group">Default</div>
<div class="btn-group btn-group-sm">Small</div>
```

**Vertical:**

```html
<div class="btn-group-vertical" role="group">
  <button type="button" class="btn btn-primary">Button</button>
</div>
```

**Checkbox/Radio button groups:**

```html
<div class="btn-group" role="group">
  <input type="checkbox" class="btn-check" id="btncheck1" autocomplete="off">
  <label class="btn btn-outline-primary" for="btncheck1">Checkbox 1</label>

  <input type="radio" class="btn-check" name="btnradio" id="btnradio1" autocomplete="off" checked>
  <label class="btn btn-outline-primary" for="btnradio1">Radio 1</label>
</div>
```

**Quando usar neste projeto:** Grupo de filtros, botões de ação em tabelas (Editar/Excluir), controles de player de vídeo.

---

### Card

**Fonte:** [Card](https://getbootstrap.com/docs/5.3/components/card/)

**Classes e estrutura:**

```html
<div class="card" style="width: 18rem;">
  <img src="..." class="card-img-top" alt="...">
  <div class="card-body">
    <h5 class="card-title">Card title</h5>
    <h6 class="card-subtitle mb-2 text-body-secondary">Card subtitle</h6>
    <p class="card-text">Some quick example text.</p>
    <a href="#" class="card-link">Card link</a>
  </div>
</div>
```

**Classes:**
- `.card` - Container principal
- `.card-body` - Conteúdo com padding
- `.card-title` - Título (em `<h*>`)
- `.card-subtitle` - Subtítulo
- `.card-text` - Texto principal
- `.card-link` - Links estilizados
- `.card-header` - Header opcional
- `.card-footer` - Footer opcional
- `.card-img-top` - Imagem no topo
- `.card-img-bottom` - Imagem embaixo
- `.card-img` - Background image
- `.card-img-overlay` - Overlay na imagem

**Variants de cor (v5.2.0+):**

```html
<div class="card text-bg-primary mb-3">Primary card</div>
<div class="card text-bg-secondary mb-3">Secondary card</div>
```

**Variants de borda:**

```html
<div class="card border-primary">Primary border</div>
```

**Card groups:**

```html
<div class="card-group">
  <div class="card">Card 1</div>
  <div class="card">Card 2</div>
</div>
```

**Grid cards:**

```html
<div class="row row-cols-1 row-cols-md-3 g-4">
  <div class="col">
    <div class="card h-100">Card</div>
  </div>
</div>
```

**Horizontal cards:**

```html
<div class="card mb-3" style="max-width: 540px;">
  <div class="row g-0">
    <div class="col-md-4">
      <img src="..." class="img-fluid rounded-start" alt="...">
    </div>
    <div class="col-md-8">
      <div class="card-body">...</div>
    </div>
  </div>
</div>
```

**Navigation integration:**

```html
<ul class="nav nav-tabs card-header-tabs">Tabs</ul>
<ul class="nav nav-pills card-header-pills">Pills</ul>
```

**List groups:**

```html
<div class="card">
  <ul class="list-group list-group-flush">
    <li class="list-group-item">An item</li>
  </ul>
</div>
```

**Quando usar neste projeto:** Cards de cursos em grid, cards de aulas em player, cards de estatísticas em dashboard.

---

### Carousel

**Fonte:** [Carousel](https://getbootstrap.com/docs/5.3/components/carousel/)

**Classes e estrutura:**

```html
<div id="carouselExample" class="carousel slide">
  <div class="carousel-inner">
    <div class="carousel-item active">
      <img src="..." class="d-block w-100" alt="...">
    </div>
  </div>
</div>
```

**Classes:**
- `.carousel` - Wrapper principal
- `.carousel.slide` - Animação slide
- `.carousel-inner` - Container de slides
- `.carousel-item` - Slide individual
- `.carousel-item.active` - **Obrigatório** - Slide visível
- `.carousel-control-prev` / `.carousel-control-next` - Botões de controle
- `.carousel-control-prev-icon` / `.carousel-control-next-icon` - Ícones
- `.carousel-indicators` - Navegação por dots
- `.carousel-caption` - Legenda do slide
- `.carousel-fade` - Crossfade ao invés de slide

**Data attributes:**

| Attribute | Values | Description |
|-----------|--------|-------------|
| `data-bs-ride` | `"carousel"`, `true`, `false` | Auto-rotate |
| `data-bs-interval` | milliseconds | Tempo entre slides |
| `data-bs-slide` | `"prev"`, `"next"` | Direção do botão |
| `data-bs-slide-to` | index number | Slide alvo |
| `data-bs-target` | `#carouselId` | Target do controle |

**Events:**

| Event | Description |
|-------|-------------|
| `slide.bs.carousel` | Imediatamente quando slide() é invocado |
| `slid.bs.carousel` | Após transição completar |

**Crossfade variant:**

```html
<div id="carouselExampleFade" class="carousel slide carousel-fade">
```

> **Nota:** `.carousel-dark` deprecated em v5.3.0 - use `data-bs-theme="dark"`

**Quando usar neste projeto:** Banner hero em landing page, galeria de certificados, preview de cursos.

---

### Close Button

**Fonte:** [Close Button](https://getbootstrap.com/docs/5.3/components/close-button/)

**Classes:**

```html
<button type="button" class="btn-close" aria-label="Close"></button>
```

**Classes:**
- `.btn-close` - Botão close base
- `.btn-close-white` - **Deprecated v5.3.0** - use `data-bs-theme="dark"`

**States:**

```html
<button type="button" class="btn-close" disabled aria-label="Close"></button>
```

**CSS variables:**

```css
--#{$prefix}btn-close-color
--#{$prefix}btn-close-bg
--#{$prefix}btn-close-opacity: .5
--#{$prefix}btn-close-hover-opacity: .75
--#{$prefix}btn-close-focus-opacity: 1
--#{$prefix}btn-close-disabled-opacity: .25
```

**Quando usar neste projeto:** Close buttons em modais de cadastro, alerts dismissíveis.

---

### Collapse

**Fonte:** [Collapse](https://getbootstrap.com/docs/5.3/components/collapse/)

**Classes:**

```html
<p>
  <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseExample" aria-expanded="false" aria-controls="collapseExample">
    Button with data-bs-target
  </button>
</p>
<div class="collapse" id="collapseExample">
  <div class="card card-body">Content</div>
</div>
```

**Classes:**
- `.collapse` - Esconde conteúdo
- `.collapse.show` - Mostra conteúdo
- `.collapsing` - Durante transições
- `.collapse-horizontal` - Collapse horizontal (width ao invés de height)

**Horizontal collapse:**

```html
<div class="collapse collapse-horizontal" id="collapseWidthExample">
  <div class="card card-body" style="width: 300px;">Content</div>
</div>
```

**Multiple targets:**

```html
<button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target=".multi-collapse">
  Toggle both
</button>
<div class="collapse multi-collapse" id="multiCollapseExample1">...</div>
<div class="collapse multi-collapse" id="multiCollapseExample2">...</div>
```

**Events:**

| Event | Description |
|-------|-------------|
| `show.bs.collapse` | Imediatamente quando show() é chamado |
| `shown.bs.collapse` | Quando collapse visível (após transição) |
| `hide.bs.collapse` | Imediatamente quando hide() é chamado |
| `hidden.bs.collapse` | Quando collapse escondido (após transição) |

**Accessibility:**
- `aria-expanded` (false se fechado, true se aberto)
- `role="button"` em elementos não-`<button>`
- `aria-controls` com id do elemento

**Quando usar neste projeto:** Seções colapsáveis de módulos, filtros avançados, detalhes expandidos de cursos.

---

### Dropdowns

**Fonte:** [Dropdowns](https://getbootstrap.com/docs/5.3/components/dropdowns/)

**Classes básicas:**

```html
<div class="dropdown">
  <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
    Dropdown button
  </button>
  <ul class="dropdown-menu">
    <li><a class="dropdown-item" href="#">Action</a></li>
    <li><a class="dropdown-item" href="#">Another action</a></li>
  </ul>
</div>
```

**Classes:**
- `.dropdown` / `.dropup` / `.dropend` / `.dropstart` - Direction
- `.dropdown-toggle` - Button trigger
- `.dropdown-menu` - Menu container
- `.dropdown-item` - Menu item
- `.dropdown-divider` (deprecated, use `<hr class="dropdown-divider">`)
- `.dropdown-header` - Header text

**Split button:**

```html
<div class="btn-group">
  <button type="button" class="btn btn-danger">Danger</button>
  <button type="button" class="btn btn-danger dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown">
    <span class="visually-hidden">Toggle Dropdown</span>
  </button>
  <ul class="dropdown-menu">...</ul>
</div>
```

**Sizing:**

```html
<button class="btn btn-lg btn-secondary dropdown-toggle">Large</button>
<button class="btn btn-sm btn-secondary dropdown-toggle">Small</button>
```

**Direction variants:**

```html
<div class="dropup">Menu above</div>
<div class="dropend">Menu to right</div>
<div class="dropstart">Menu to left</div>
<div class="dropdown-center">Centered below</div>
<div class="dropup-center">Centered above</div>
```

**Alignment:**

```html
<ul class="dropdown-menu dropdown-menu-end">Right-aligned</ul>
```

**Menu content:**

```html
<ul class="dropdown-menu">
  <li><h6 class="dropdown-header">Dropdown header</h6></li>
  <li><hr class="dropdown-divider"></li>
  <li><a class="dropdown-item active" href="#">Active</a></li>
  <li><a class="dropdown-item disabled">Disabled</a></li>
</ul>
```

**Auto-close behavior:**

| Value | Behavior |
|-------|----------|
| `true` (default) | Inside ou outside click |
| `inside` | Inside click only |
| `outside` | Outside click only |
| `false` | Manual close (Esc ainda funciona) |

```html
<button data-bs-toggle="dropdown" data-bs-auto-close="inside">
```

**Dark dropdowns (deprecated v5.3.0):**

```html
<!-- Use data-bs-theme="dark" instead -->
<ul class="dropdown-menu dropdown-menu-dark">
```

**Events:**

| Event | Description |
|-------|-------------|
| `show.bs.dropdown` | Imediatamente quando show() é chamado |
| `shown.bs.dropdown` | Quando dropdown visível |
| `hide.bs.dropdown` | Imediatamente quando hide() é chamado |
| `hidden.bs.dropdown` | Quando dropdown escondido |

**Quando usar neste projeto:** Menu de usuário, ações bulk em tabelas, filtros de categorias.

---

### List Group

**Fonte:** [List Group](https://getbootstrap.com/docs/5.3/components/list-group/)

**Classes básicas:**

```html
<ul class="list-group">
  <li class="list-group-item">An item</li>
  <li class="list-group-item">A second item</li>
</ul>
```

**States:**

```html
<li class="list-group-item active" aria-current="true">Active</li>
<a href="#" class="list-group-item list-group-item-action disabled" aria-disabled="true">Disabled</a>
```

**Interactive items:**

```html
<div class="list-group">
  <a href="#" class="list-group-item list-group-item-action active">Active link</a>
  <a href="#" class="list-group-item list-group-item-action">Link</a>
</div>
```

**Layout variants:**

```html
<!-- Flush -->
<ul class="list-group list-group-flush">Edge-to-edge</ul>

<!-- Numbered -->
<ol class="list-group list-group-numbered">Counter</ol>

<!-- Horizontal -->
<ul class="list-group list-group-horizontal">Horizontal</ul>
```

**Contextual variants:**

```html
<li class="list-group-item list-group-item-primary">Primary</li>
<li class="list-group-item list-group-item-success">Success</li>
```

**With badges:**

```html
<ul class="list-group">
  <li class="list-group-item d-flex justify-content-between align-items-center">
    A list item
    <span class="badge text-bg-primary rounded-pill">14</span>
  </li>
</ul>
```

**Custom content:**

```html
<div class="list-group">
  <a href="#" class="list-group-item list-group-item-action active">
    <div class="d-flex w-100 justify-content-between">
      <h5 class="mb-1">Heading</h5>
      <small>3 days ago</small>
    </div>
    <p class="mb-1">Some placeholder content.</p>
  </a>
</div>
```

**Quando usar neste projeto:** Lista de aulas em módulo, lista de discussões em forum, lista de alunos em curso.

---

### Modal

**Fonte:** [Modal](https://getbootstrap.com/docs/5.3/components/modal/)

**Classes e estrutura:**

```html
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5" id="exampleModalLabel">Modal title</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Modal body text goes here.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary">Save changes</button>
      </div>
    </div>
  </div>
</div>
```

**Classes:**
- `.modal` - Container principal
- `.modal-dialog` - Dialog wrapper
- `.modal-content` - Conteúdo do modal
- `.modal-header` - Header
- `.modal-title` - Título
- `.modal-body` - Body
- `.modal-footer` - Footer
- `.fade` - Animação (opcional)

**Data attributes:**

```html
<!-- Toggle -->
<button type="button" data-bs-toggle="modal" data-bs-target="#myModal">Launch</button>

<!-- Dismiss -->
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
```

**Sizing variants:**

| Size | Class | Max-width |
|------|-------|-----------|
| Small | `.modal-sm` | 300px |
| Default | None | 500px |
| Large | `.modal-lg` | 800px |
| XL | `.modal-xl` | 1140px |

```html
<div class="modal-dialog modal-sm">...</div>
```

**Fullscreen modal:**

| Class | Availability |
|-------|--------------|
| `.modal-fullscreen` | Always |
| `.modal-fullscreen-sm-down` | Below 576px |
| `.modal-fullscreen-md-down` | Below 768px |
| `.modal-fullscreen-lg-down` | Below 992px |
| `.modal-fullscreen-xl-down` | Below 1200px |

**Scrollable modal:**

```html
<div class="modal-dialog modal-dialog-scrollable">...</div>
```

**Vertically centered:**

```html
<div class="modal-dialog modal-dialog-centered">...</div>
```

**Static backdrop:**

```html
<div class="modal fade" id="staticBackdrop" data-bs-backdrop="static" data-bs-keyboard="false">
```

**Accessibility (ARIA):**

```html
<div class="modal" id="myModal" aria-labelledby="myModalTitle" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title" id="myModalTitle">Modal title</h1>
      </div>
    </div>
  </div>
</div>
```

**Events:**

| Event | Description |
|-------|-------------|
| `show.bs.modal` | Imediatamente quando show() é chamado |
| `shown.bs.modal` | Quando modal visível (após transição) |
| `hide.bs.modal` | Imediatamente quando hide() é chamado |
| `hidden.bs.modal` | Quando modal escondido (após transição) |
| `hidePrevented.bs.modal` | Quando backdrop é static e click fora ocorre |

**JavaScript API methods:**

| Method | Description |
|--------|-------------|
| `dispose()` | Destroy modal |
| `getInstance()` | Get modal instance |
| `getOrCreateInstance()` | Get or create instance |
| `handleUpdate()` | Readjust position after height change |
| `hide()` | Hide modal |
| `show()` | Show modal |
| `toggle()` | Toggle modal |

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `backdrop` | boolean, `'static'` | `true` | Backdrop element |
| `focus` | boolean | `true` | Focus on modal when initialized |
| `keyboard` | boolean | `true` | Close on escape key |

**Quando usar neste projeto:** Modais de criação/edição de cursos, modais de confirmação de exclusão, modais de preview de aula.

---

### Navbar

**Fonte:** [Navbar](https://getbootstrap.com/docs/5.3/components/navbar/)

**Classes e estrutura:**

```html
<nav class="navbar navbar-expand-lg bg-body-tertiary">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Navbar</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarContent">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link active" aria-current="page" href="#">Home</a>
        </li>
      </ul>
    </div>
  </div>
</nav>
```

**Classes:**
- `.navbar` - Base wrapper
- `.navbar-expand{-sm|-md|-lg|-xl|-xxl}` - Responsive breakpoint
- `.navbar-light` / `data-bs-theme="dark"` - Theme
- `.navbar-collapse` - Collapsible content
- `.navbar-toggler` - Toggle button
- `.navbar-toggler-icon` - Icon
- `.navbar-brand` - Brand/logo
- `.navbar-nav` - Nav list
- `.nav-item` - Nav item
- `.nav-link` - Nav link

**Navbar brand:**

```html
<!-- Text -->
<a class="navbar-brand" href="#">Navbar</a>

<!-- Image -->
<a class="navbar-brand" href="#">
  <img src="/path/to/logo.svg" alt="Logo" width="30" height="24">
</a>
```

**Color schemes (v5.3+):**

```html
<!-- Dark theme -->
<nav class="navbar bg-dark" data-bs-theme="dark">

<!-- Light theme -->
<nav class="navbar" style="background-color: #e3f2fd;" data-bs-theme="light">
```

**Placement:**

```html
<nav class="navbar fixed-top bg-body-tertiary">Fixed top</nav>
<nav class="navbar fixed-bottom bg-body-tertiary">Fixed bottom</nav>
<nav class="navbar sticky-top bg-body-tertiary">Sticky top</nav>
<nav class="navbar sticky-bottom bg-body-tertiary">Sticky bottom</nav>
```

**Offcanvas navbar:**

```html
<nav class="navbar bg-body-tertiary fixed-top">
  <div class="container-fluid">
    <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title">Offcanvas</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
      </div>
      <div class="offcanvas-body">...</div>
    </div>
  </div>
</nav>
```

**Quando usar neste projeto:** Navegação principal (desktop + mobile offcanvas), navbar administrativa com dropdown de usuário.

---

### Navs & Tabs

**Fonte:** [Navs & Tabs](https://getbootstrap.com/docs/5.3/components/navs-tabs/)

**Classes base:**

```html
<ul class="nav">
  <li class="nav-item">
    <a class="nav-link active" aria-current="page" href="#">Active</a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="#">Link</a>
  </li>
  <li class="nav-item">
    <a class="nav-link disabled" aria-disabled="true">Disabled</a>
  </li>
</ul>
```

**Classes:**
- `.nav` - Base flexbox navigation
- `.nav-item` - Nav item (opcional com `<nav>`)
- `.nav-link` - Nav link styling
- `.active` - Active state
- `.disabled` - Disabled state

**Nav variants:**

```html
<!-- Tabs -->
<ul class="nav nav-tabs">
  <li class="nav-item"><a class="nav-link active" href="#">Active</a></li>
</ul>

<!-- Pills -->
<ul class="nav nav-pills">
  <li class="nav-item"><a class="nav-link active" href="#">Active</a></li>
</ul>

<!-- Underline (v5.3.0) -->
<ul class="nav nav-underline">
  <li class="nav-item"><a class="nav-link active" href="#">Active</a></li>
</ul>
```

**Layout modifiers:**

```html
<ul class="nav nav-pills nav-fill">Proportionally fills space</ul>
<ul class="nav nav-pills nav-justified">Equal-width items</ul>
<ul class="nav justify-content-center">Center</ul>
<ul class="nav flex-column">Vertical</ul>
```

**Dropdown navs:**

```html
<ul class="nav nav-tabs">
  <li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">Dropdown</a>
    <ul class="dropdown-menu">
      <li><a class="dropdown-item" href="#">Action</a></li>
    </ul>
  </li>
</ul>
```

**JavaScript behavior (tabs):**

```html
<ul class="nav nav-tabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#home" role="tab">Home</button>
  </li>
</ul>
<div class="tab-content">
  <div class="tab-pane fade show active" id="home" role="tabpanel">...</div>
</div>
```

**Events:**

| Event | Description |
|-------|-------------|
| `hide.bs.tab` | Antes de tab esconder |
| `hidden.bs.tab` | Após tab escondida |
| `show.bs.tab` | Antes de tab mostrar |
| `shown.bs.tab` | Após tab mostrada |

**Quando usar neste projeto:** Tabs de conteúdo em página de curso, pills de navegação em settings, navegação secundária em dashboards.

---

### Offcanvas

**Fonte:** [Offcanvas](https://getbootstrap.com/docs/5.3/components/offcanvas/)

**Classes:**

```html
<a class="btn btn-primary" data-bs-toggle="offcanvas" href="#offcanvasExample" role="button" aria-controls="offcanvasExample">
  Link with href
</a>

<div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasExample" aria-labelledby="offcanvasExampleLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="offcanvasExampleLabel">Offcanvas</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">Content</div>
</div>
```

**Classes:**
- `.offcanvas` - Base class
- `.offcanvas.show` - Mostra conteúdo
- `.offcanvas-header` - Header
- `.offcanvas-title` - Title
- `.offcanvas-body` - Body
- `.offcanvas-start` - Left side
- `.offcanvas-end` - Right side
- `.offcanvas-top` - Top
- `.offcanvas-bottom` - Bottom

**Placement variants:**
- `.offcanvas-start` - Esquerda
- `.offcanvas-end` - Direita
- `.offcanvas-top` - Topo
- `.offcanvas-bottom` - Base

**Backdrop options:**

```html
<!-- Default backdrop -->
<div class="offcanvas offcanvas-start">

<!-- Static backdrop -->
<div class="offcanvas offcanvas-start" data-bs-backdrop="static">

<!-- No backdrop -->
<div class="offcanvas offcanvas-start" data-bs-backdrop="false">
```

**Body scrolling:**

```html
<div class="offcanvas offcanvas-start" data-bs-scroll="true">
```

**Responsiveness:**

```html
<button class="btn btn-primary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasResponsive">
  Toggle
</button>

<div class="offcanvas-lg offcanvas-end" tabindex="-1" id="offcanvasResponsive">
  <!-- Visível acima de lg, offcanvas abaixo -->
</div>
```

**Events:**

| Event | Description |
|-------|-------------|
| `show.bs.offcanvas` | Imediatamente quando show() é chamado |
| `shown.bs.offcanvas` | Quando offcanvas visível |
| `hide.bs.offcanvas` | Imediatamente quando hide() é chamado |
| `hidden.bs.offcanvas` | Quando offcanvas escondido |

**Quando usar neste projeto:** Menu mobile, sidebar administrativa colapsável, filtros laterais em listagens.

---

### Pagination

**Fonte:** [Pagination](https://getbootstrap.com/docs/5.3/components/pagination/)

**Classes:**

```html
<nav aria-label="Page navigation example">
  <ul class="pagination">
    <li class="page-item"><a class="page-link" href="#">Previous</a></li>
    <li class="page-item"><a class="page-link" href="#">1</a></li>
    <li class="page-item active"><a class="page-link" href="#" aria-current="page">2</a></li>
    <li class="page-item"><a class="page-link" href="#">3</a></li>
    <li class="page-item"><a class="page-link" href="#">Next</a></li>
  </ul>
</nav>
```

**Classes:**
- `.pagination` - Container
- `.page-item` - Wrapper de item
- `.page-link` - Link styling
- `.active` - Current page
- `.disabled` - Disabled state
- `.pagination-lg` - Large
- `.pagination-sm` - Small

**Pagination com ícones:**

```html
<nav aria-label="Page navigation">
  <ul class="pagination">
    <li class="page-item">
      <a class="page-link" href="#" aria-label="Previous">
        <span aria-hidden="true">&laquo;</span>
      </a>
    </li>
    <li class="page-item active">
      <a class="page-link" href="#" aria-current="page">2</a>
    </li>
  </ul>
</nav>
```

**Active state:**

```html
<li class="page-item active">
  <a class="page-link" href="#" aria-current="page">2</a>
</li>

<!-- Ou com span -->
<li class="page-item active">
  <span class="page-link">2</span>
</li>
```

**Disabled state:**

```html
<li class="page-item disabled">
  <a class="page-link" tabindex="-1">Previous</a>
</li>

<!-- Ou com span -->
<li class="page-item disabled">
  <span class="page-link">Previous</span>
</li>
```

**Alignment:**

```html
<ul class="pagination justify-content-center">Center</ul>
<ul class="pagination justify-content-end">Right</ul>
```

**Accessibility:**
- `<nav>` com `aria-label`
- `aria-current="page"` em active
- `aria-label` em links com ícones
- `tabindex="-1"` em disabled links

**Quando usar neste projeto:** Paginação de cursos, paginação de alunos, paginação de discussões de fórum.

---

### Placeholders

**Fonte:** [Placeholders](https://getbootstrap.com/docs/5.3/components/placeholders/)

**Classes:**

```html
<p aria-hidden="true">
  <span class="placeholder col-6"></span>
</p>
```

**Classes:**
- `.placeholder` - Base class
- `.placeholder-lg` - Large
- `.placeholder-sm` - Small
- `.placeholder-xs` - Extra small
- `.placeholder-glow` - Glowing animation
- `.placeholder-wave` - Wave animation

**Color variants:**

```html
<span class="placeholder col-12 bg-primary">Primary</span>
<span class="placeholder col-12 bg-success">Success</span>
```

**Animation:**

```html
<p class="placeholder-glow">
  <span class="placeholder col-12"></span>
</p>

<p class="placeholder-wave">
  <span class="placeholder col-12"></span>
</p>
```

**Width control:**

```html
<span class="placeholder col-6">Grid columns</span>
<span class="placeholder w-75">Width utility</span>
<span class="placeholder" style="width: 25%;">Inline style</span>
```

**Quando usar neste projeto:** Skeleton loading em cards de cursos, placeholders enquanto carrega aulas.

---

### Popovers

**Fonte:** [Popovers](https://getbootstrap.com/docs/5.3/components/popovers/)

**Inicialização (obrigatória):**

```javascript
const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]')
const popoverList = [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl))
```

**HTML:**

```html
<button type="button" class="btn btn-lg btn-danger" 
        data-bs-toggle="popover" 
        data-bs-title="Popover title" 
        data-bs-content="And here's some amazing content.">
  Click to toggle popover
</button>
```

**Directions:**

```html
<button data-bs-toggle="popover" data-bs-placement="top">Top</button>
<button data-bs-toggle="popover" data-bs-placement="right">Right</button>
<button data-bs-toggle="popover" data-bs-placement="bottom">Bottom</button>
<button data-bs-toggle="popover" data-bs-placement="left">Left</button>
```

**Dismissible (focus trigger):**

```html
<a tabindex="0" class="btn btn-lg btn-danger" role="button" 
   data-bs-toggle="popover" 
   data-bs-trigger="focus" 
   data-bs-title="Dismissible popover" 
   data-bs-content="Content">
  Dismissible popover
</a>
```

**Disabled elements:**

```html
<span class="d-inline-block" tabindex="0" 
      data-bs-toggle="popover" 
      data-bs-trigger="hover focus" 
      data-bs-content="Disabled popover">
  <button class="btn btn-primary" type="button" disabled>Disabled</button>
</span>
```

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `animation` | boolean | `true` | CSS fade transition |
| `container` | string/element | `false` | Container element |
| `content` | string/element/function | `''` | Popover content |
| `delay` | number/object | `0` | Delay show/hide (ms) |
| `html` | boolean | `false` | Allow HTML |
| `placement` | string/function | `'right'` | auto, top, bottom, left, right |
| `trigger` | string | `'click'` | click, hover, focus, manual |
| `sanitize` | boolean | `true` | Sanitization |

**Events:**

| Event | Description |
|-------|-------------|
| `show.bs.popover` | Imediatamente quando show é chamado |
| `shown.bs.popover` | Quando popover visível |
| `hide.bs.popover` | Imediatamente quando hide é chamado |
| `hidden.bs.popover` | Quando popover escondido |
| `inserted.bs.popover` | Após template adicionado ao DOM |

**Quando usar neste projeto:** Tooltips explicativos em campos de formulário, contextual help em ícones de informação.

---

### Progress

**Fonte:** [Progress](https://getbootstrap.com/docs/5.3/components/progress/)

**Classes:**

```html
<div class="progress" role="progressbar" aria-label="Basic example" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">
  <div class="progress-bar" style="width: 25%"></div>
</div>
```

**Classes:**
- `.progress` - Wrapper
- `.progress-bar` - Bar visual
- `.progress-bar-striped` - Striped
- `.progress-bar-animated` - Animated stripes
- `.progress-stacked` - Stacked container

**Sizing:**

```html
<!-- Width -->
<div class="progress">
  <div class="progress-bar w-75">75%</div>
</div>

<!-- Height -->
<div class="progress" style="height: 20px;">
  <div class="progress-bar" style="width: 25%"></div>
</div>
```

**Labels:**

```html
<div class="progress">
  <div class="progress-bar" style="width: 25%">25%</div>
</div>
```

**Background variants:**

```html
<div class="progress-bar bg-success" style="width: 25%"></div>
<div class="progress-bar bg-info" style="width: 50%"></div>
<div class="progress-bar bg-warning" style="width: 75%"></div>
<div class="progress-bar bg-danger" style="width: 100%"></div>
```

**Striped:**

```html
<div class="progress-bar progress-bar-striped" style="width: 10%"></div>
```

**Animated:**

```html
<div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 75%"></div>
```

**Multiple/Stacked:**

```html
<div class="progress-stacked">
  <div class="progress" style="width: 15%">
    <div class="progress-bar"></div>
  </div>
  <div class="progress" style="width: 30%">
    <div class="progress-bar bg-success"></div>
  </div>
  <div class="progress" style="width: 20%">
    <div class="progress-bar bg-info"></div>
  </div>
</div>
```

**Quando usar neste projeto:** Barra de progresso de conclusão de curso, loading indicators em upload de CSV.

---

### Scrollspy

**Fonte:** [Scrollspy](https://getbootstrap.com/docs/5.3/components/scrollspy/)

**Classes e data attributes:**

- `.active` - Toggle on anchor elements
- `data-bs-spy="scroll"` - Scrollable container
- `data-bs-target="#navId"` - Associated navigation
- `data-bs-smooth-scroll="true"` - Smooth scrolling
- `data-bs-root-margin="0px 0px -40%"` - Intersection detection

**Com navbar:**

```html
<nav id="navbar-example2" class="navbar bg-body-tertiary px-3 mb-3">
  <ul class="nav nav-pills">
    <li class="nav-item">
      <a class="nav-link" href="#scrollspyHeading1">First</a>
    </li>
  </ul>
</nav>
<div data-bs-spy="scroll" data-bs-target="#navbar-example2" class="scrollspy-example" tabindex="0">
  <h4 id="scrollspyHeading1">First heading</h4>
  <p>...</p>
</div>
```

**Com list group:**

```html
<div class="row">
  <div class="col-4">
    <div id="list-example" class="list-group">
      <a class="list-group-item list-group-item-action" href="#list-item-1">Item 1</a>
    </div>
  </div>
  <div class="col-8">
    <div data-bs-spy="scroll" data-bs-target="#list-example" tabindex="0">
      <h4 id="list-item-1">Item 1</h4>
    </div>
  </div>
</div>
```

**Events:**

| Event | Description |
|-------|-------------|
| `activate.bs.scrollspy` | Quando anchor é ativado |

**Quando usar neste projeto:** Sidebar de conteúdo em páginas longas (documentação), table of contents em módulos.

---

### Spinners

**Fonte:** [Spinners](https://getbootstrap.com/docs/5.3/components/spinners/)

**Classes:**

```html
<div class="spinner-border" role="status">
  <span class="visually-hidden">Loading...</span>
</div>

<div class="spinner-grow" role="status">
  <span class="visually-hidden">Loading...</span>
</div>
```

**Colors:**

```html
<div class="spinner-border text-primary">Primary</div>
<div class="spinner-border text-secondary">Secondary</div>
<div class="spinner-border text-success">Success</div>
```

**Sizing:**

```html
<div class="spinner-border spinner-border-sm">Small</div>
<div class="spinner-border" style="width: 3rem; height: 3rem;">Custom</div>
```

**Buttons:**

```html
<button class="btn btn-primary" type="button" disabled>
  <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
  <span class="visually-hidden" role="status">Loading...</span>
</button>

<button class="btn btn-primary" type="button" disabled>
  <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
  <span role="status">Loading...</span>
</button>
```

**Quando usar neste projeto:** Loading states em botões de submissão, loading em modais de upload.

---

### Toasts

**Fonte:** [Toasts](https://getbootstrap.com/docs/5.3/components/toasts/)

**Classes e estrutura:**

```html
<div class="toast" role="alert" aria-live="assertive" aria-atomic="true">
  <div class="toast-header">
    <img src="..." class="rounded me-2" alt="...">
    <strong class="me-auto">Bootstrap</strong>
    <small>11 mins ago</small>
    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
  </div>
  <div class="toast-body">
    Hello, world!
  </div>
</div>
```

**Classes:**
- `.toast` - Main container
- `.toast-header` - Header
- `.toast-body` - Body
- `.toast-container` - Stacking wrapper

**Dismissal:**

```html
<!-- Within toast -->
<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>

<!-- Outside toast -->
<button type="button" class="btn-close" data-bs-dismiss="toast" data-bs-target="#my-toast" aria-label="Close"></button>
```

**Placement:**

```html
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div class="toast">...</div>
</div>
```

**Autohide behavior:**

```html
<!-- Default (5 seconds) -->
<div class="toast" data-bs-autohide="true" data-bs-delay="5000">

<!-- Manual dismiss -->
<div class="toast" data-bs-autohide="false">
```

**Stacking:**

```html
<div class="toast-container">
  <div class="toast">...</div>
  <div class="toast">...</div>
</div>
```

**Variants:**

```html
<!-- Primary -->
<div class="toast align-items-center text-bg-primary border-0" role="alert">
  <div class="d-flex">
    <div class="toast-body">Hello, world!</div>
    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
  </div>
</div>
```

**Accessibility:**

```html
<!-- Important messages (errors) -->
<div class="toast" role="alert" aria-live="assertive" aria-atomic="true">

<!-- Non-critical -->
<div class="toast" role="status" aria-live="polite" aria-atomic="true">
```

**Events:**

| Event | Description |
|-------|-------------|
| `show.bs.toast` | Imediatamente quando show() é chamado |
| `shown.bs.toast` | Quando toast visível |
| `hide.bs.toast` | Imediatamente quando hide() é chamado |
| `hidden.bs.toast` | Quando toast escondido |

**JavaScript methods:**

```javascript
const toastBootstrap = bootstrap.Toast.getOrCreateInstance(toastEl)
toastBootstrap.show()
toastBootstrap.hide()
toastBootstrap.isShown() // returns boolean
toastBootstrap.dispose()
```

**Quando usar neste projeto:** Notificações de conclusão de aula, toasts de sucesso em ações CRUD, alerts de erro em formulários.

---

### Tooltips

**Fonte:** [Tooltips](https://getbootstrap.com/docs/5.3/components/tooltips/)

**Inicialização (obrigatória):**

```javascript
const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
const tooltipList = [...tooltipTriggerList].map(
  tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl)
)
```

**HTML:**

```html
<button type="button" class="btn btn-secondary" 
        data-bs-toggle="tooltip" 
        data-bs-placement="top" 
        data-bs-title="Tooltip on top">
  Tooltip on top
</button>
```

**Directions:**

```html
<button data-bs-toggle="tooltip" data-bs-placement="top">Top</button>
<button data-bs-toggle="tooltip" data-bs-placement="right">Right</button>
<button data-bs-toggle="tooltip" data-bs-placement="bottom">Bottom</button>
<button data-bs-toggle="tooltip" data-bs-placement="left">Left</button>
```

**Custom class (v5.2.0+):**

```css
.custom-tooltip {
  --bs-tooltip-bg: var(--bd-violet-bg);
  --bs-tooltip-color: var(--bs-white);
}
```

```html
<button data-bs-toggle="tooltip" 
        data-bs-custom-class="custom-tooltip"
        data-bs-title="Custom tooltip">
  Custom tooltip
</button>
```

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `animation` | boolean | `true` | CSS fade transition |
| `container` | string/element | `false` | Container element |
| `delay` | number/object | `0` | Delay show/hide (ms) |
| `html` | boolean | `false` | Allow HTML |
| `placement` | string/function | `'top'` | auto, top, bottom, left, right |
| `sanitize` | boolean | `true` | Sanitization |
| `trigger` | string | `'hover focus'` | click, hover, focus, manual |
| `customClass` | string/function | `''` | Add classes |

**Events:**

| Event | Description |
|-------|-------------|
| `show.bs.tooltip` | Imediatamente quando show() é chamado |
| `shown.bs.tooltip` | Quando tooltip visível |
| `hide.bs.tooltip` | Imediatamente quando hide() é chamado |
| `hidden.bs.tooltip` | Quando tooltip escondido |
| `inserted.bs.tooltip` | Após template adicionado ao DOM |

**Quando usar neste projeto:** Help text em ícones informativos, tooltips de validação em campos, explicações em ações de botões.

---

## Helpers

### Color & Background

**Fonte:** [Color & Background](https://getbootstrap.com/docs/5.3/helpers/color-background/)

**Classes combined `.text-bg-*`:**

```html
<div class="text-bg-primary p-3">Primary with contrasting color</div>
<div class="text-bg-success p-3">Success with contrasting color</div>
```

**Available:**
- `.text-bg-primary`, `.text-bg-secondary`, `.text-bg-success`
- `.text-bg-danger`, `.text-bg-warning`, `.text-bg-info`
- `.text-bg-light`, `.text-bg-dark`

**Em componentes:**

```html
<span class="badge text-bg-primary">Primary</span>
<div class="card text-bg-primary mb-3">Primary card</div>
```

> **Nota:** Color alone não transmite significado - use adicionalmente `.visually-hidden` para screen readers.

**Quando usar neste projeto:** Badges de categorias, cards de status, alertas de sistema.

---

### Position

**Fonte:** [Position](https://getbootstrap.com/docs/5.3/helpers/position/)

**Classes:**

```html
<!-- Fixed positioning -->
<div class="fixed-top">Fixed top</div>
<div class="fixed-bottom">Fixed bottom</div>

<!-- Sticky positioning -->
<div class="sticky-top">Sticky top</div>
<div class="sticky-bottom">Sticky bottom</div>
```

**Responsive sticky:**

```html
<div class="sticky-sm-top">Stick to top on SM+</div>
<div class="sticky-md-top">Stick to top on MD+</div>
<div class="sticky-lg-top">Stick to top on LG+</div>
```

**Classes disponíveis:**
- `.fixed-top`, `.fixed-bottom`
- `.sticky-top`, `.sticky-bottom`
- `.sticky-{sm,md,lg,xl,xxl}-top`
- `.sticky-{sm,md,lg,xl,xxl}-bottom`

**Quando usar neste projeto:** Navbar fixa no topo, sidebar sticky em dashboards, header fixo em player de aula.

---

### Stacks

**Fonte:** [Stacks](https://getbootstrap.com/docs/5.3/helpers/stacks/)

**Classes:**

```html
<!-- Vertical stack (.vstack) -->
<div class="vstack gap-3">
  <div class="p-2">First item</div>
  <div class="p-2">Second item</div>
</div>

<!-- Horizontal stack (.hstack) -->
<div class="hstack gap-3">
  <div class="p-2">First item</div>
  <div class="p-2">Second item</div>
</div>
```

**Horizontal with margin auto:**

```html
<div class="hstack gap-3">
  <div class="p-2">First item</div>
  <div class="p-2 ms-auto">Pushed to right</div>
</div>
```

**With vertical rule:**

```html
<div class="hstack gap-3">
  <div class="p-2">First item</div>
  <div class="vr"></div>
  <div class="p-2">Third item</div>
</div>
```

**CSS implementation:**

```scss
.hstack {
  display: flex;
  flex-direction: row;
  align-items: center;
  align-self: stretch;
}

.vstack {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  align-self: stretch;
}
```

**Quando usar neste projeto:** Stacks de botões de ação, form layouts verticais, inline forms com botões alinhados.

---

### Stretched Link

**Fonte:** [Stretched Link](https://getbootstrap.com/docs/5.3/helpers/stretched-link/)

**Classes:**

```html
<!-- Card com stretched link -->
<div class="card" style="width: 18rem;">
  <img src="..." class="card-img-top" alt="...">
  <div class="card-body">
    <h5 class="card-title">Card with stretched link</h5>
    <p class="card-text">Some quick example text.</p>
    <a href="#" class="btn btn-primary stretched-link">Go somewhere</a>
  </div>
</div>
```

**Custom component (requer .position-relative):**

```html
<div class="d-flex position-relative">
  <img src="..." class="flex-shrink-0 me-3" alt="...">
  <div>
    <h5 class="mt-0">Custom component</h5>
    <p>Placeholder content.</p>
    <a href="#" class="stretched-link">Go somewhere</a>
  </div>
</div>
```

**Limitações:**
- Não funciona com elementos de table
- Multiple links não recomendados
- Elemento com link não pode ter `position: relative`

**Quando usar neste projeto:** Cards clicáveis de curso, lista items clicáveis de aulas, banners hero com CTA whole-card.

---

### Vertical Rule

**Fonte:** [Vertical Rule](https://getbootstrap.com/docs/5.3/helpers/vertical-rule/)

**Classes:**

```html
<div class="vr"></div>
```

**Em flex layouts (auto-scaling height):**

```html
<div class="d-flex" style="height: 200px;">
  <div class="vr"></div>
</div>
```

**Com stacks:**

```html
<div class="hstack gap-3">
  <div class="p-2">First item</div>
  <div class="vr"></div>
  <div class="p-2">Second item</div>
</div>
```

**Sass variable:**

```scss
$vr-border-width: var(--#{$prefix}border-width);
```

**Quando usar neste projeto:** Divisores em navbars, separadores em footers, divisão visual em dashboards.

---

### Visually Hidden

**Fonte:** [Visually Hidden](https://getbootstrap.com/docs/5.3/helpers/visually-hidden/)

**Classes:**

```html
<!-- Visually hidden (screen readers only) -->
<h2 class="visually-hidden">Title for screen readers</h2>

<!-- Focusable (visible when focused) -->
<a class="visually-hidden-focusable" href="#content">Skip to main content</a>
```

**Sass mixins:**

```scss
.visually-hidden-title {
  @include visually-hidden;
}

.skip-navigation {
  @include visually-hidden-focusable;
}
```

**Use cases:**
- "Skip to main content" links
- Labels para screen readers
- Hidden content visível ao focar

**Quando usar neste projeto:** Skip links em páginas, labels de formulários hidden, aria-labels para ícones.

---

### Ratio

**Fonte:** [Ratio](https://getbootstrap.com/docs/5.3/helpers/ratio/)

**Classes:**

```html
<div class="ratio ratio-16x9">
  <iframe src="..."></iframe>
</div>
```

**Built-in ratios:**

| Class | Aspect Ratio |
|-------|--------------|
| `.ratio-1x1` | 1:1 (Square) |
| `.ratio-4x3` | 4:3 (Standard) |
| `.ratio-16x9` | 16:9 (Widescreen) |
| `.ratio-21x9` | 21:9 (Ultrawide) |

**Custom ratio:**

```html
<!-- 2x1 ratio -->
<div class="ratio" style="--bs-aspect-ratio: 50%;">
  <div>2x1</div>
</div>
```

**Responsive changes:**

```scss
.ratio-4x3 {
  @include media-breakpoint-up(md) {
    --bs-aspect-ratio: 50%; // Changes to 2x1 at md
  }
}
```

**Quando usar neste projeto:** Iframes de vídeo embed, thumbnails de cursos em grid, player de vídeo responsivo.

---

## Utilities

### Background

**Fonte:** [Background](https://getbootstrap.com/docs/5.3/utilities/background/)

**Classes:**

```html
<div class="p-3 mb-2 bg-primary text-white">.bg-primary</div>
<div class="p-3 mb-2 bg-success text-white">.bg-success</div>
```

**Subtle variants (color-mode adaptive):**

```html
<div class="p-3 mb-2 bg-primary-subtle">Primary subtle</div>
<div class="p-3 mb-2 bg-body-tertiary">Body tertiary</div>
```

**Background gradient:**

```html
<div class="p-3 mb-2 bg-primary bg-gradient text-white">.bg-primary.bg-gradient</div>
```

**Background opacity (v5.1.0+):**

```html
<!-- Com CSS variable -->
<div class="bg-success p-2" style="--bs-bg-opacity: .5;">50% opacity</div>

<!-- Com utility classes -->
<div class="bg-success p-2 bg-opacity-75">75% opacity</div>
<div class="bg-success p-2 bg-opacity-50">50% opacity</div>
<div class="bg-success p-2 bg-opacity-25">25% opacity</div>
```

**Valores disponíveis:**
- `.bg-opacity-10`, `.bg-opacity-25`, `.bg-opacity-50`
- `.bg-opacity-75`, `.bg-opacity-100`

**Como funciona:**

```css
.bg-success {
  --bs-bg-opacity: 1;
  background-color: rgba(var(--bs-success-rgb), var(--bs-bg-opacity)) !important;
}
```

**Quando usar neste projeto:** Backgrounds de cards de status, overlays de imagens, badges com transparência.

---

### Borders

**Fonte:** [Borders](https://getbootstrap.com/docs/5.3/utilities/borders/)

**Additive (add borders):**

```html
<span class="border">All borders</span>
<span class="border-top">Top only</span>
<span class="border-end">Right only</span>
<span class="border-bottom">Bottom only</span>
<span class="border-start">Left only</span>
```

**Subtractive (remove borders):**

```html
<span class="border border-0">Remove all</span>
<span class="border border-top-0">Remove top</span>
```

**Border color:**

```html
<span class="border border-primary">Primary</span>
<span class="border border-secondary">Secondary</span>
```

**Border opacity (v5.2.0+):**

```html
<div class="border border-success border-opacity-75">75% opacity</div>
<div class="border border-success border-opacity-50">50% opacity</div>
```

**Border width:**

```html
<span class="border border-1">1px</span>
<span class="border border-2">2px</span>
<span class="border border-3">3px</span>
<span class="border border-4">4px</span>
<span class="border border-5">5px</span>
```

**Border radius (rounded corners):**

```html
<img class="rounded" alt="...">
<img class="rounded-top" alt="...">
<img class="rounded-circle" alt="...">
<img class="rounded-pill" alt="...">
```

**Size variants:**

```html
<img class="rounded-0" alt="...">  <!-- No radius -->
<img class="rounded-1" alt="...">  /* Small (.25rem) */
<img class="rounded-2" alt="...">  /* Default (.375rem) */
<img class="rounded-3" alt="...">  /* Large (.5rem) */
<img class="rounded-4" alt="...">  /* XL (1rem) */
<img class="rounded-5" alt="...">  /* XXL (2rem) */
```

**Sass variables:**

```scss
$border-radius: .375rem;
$border-radius-sm: .25rem;
$border-radius-lg: .5rem;
$border-radius-xl: 1rem;
$border-radius-xxl: 2rem;
$border-radius-pill: 50rem;
```

**Quando usar neste projeto:** Bordas em cards de curso, imagens arredondadas de avatares, bordas coloridas em alerts.

---

### Display

**Fonte:** [Display](https://getbootstrap.com/docs/5.3/utilities/display/)

**Classes:**

```html
<!-- Regular -->
<div class="d-flex p-2">Flexbox container</div>

<!-- Inline -->
<div class="d-inline-flex p-2">Inline flexbox</div>
```

**Responsive:**

```html
<div class="d-sm-flex">Flex em sm+</div>
<div class="d-md-flex">Flex em md+</div>
<div class="d-lg-flex">Flex em lg+</div>
```

**Hiding responsivamente:**

```html
<!-- Hide on lg and wider -->
<div class="d-lg-none">Hide on lg+</div>

<!-- Hide on smaller than lg -->
<div class="d-none d-lg-block">Hide up to lg</div>
```

**Display in print:**

```html
<div class="d-print-none">Screen only</div>
<div class="d-none d-print-block">Print only</div>
```

**Valores disponíveis:**
- `.d-none`, `.d-inline`, `.d-inline-block`
- `.d-block`, `.d-grid`, `.d-inline-grid`
- `.d-table`, `.d-table-cell`, `.d-table-row`
- `.d-flex`, `.d-inline-flex`

**Quando usar neste projeto:** Display utilities para layouts responsivos, esconder/mostrar elementos por breakpoint, print styles para PDF.

---

### Flex

**Fonte:** [Flex](https://getbootstrap.com/docs/5.3/utilities/flex/)

**Enable flex behaviors:**

```html
<div class="d-flex p-2">I'm a flexbox container!</div>
<div class="d-inline-flex p-2">I'm an inline flexbox container!</div>
```

**Direction:**

```html
<div class="d-flex flex-row">Horizontal (default)</div>
<div class="d-flex flex-row-reverse">Horizontal reversed</div>
<div class="d-flex flex-column">Vertical</div>
<div class="d-flex flex-column-reverse">Vertical reversed</div>
```

**Justify content:**

```html
<div class="d-flex justify-content-start">...</div>
<div class="d-flex justify-content-end">...</div>
<div class="d-flex justify-content-center">...</div>
<div class="d-flex justify-content-between">...</div>
<div class="d-flex justify-content-around">...</div>
<div class="d-flex justify-content-evenly">...</div>
```

**Align items:**

```html
<div class="d-flex align-items-start">...</div>
<div class="d-flex align-items-end">...</div>
<div class="d-flex align-items-center">...</div>
<div class="d-flex align-items-baseline">...</div>
<div class="d-flex align-items-stretch">...</div>
```

**Align self:**

```html
<div class="align-self-start">Aligned flex item</div>
<div class="align-self-end">Aligned flex item</div>
```

**Flex wrap:**

```html
<div class="d-flex flex-nowrap">No wrap</div>
<div class="d-flex flex-wrap">Wrap</div>
<div class="d-flex flex-wrap-reverse">Wrap reverse</div>
```

**Order:**

```html
<div class="order-0">Order 0</div>
<div class="order-1">Order 1</div>
<div class="order-first">First</div>
<div class="order-last">Last</div>
```

**Grow and shrink:**

```html
<div class="flex-grow-0">No grow</div>
<div class="flex-grow-1">Grow</div>
<div class="flex-shrink-0">No shrink</div>
<div class="flex-shrink-1">Shrink</div>
```

**Quando usar neste projeto:** Layouts de navbar, alinhamento de elementos em cards, distribuição de espaço em dashboards.

---

### Shadows

**Fonte:** [Shadows](https://getbootstrap.com/docs/5.3/utilities/shadows/)

**Classes:**

```html
<div class="shadow-none p-3 mb-5 bg-body-tertiary rounded">No shadow</div>
<div class="shadow-sm p-3 mb-5 bg-body-tertiary rounded">Small shadow</div>
<div class="shadow p-3 mb-5 bg-body-tertiary rounded">Regular shadow</div>
<div class="shadow-lg p-3 mb-5 bg-body-tertiary rounded">Larger shadow</div>
```

**Classes disponíveis:**
- `.shadow-none` - No shadow
- `.shadow-sm` - Small
- `.shadow` - Regular (default)
- `.shadow-lg` - Large

**Sass variables:**

```scss
$box-shadow: 0 .5rem 1rem rgba($black, .15);
$box-shadow-sm: 0 .125rem .25rem rgba($black, .075);
$box-shadow-lg: 0 1rem 3rem rgba($black, .175);
$box-shadow-inset: inset 0 1px 2px rgba($black, .075);
```

**Nota:** Shadows em componentes são desabilitadas por default em Bootstrap. Enable via `$enable-shadows`.

**Quando usar neste projeto:** Elevação em cards de curso ao hover, depth em modais, sombras em dropdowns.

---

### Sizing

**Fonte:** [Sizing](https://getbootstrap.com/docs/5.3/utilities/sizing/)

**Width utilities (relativo ao parent):**

```html
<div class="w-25 p-3">Width 25%</div>
<div class="w-50 p-3">Width 50%</div>
<div class="w-75 p-3">Width 75%</div>
<div class="w-100 p-3">Width 100%</div>
<div class="w-auto p-3">Width auto</div>
```

**Height utilities:**

```html
<div class="h-25 d-inline-block">Height 25%</div>
<div class="h-50 d-inline-block">Height 50%</div>
<div class="h-75 d-inline-block">Height 75%</div>
<div class="h-100 d-inline-block">Height 100%</div>
<div class="h-auto d-inline-block">Height auto</div>
```

**Max-width/Max-height:**

```html
<div class="mw-100">Max-width 100%</div>
<div class="mh-100">Max-height 100%</div>
```

**Viewport sizing:**

```html
<div class="vw-100">Width 100vw</div>
<div class="vh-100">Height 100vh</div>
<div class="min-vw-100">Min-width 100vw</div>
<div class="min-vh-100">Min-height 100vh</div>
```

**Quando usar neste projeto:** Larguras específicas de sidebar, alturas de containers, full viewport sections.

---

### Spacing

**Fonte:** [Spacing](https://getbootstrap.com/docs/5.3/utilities/spacing/)

**Notação:** `{property}{sides}-{size}` (xs) ou `{property}{sides}-{breakpoint}-{size}` (sm+)

**Properties:**
- `m` - margin
- `p` - padding

**Sides:**
- `t` - top
- `b` - bottom
- `s` - start (left em LTR, right em RTL)
- `e` - end (right em LTR, left em LTR)
- `x` - left e right
- `y` - top e bottom
- (blank) - todos os 4 lados

**Size values:**

| Class | Value |
|-------|-------|
| `0` | `0` |
| `1` | `$spacer * .25` (0.25rem) |
| `2` | `$spacer * .5` (0.5rem) |
| `3` | `$spacer` (1rem) |
| `4` | `$spacer * 1.5` (1.5rem) |
| `5` | `$spacer * 3` (3rem) |
| `auto` | `auto` (margin only) |

**Examples:**

```html
.mt-0 { margin-top: 0; }
.ms-1 { margin-left: 0.25rem; }
.px-2 { padding-left: 0.5rem; padding-right: 0.5rem; }
.p-3 { padding: 1rem; }
```

**Horizontal centering:**

```html
<div class="mx-auto p-2" style="width: 200px;">Centered element</div>
```

**Negative margins:**

Enable via `$enable-negative-margins: true` em Sass.

```css
.mt-n1 { margin-top: -0.25rem; }
```

**Gap utilities (grid/flex):**

```html
<div class="d-grid gap-3">...</div>
```

**Valores de gap:** `gap-0` through `gap-5`, `row-gap-{0-5}`, `column-gap-{0-5}`

**Quando usar neste projeto:** Spacing consistente entre cards, padding em containers, margin entre seções.

---

### Text

**Fonte:** [Text](https://getbootstrap.com/docs/5.3/utilities/text/)

**Text alignment:**

```html
<p class="text-start">Start aligned</p>
<p class="text-center">Center aligned</p>
<p class="text-end">End aligned</p>
```

**Responsive:**

```html
<p class="text-sm-end">End aligned on SM+</p>
```

**Text wrapping:**

```html
<div class="text-wrap">Wrap text</div>
<div class="text-nowrap">No wrap</div>
```

**Word break:**

```html
<p class="text-break">mmmmmmmmmmmmmmmmmm</p>
```

**Text transform:**

```html
<p class="text-lowercase">Lowercased</p>
<p class="text-uppercase">Uppercased</p>
<p class="text-capitalize">Capitalized</p>
```

**Font size:**

```html
<p class="fs-1">.fs-1 (largest)</p>
<p class="fs-2">.fs-2</p>
<p class="fs-3">.fs-3</p>
<p class="fs-4">.fs-4</p>
<p class="fs-5">.fs-5</p>
<p class="fs-6">.fs-6 (smallest)</p>
```

**Font weight:**

```html
<p class="fw-lighter">Lighter</p>
<p class="fw-light">Light</p>
<p class="fw-normal">Normal</p>
<p class="fw-medium">Medium</p>
<p class="fw-semibold">Semibold</p>
<p class="fw-bold">Bold</p>
<p class="fw-bolder">Bolder</p>
```

**Line height:**

```html
<p class="lh-1">Line height 1</p>
<p class="lh-sm">Small line height</p>
<p class="lh-base">Base line height</p>
<p class="lh-lg">Large line height</p>
```

**Text decoration:**

```html
<p class="text-decoration-underline">Underlined</p>
<p class="text-decoration-line-through">Strikethrough</p>
<a href="#" class="text-decoration-none">No decoration link</a>
```

**Monospace:**

```html
<p class="font-monospace">Monospace text</p>
```

**Reset color:**

```html
<p class="text-body-secondary">
  Secondary text with a <a href="#" class="text-reset">reset link</a>.
</p>
```

**Quando usar neste projeto:** Styling de headings em landing pages, alinhamento de text em cards, transform de text em botões.

---

## Padrão Laravel + Bootstrap

### Integração de Validação

**Mapeamento de `$errors` do Laravel para classes Bootstrap:**

```blade
<!-- Campo com erro -->
<div class="mb-3">
  <label for="email" class="form-label">Email</label>
  <input type="email" 
         class="form-control @error('email') is-invalid @else is-valid @enderror" 
         id="email" 
         name="email" 
         value="{{ old('email') }}"
         aria-describedby="{{ $errors->has('email') ? 'emailFeedback' : '' }}">
  
  @error('email')
    <div id="emailFeedback" class="invalid-feedback">
      {{ $message }}
    </div>
  @enderror
</div>

<!-- Select com erro -->
<div class="mb-3">
  <label for="category" class="form-label">Categoria</label>
  <select class="form-select @error('category') is-invalid @else is-valid @enderror" 
          id="category" 
          name="category">
    <option value="">Selecione...</option>
    <option value="1" {{ old('category') == 1 ? 'selected' : '' }}>Categoria 1</option>
  </select>
  
  @error('category')
    <div class="invalid-feedback">{{ $message }}</div>
  @enderror
</div>
```

**Input groups com validação:**

```blade
<div class="input-group has-validation @error('cpf') is-invalid @enderror">
  <span class="input-group-text">CPF</span>
  <input type="text" 
         class="form-control" 
         id="cpf" 
         name="cpf" 
         value="{{ old('cpf') }}"
         aria-describedby="{{ $errors->has('cpf') ? 'cpfFeedback' : '' }}">
  
  @error('cpf')
    <div id="cpfFeedback" class="invalid-feedback">{{ $message }}</div>
  @enderror
</div>
```

**Padrão reutilizável como Blade component:**

```blade
<!-- resources/views/components/input.blade.php -->
@props(['name', 'label', 'value' => null, 'type' => 'text'])

<div class="mb-3">
  <label for="{{ $name }}" class="form-label">{{ $label }}</label>
  <input type="{{ $type }}" 
         class="form-control @error($name) is-invalid @enderror" 
         id="{{ $name }}" 
         name="{{ $name }}" 
         value="{{ $value ?? old($name) }}"
         {{ $attributes }}>
  
  @error($name)
    <div class="invalid-feedback">{{ $message }}</div>
  @enderror
</div>
```

**Uso:**

```blade
<x-input name="email" label="Email" type="email" :value="$user->email" />
```

---

### Integração de Paginação

**Laravel Paginator com Bootstrap 5:**

```php
// app/Providers/AppServiceProvider.php

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Paginator::useBootstrapFive();
    }
}
```

**Renderização nas views:**

```blade
<!-- courses/index.blade.php -->
<div class="container">
  <div class="row">
    @foreach ($courses as $course)
      <div class="col-md-4">
        <!-- card do curso -->
      </div>
    @endforeach
  </div>
  
  <!-- Paginação -->
  {{ $courses->links() }}
</div>
```

**Paginação customizada:**

```blade
<!-- resources/views/pagination/bootstrap-5.blade.php (exportado via vendor:publish) -->
@if ($paginator->hasPages())
    <nav aria-label="Page navigation">
        <ul class="pagination">
            @if ($paginator->onFirstPage())
                <li class="page-item disabled"><span class="page-link">Anterior</span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $paginator->previousPageUrl() }}">Anterior</a></li>
            @endif
            
            @foreach ($paginator->elements as $element)
                @if (is_string($element))
                    <li class="page-item disabled"><span class="page-link">{{ $element }}</span></li>
                @elseif ($element->isActive())
                    <li class="page-item active"><span class="page-link">{{ $element->page }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $element->url }}">{{ $element->page }}</a></li>
                @endif
            @endforeach
            
            @if ($paginator->hasMorePages())
                <li class="page-item"><a class="page-link" href="{{ $paginator->nextPageUrl() }}">Próximo</a></li>
            @else
                <li class="page-item disabled"><span class="page-link">Próximo</span></li>
            @endif
        </ul>
    </nav>
@endif
```

---

### Flash Messages

**Mapeamento de session messages para Alerts Bootstrap:**

```blade
<!-- resources/views/layouts/app.blade.php (após opening body tag) -->
@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>Erro!</strong> Corrija os campos abaixo.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if (session('warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        {{ session('warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if (session('info'))
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        {{ session('info') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
```

**Component Blade reutilizável:**

```blade
<!-- resources/views/components/alert.blade.php -->
@props(['type' => 'info', 'dismissible' => true])

<div class="alert alert-{{ $type }} @if($dismissible) alert-dismissible fade show @endif" role="alert">
    {{ $slot }}
    
    @if($dismissible)
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    @endif
</div>
```

**Uso:**

```blade
<x-alert type="success">
    Curso criado com sucesso!
</x-alert>

<x-alert type="danger" :dismissible="false">
    Erro ao processar arquivo.
</x-alert>
```

---

### Blade Components com Bootstrap

**Anonymous components com `$attributes->merge()`:**

```blade
<!-- resources/views/components/button.blade.php -->
@props(['variant' => 'primary', 'size' => null])

<button class="btn btn-{{ $variant }} @if($size) btn-{{ $size }} @endif" {{ $attributes->merge(['type' => 'button']) }}>
    {{ $slot }}
</button>
```

**Uso:**

```blade
<!-- Classes são merged, não sobrescritas -->
<x-button variant="primary" class="ms-2">
    Cadastrar
</x-button>

<!-- Output: -->
<button class="btn btn-primary ms-2" type="button">
    Cadastrar
</button>
```

**Component card com $attributes:**

```blade
<!-- resources/views/components/card.blade.php -->
@props(['title', 'variant' => 'light'])

<div class="card text-bg-{{ $variant }} mb-3" {{ $attributes->merge(['class' => 'mb-0']) }}>
    @if($title)
        <div class="card-header">{{ $title }}</div>
    @endif
    
    <div class="card-body">
        {{ $slot }}
    </div>
</div>
```

**Uso:**

```blade
<x-card title="Meu Curso" variant="primary" class="shadow-lg">
    <p>Conteúdo do card...</p>
</x-card>
```

**Component form com $attributes forwarding:**

```blade
<!-- resources/views/components/form.blade.php -->
@props(['action' => null, 'method' => 'POST'])

<form {{ $attributes->merge(['action' => $action, 'method' => $method]) }}>
    {{ $slot }}
    
    @csrf
</form>
```

**Uso:**

```blade
<x-form action="{{ route('courses.store') }}" method="POST" class="row g-3">
    <!-- fields -->
</x-form>
```

---

## Armadilhas / Pitfalls

### jQuery NÃO é Necessário

Bootstrap 5 **não requer jQuery**. Remova qualquer referência se houver migração de BS4/BS3.

**Errado:**
```html
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
```

**Correto:**
```html
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
```

---

### Popper Dependency

**Popper é necessário para:**
- Dropdowns
- Popovers
- Tooltips
- Toast positioning

**Se não usar estes componentes,** pode usar o bundle sem Popper:

```html
<!-- Sem Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js"></script>

<!-- Com Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
```

---

### Nested Modals Não São Suportados

Bootstrap **não suporta modais aninhados**. Use offcanvas ou navegação entre páginas como alternativa.

---

### data-bs-toggle Requer JS

Componentes com `data-bs-toggle` **não funcionam sem o JavaScript do Bootstrap carregado**.

**Verifique:**
1. `bootstrap.bundle.min.js` está incluído
2. Script está após o HTML (ou usa `DOMContentLoaded`)
3. Componentes inicializados se não usando data attributes

```javascript
// Inicialização manual se necessário
const dropdownElementList = document.querySelectorAll('.dropdown-toggle')
const dropdownList = [...dropdownElementList].map(dropdownToggleEl => new bootstrap.Dropdown(dropdownToggleEl))
```

---

### Especificidade CSS vs Utility Classes

Classes utility podem ser sobrescritas por CSS customizado com maior especificidade.

**Problemático:**
```css
/* specificity mais alta */
.my-custom-card .btn {
    padding: 20px !important; /* !required se utility já aplicada */
}
```

**Melhor abordagem:**
```css
/* Crie nova utility ou modifique via Sass */
.btn-custom-padding {
    padding: var(--bs-btn-padding-y) var(--bs-btn-padding-x);
}
```

---

### .table-responsive Wrapper

Tabela responsiva **requer wrapper `.table-responsive`**:

```html
<!-- Funciona em mobile -->
<div class="table-responsive">
    <table class="table table-striped">
        <!-- conteúdo -->
    </table>
</div>
```

Sem o wrapper, tabelas podem quebrar layout mobile.

---

### dompdf e CSS Moderno

**dompdf não suporta CSS moderno** usado por Bootstrap. Para templates de PDF:

1. Use **stylesheets inline e simplificados**
2. Evite: flexbox, grid, CSS variables, `calc()`, media queries complexas
3. Prefira: tables simples, widths fixas, cores inline

**Exemplo de template PDF-friendly:**

```blade
<!-- resources/views/reports/certificate-pdf.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .header { text-align: center; margin-bottom: 30px; }
        .content { padding: 20px; border: 2px solid #333; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Certificado de Conclusão</h1>
    </div>
    <div class="content">
        <p>Certificamos que <strong>{{ $user->name }}</strong> concluiu o curso...</p>
    </div>
</body>
</html>
```

---

### Rounded Corners no Projeto

**Projeto EAD requer `border-radius: 0` system-wide.**

**Via CSS variables:**

```css
/* resources/css/app.scss */
:root {
    --bs-border-radius: 0;
    --bs-border-radius-sm: 0;
    --bs-border-radius-lg: 0;
}
```

**Via Sass (melhor):**

```scss
/* resources/css/bootstrap.scss */
$enable-rounded: false;

@import "bootstrap/scss/bootstrap";
```

**Isso afeta:**
- `.btn`, `.card`, `.modal-content`, `.dropdown-menu`
- `.form-control`, `.form-select`
- Todos os componentes com border-radius

---

### Font Archivo

**Projeto usa Archivo como fonte principal.**

```scss
// resources/css/app.scss
@import url('https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&display=swap');

:root {
    --bs-body-font-family: 'Archivo', system-ui, -apple-system, sans-serif;
    --bs-font-sans-serif: 'Archivo', system-ui, -apple-system, sans-serif;
}
```

**Apply via Vite:**

```css
/* resources/css/app.css */
@import 'bootstrap/scss/bootstrap';
@import url('https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&display=swap');

:root {
    --bs-body-font-family: 'Archivo', sans-serif;
    --bs-font-sans-serif: 'Archivo', sans-serif;
}
```

---

### Cor de Acento (#ec3013)

**Projeto usa #ec3013 como cor primária/acentu.**

```scss
// resources/css/app.scss
$primary: #ec3013;

@import "bootstrap/scss/bootstrap";
```

**Ou via CSS variables:**

```css
:root {
    --bs-primary: #ec3013;
    --bs-primary-rgb: 236, 48, 19;
}
```

**Aplica automaticamente a:**
- `.btn-primary`, `.text-primary`, `.bg-primary`, `.border-primary`
- `.alert-primary`, `.badge.text-bg-primary`
- Links com `.link-primary`

---

### Sidebar Neutral-900

**Sidebar administrativa usa neutral-900.**

```html
<!-- resources/views/layouts/admin.blade.php -->
<div class="d-flex flex-column flex-shrink-0 p-3 text-bg-dark" style="width: 280px; height: 100vh; background-color: #212529 !important;">
    <a href="/" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-decoration-none">
        <span class="fs-4">Sidebar</span>
    </a>
    <hr>
    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
            <a href="#" class="nav-link active" aria-current="page">Home</a>
        </li>
        <!-- ... -->
    </ul>
</div>
```

**Ou via CSS variable:**

```css
.sidebar-admin {
    --bs-dark-rgb: 33, 37, 41;
    --bs-body-bg: var(--bs-dark);
}
```

---

### Grayscale Imagery

**Imagens do projeto são grayscale por padrão.**

```css
/* resources/css/app.css */
.img-grayscale {
    filter: grayscale(100%);
}

.img-grayscale:hover {
    filter: grayscale(0%);
    transition: filter 0.3s ease;
}
```

**Apply globamente:**

```css
img {
    filter: grayscale(100%);
    transition: filter 0.3s ease;
}

img:hover {
    filter: grayscale(0%);
}
```

**Ou via utility customizada:**

```scss
$utilities: map-merge(
    $utilities,
    (
        "filter-grayscale": (
            property: filter,
            class: filter,
            values: (
                grayscale: grayscale(100%),
                none: none,
            )
        )
    )
);
```

---

## Fontes das Seções

- [Getting Started - Introduction](https://getbootstrap.com/docs/5.3/getting-started/introduction/)
- [Getting Started - Download](https://getbootstrap.com/docs/5.3/getting-started/download/)
- [Getting Started - Contents](https://getbootstrap.com/docs/5.3/getting-started/contents/)
- [Getting Started - JavaScript](https://getbootstrap.com/docs/5.3/getting-started/javascript/)
- [Getting Started - Webpack](https://getbootstrap.com/docs/5.3/getting-started/webpack/)
- [Getting Started - Accessibility](https://getbootstrap.com/docs/5.3/getting-started/accessibility/)
- [Customize - Overview](https://getbootstrap.com/docs/5.3/customize/overview/)
- [Customize - Sass](https://getbootstrap.com/docs/5.3/customize/sass/)
- [Customize - Options](https://getbootstrap.com/docs/5.3/customize/options/)
- [Customize - Color](https://getbootstrap.com/docs/5.3/customize/color/)
- [Customize - Color Modes](https://getbootstrap.com/docs/5.3/customize/color-modes/)
- [Customize - CSS Variables](https://getbootstrap.com/docs/5.3/customize/css-variables/)
- [Customize - Components](https://getbootstrap.com/docs/5.3/customize/components/)
- [Customize - Utility API](https://getbootstrap.com/docs/5.3/utilities/api/)
- [Layout - Breakpoints](https://getbootstrap.com/docs/5.3/layout/breakpoints/)
- [Layout - Containers](https://getbootstrap.com/docs/5.3/layout/containers/)
- [Layout - Grid](https://getbootstrap.com/docs/5.3/layout/grid/)
- [Layout - Columns](https://getbootstrap.com/docs/5.3/layout/columns/)
- [Forms - Overview](https://getbootstrap.com/docs/5.3/forms/overview/)
- [Forms - Form Control](https://getbootstrap.com/docs/5.3/forms/form-control/)
- [Forms - Select](https://getbootstrap.com/docs/5.3/forms/select/)
- [Forms - Checks & Radios](https://getbootstrap.com/docs/5.3/forms/checks-radios/)
- [Forms - Range](https://getbootstrap.com/docs/5.3/forms/range/)
- [Forms - Input Group](https://getbootstrap.com/docs/5.3/forms/input-group/)
- [Forms - Floating Labels](https://getbootstrap.com/docs/5.3/forms/floating-labels/)
- [Forms - Validation](https://getbootstrap.com/docs/5.3/forms/validation/)
- [Components - Accordion](https://getbootstrap.com/docs/5.3/components/accordion/)
- [Components - Alerts](https://getbootstrap.com/docs/5.3/components/alerts/)
- [Components - Badge](https://getbootstrap.com/docs/5.3/components/badge/)
- [Components - Breadcrumb](https://getbootstrap.com/docs/5.3/components/breadcrumb/)
- [Components - Buttons](https://getbootstrap.com/docs/5.3/components/buttons/)
- [Components - Button Group](https://getbootstrap.com/docs/5.3/components/button-group/)
- [Components - Card](https://getbootstrap.com/docs/5.3/components/card/)
- [Components - Carousel](https://getbootstrap.com/docs/5.3/components/carousel/)
- [Components - Close Button](https://getbootstrap.com/docs/5.3/components/close-button/)
- [Components - Collapse](https://getbootstrap.com/docs/5.3/components/collapse/)
- [Components - Dropdowns](https://getbootstrap.com/docs/5.3/components/dropdowns/)
- [Components - List Group](https://getbootstrap.com/docs/5.3/components/list-group/)
- [Components - Modal](https://getbootstrap.com/docs/5.3/components/modal/)
- [Components - Navbar](https://getbootstrap.com/docs/5.3/components/navbar/)
- [Components - Navs & Tabs](https://getbootstrap.com/docs/5.3/components/navs-tabs/)
- [Components - Offcanvas](https://getbootstrap.com/docs/5.3/components/offcanvas/)
- [Components - Pagination](https://getbootstrap.com/docs/5.3/components/pagination/)
- [Components - Placeholders](https://getbootstrap.com/docs/5.3/components/placeholders/)
- [Components - Popovers](https://getbootstrap.com/docs/5.3/components/popovers/)
- [Components - Progress](https://getbootstrap.com/docs/5.3/components/progress/)
- [Components - Scrollspy](https://getbootstrap.com/docs/5.3/components/scrollspy/)
- [Components - Spinners](https://getbootstrap.com/docs/5.3/components/spinners/)
- [Components - Toasts](https://getbootstrap.com/docs/5.3/components/toasts/)
- [Components - Tooltips](https://getbootstrap.com/docs/5.3/components/tooltips/)
- [Helpers - Color & Background](https://getbootstrap.com/docs/5.3/helpers/color-background/)
- [Helpers - Position](https://getbootstrap.com/docs/5.3/helpers/position/)
- [Helpers - Stacks](https://getbootstrap.com/docs/5.3/helpers/stacks/)
- [Helpers - Stretched Link](https://getbootstrap.com/docs/5.3/helpers/stretched-link/)
- [Helpers - Vertical Rule](https://getbootstrap.com/docs/5.3/helpers/vertical-rule/)
- [Helpers - Visually Hidden](https://getbootstrap.com/docs/5.3/helpers/visually-hidden/)
- [Helpers - Ratio](https://getbootstrap.com/docs/5.3/helpers/ratio/)
- [Utilities - Background](https://getbootstrap.com/docs/5.3/utilities/background/)
- [Utilities - Borders](https://getbootstrap.com/docs/5.3/utilities/borders/)
- [Utilities - Display](https://getbootstrap.com/docs/5.3/utilities/display/)
- [Utilities - Flex](https://getbootstrap.com/docs/5.3/utilities/flex/)
- [Utilities - Shadows](https://getbootstrap.com/docs/5.3/utilities/shadows/)
- [Utilities - Sizing](https://getbootstrap.com/docs/5.3/utilities/sizing/)
- [Utilities - Spacing](https://getbootstrap.com/docs/5.3/utilities/spacing/)
- [Utilities - Text](https://getbootstrap.com/docs/5.3/utilities/text/)
- [Laravel 13 - Pagination](https://laravel.com/docs/13.x/pagination)

---

**Documento compilado a partir do crawl da documentação oficial do Bootstrap 5.3 e Laravel 13. Última atualização: Agosto 2026.**
