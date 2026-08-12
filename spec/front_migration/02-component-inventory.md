# 02 — Inventário de Componentes Blade e Mapeamento DE ↔ PARA Bootstrap 5.3

> **Escopo deste documento:** exclusivamente a camada de componentes Blade —
> `resources/views/components/**`, `resources/views/layouts/{app,guest}.blade.php`
> e todos os parciais compartilhados (`resources/views/**/_*.blade.php`).
> Telas (views de página) são cobertas por outro documento da série.
>
> **Este arquivo é instrução de build**, não resumo. Todo markup alvo abaixo
> deve ser copiado/adaptado literalmente pelos agentes de implementação.

---

## Sumário

1. [Fatos verificados da baseline](#1-fatos-verificados-da-baseline)
2. [Arquivos no escopo](#2-arquivos-no-escopo)
3. [Inventário detalhado — `components/ui/*`](#3-inventário-detalhado--componentsui)
4. [Inventário detalhado — `components/layout/*` e componentes de topo](#4-inventário-detalhado--componentslayout-e-componentes-de-topo)
5. [Inventário detalhado — layouts](#5-inventário-detalhado--layouts)
6. [Inventário detalhado — parciais compartilhados (`_*.blade.php`)](#6-inventário-detalhado--parciais-compartilhados)
7. [Tabela mestra DE ↔ PARA](#7-tabela-mestra-de--para)
8. [Camada de tema: overrides SCSS/CSS obrigatórios (Modernist)](#8-camada-de-tema-overrides-scsscss-obrigatórios-modernist)
9. [Catálogo alvo de componentes pós-migração](#9-catálogo-alvo-de-componentes-pós-migração)
10. [Seletores `dusk` — contrato inviolável](#10-seletores-dusk--contrato-inviolável)
11. [Anti-padrões, riscos e lacunas de acessibilidade](#11-anti-padrões-riscos-e-lacunas-de-acessibilidade)
12. [Ordem de execução recomendada](#12-ordem-de-execução-recomendada)

---

## 1. Fatos verificados da baseline

Confirmado por leitura direta dos arquivos (não reproduzido de memória):

| Fato | Evidência |
|---|---|
| `resources/css/app.css` importa **apenas** `bootstrap-grid.min.css` e `bootstrap-utilities.min.css` | linhas 1–2 do arquivo |
| Não há build completo do Bootstrap, nem bundle JS do Bootstrap, nem pipeline SCSS | `package.json` → `dependencies: { "bootstrap": "^5.3.3" }`, nenhum `sass`/`sass-embedded`; `resources/js/app.js` não importa `bootstrap` |
| Tailwind é dependência morta | `devDependencies` tem `tailwindcss@^4` + `@tailwindcss/vite` no `vite.config.js`, mas `app.css` nunca faz `@import "tailwindcss"` |
| `app.css` define somente: 3 `@font-face` Archivo, bloco `:root` de tokens Modernist, `:focus-visible`, `.grayscale`, `.org-logo` | leitura integral do arquivo |
| Classes `.btn`, `.btn-primary`, `.btn-secondary`, `.btn-ghost`, `.btn-icon`, `.btn-block`, `.card`, `.card-body`, `.card-title`, `.card-kicker`, `.card-meta`, `.card-footer`, `.dialog`, `.dialog-backdrop`, `.dialog-title`, `.dialog-body`, `.dialog-actions`, `.tag`, `.tag-accent`, `.tag-outline`, `.tag-neutral`, `.tag-accent-2`, `.field`, `.input`, `.table`, `.nav`, `.sidebar-item`, `.sidebar-badge`, `.elev-sm/md/lg` **não possuem definição CSS em lugar nenhum** | ausentes de `app.css`; nenhum outro CSS é carregado |
| `.table-responsive`, `.d-none`, `.d-lg-flex`, `.d-md-block`, `.d-sm-block`, `.text-start` **funcionam** | vêm de `bootstrap-utilities.min.css` |
| **Alpine.js NÃO está instalado** | ausente de `package.json`; comentários explícitos em `ModalManager.js:27`, `ForumEditHistory.js:10`, `AuditLogDiffModal.js:14` |
| Toda a interatividade real é feita por módulos JS próprios | `resources/js/modules/*` (13 módulos) + `quiz-builder.js` + `quiz-timer.js`, registrados em `window.*` por `app.js` |
| Total de `dusk="..."` em `resources/views` | **316 ocorrências**; **76 delas dentro do escopo deste documento** |

### 1.1 Consequência crítica: atributos Alpine são código morto

`x-data`, `x-show`, `x-cloak`, `x-transition:*`, `@click`, `@keydown.escape.window`,
`@click.outside` aparecem em **3 arquivos** (`layouts/app.blade.php`,
`components/ui/modal.blade.php`, `components/ui/alert.blade.php`) e mais o
`components/layout/sidebar.blade.php` (`x-show="sidebarOpen"`, `x-transition`) e
`components/layout/topbar.blade.php` (`@click="sidebarOpen = !sidebarOpen"`).
**Nenhum desses atributos executa.** Efeitos comprovados:

- **BUG-004** (já catalogado em `spec/bugs/BUG-004-alert-dismiss-button-inert.md`):
  o botão "Fechar alerta" de `<x-ui.alert dismissable>` é inerte.
- **Bug ainda não catalogado:** o drawer mobile do sidebar nunca abre. O botão
  `dusk="mobile-menu-button"` do topbar só faz `@click="sidebarOpen = ..."`
  (Alpine, inerte) e não existe **nenhuma** referência a `sidebarOpen` em
  `resources/js/` ou em `app/`. A navegação mobile está 100% quebrada.
- O `<x-ui.modal>` só funciona porque o `ModalManager.js` (custom) intercepta
  `[data-modal-target]` / `[data-modal-dismiss]` — o `x-show` é ignorado e o
  backdrop nasce com `display: none` inline.

> **A migração para Bootstrap 5.3 com o bundle JS oficial resolve BUG-004 e o
> drawer mobile "de graça"** (`.alert-dismissible + .btn-close[data-bs-dismiss]`
> e `.offcanvas + [data-bs-toggle="offcanvas"]`). Isso deve ser explicitado nos
> PRs correspondentes.

### 1.2 Paginação está sem estilo algum

`{{ $paginator->links() }}` é usado em **9 telas** (`forum/index`, `users/index`,
`certificates/index`, `courses/invitation-links/index`, `quizzes/attempts/pending`,
`audit-logs/index`, `organizations/index`, `courses/index`,
`courses/enrollments/index`). Não existe chamada a
`Paginator::useBootstrapFive()` em nenhum Service Provider (`grep -rn "Paginator" app/Providers` → vazio),
portanto o Laravel 13 renderiza o **tema Tailwind padrão**, cujas classes não existem
no bundle carregado. **A paginação inteira do sistema está visualmente quebrada hoje.**

---

## 2. Arquivos no escopo

| # | Arquivo | Linhas | Tipo |
|---|---|---|---|
| 1 | `resources/views/components/ui/alert.blade.php` | 43 | UI |
| 2 | `resources/views/components/ui/badge.blade.php` | 27 | UI |
| 3 | `resources/views/components/ui/button.blade.php` | 44 | UI |
| 4 | `resources/views/components/ui/card.blade.php` | 65 | UI |
| 5 | `resources/views/components/ui/icon.blade.php` | 183 | UI |
| 6 | `resources/views/components/ui/input.blade.php` | 58 | UI |
| 7 | `resources/views/components/ui/modal.blade.php` | 60 | UI |
| 8 | `resources/views/components/ui/select.blade.php` | 55 | UI |
| 9 | `resources/views/components/ui/stat-card.blade.php` | 24 | UI |
| 10 | `resources/views/components/ui/table.blade.php` | 29 | UI |
| 11 | `resources/views/components/layout/alerts.blade.php` | 35 | Layout |
| 12 | `resources/views/components/layout/footer.blade.php` | 12 | Layout |
| 13 | `resources/views/components/layout/sidebar.blade.php` | 118 | Layout |
| 14 | `resources/views/components/layout/topbar.blade.php` | 77 | Layout |
| 15 | `resources/views/components/help-button.blade.php` | 69 | Feature (classe `App\View\Components\HelpButton`) |
| 16 | `resources/views/components/notifications-bell.blade.php` | 93 | Feature (anônimo) |
| 17 | `resources/views/layouts/app.blade.php` | 32 | Layout raiz |
| 18 | `resources/views/layouts/guest.blade.php` | 53 | Layout raiz |
| 19 | `resources/views/courses/_form.blade.php` | 34 | Parcial |
| 20 | `resources/views/courses/modules/_form.blade.php` | 19 | Parcial |
| 21 | `resources/views/courses/modules/_list.blade.php` | 37 | Parcial |
| 22 | `resources/views/organizations/_form.blade.php` | 47 | Parcial |
| 23 | `resources/views/modules/lessons/_form.blade.php` | 130 | Parcial (+ `@push('scripts')` inline) |
| 24 | `resources/views/quizzes/partials/_question-form.blade.php` | 131 | Parcial |
| 25 | `resources/views/quizzes/partials/_question-list.blade.php` | 55 | Parcial |
| 26 | `resources/views/forum/partials/_topic.blade.php` | 33 | Parcial |
| 27 | `resources/views/forum/partials/_reply.blade.php` | 57 | Parcial |
| 28 | `resources/views/forum/partials/_edit-history-modal.blade.php` | 51 | Parcial |
| 29 | `resources/views/audit-logs/partials/_diff-modal.blade.php` | 33 | Parcial |
| 30 | `resources/views/classroom/partials/_video.blade.php` | 55 | Parcial |
| 31 | `resources/views/classroom/partials/_pdf.blade.php` | 50 | Parcial |
| 32 | `resources/views/classroom/partials/_text-image.blade.php` | 42 | Parcial |
| 33 | `resources/views/classroom/partials/_quiz-placeholder.blade.php` | 23 | Parcial |

**Total: 33 arquivos, 1.874 linhas.**

---

## 3. Inventário detalhado — `components/ui/*`

### 3.1 `<x-ui.button>`

- **Arquivo:** `resources/views/components/ui/button.blade.php`
- **Consumo:** **82 ocorrências em 45 arquivos** (o componente mais usado do sistema).
  Top consumidores: `courses/index` (4), `users/index` (3), `quizzes/partials/_question-form` (3),
  `quizzes/edit` (3), `modules/lessons/index` (3).

**API de props**

| Prop | Default | Tipo | Efeito |
|---|---|---|---|
| `variant` | `'primary'` | `primary\|secondary\|ghost` | escolhe `btn-primary` / `btn-secondary` / `btn-ghost` (classes inexistentes) |
| `size` | `'md'` | `sm\|md\|lg` | injeta `padding`/`font-size` inline |
| `block` | `false` | bool | adiciona `btn-block` |
| `icon` | `false` | bool | adiciona `btn-icon` |
| `type` | `'button'` | string | atributo `type` do `<button>` |
| `href` | `null` | string\|null | se presente renderiza `<a>` em vez de `<button>` |
| `disabled` | `false` | bool | atributo `disabled` |

- **Slot:** default apenas (rótulo).
- **Atributos repassados via `$attributes->merge()`:** `dusk`, `data-modal-target`,
  `data-mark-complete-url`, `data-add-option-btn`, `:hidden`, `form`, `onclick`, etc.

**Estilos inline emitidos**

```
border-radius: 0px; text-align: left; justify-content: flex-start;
+ sm : padding: 6px 12px; font-size: 12px;
+ md : padding: 10px 18px; font-size: 14px;
+ lg : padding: 14px 24px; font-size: 16px;
```

> Observação: `text-align: left; justify-content: flex-start` só faz sentido se
> `.btn` tivesse `display:flex`, o que não tem. Hoje o botão é um `<button>`
> inline-block sem borda, sem cor de fundo e sem cor de texto — **totalmente sem
> estilo visual**.

**DE ↔ PARA**

| DE (atual) | PARA (Bootstrap 5.3) |
|---|---|
| `class="btn btn-primary"` + style inline | `class="btn btn-primary"` (agora com CSS real do build completo) |
| `variant="secondary"` | `btn-outline-secondary` (ou `btn-secondary`, ver §8.4) |
| `variant="ghost"` | `btn-link` (para links textuais) ou `btn-outline-secondary border-0` |
| `size="sm"` | `btn-sm` |
| `size="lg"` | `btn-lg` |
| `block` | `w-100` (o `.btn-block` foi removido no BS5) |
| `icon` | `btn-icon` custom (ver §8.5 — Bootstrap não tem equivalente) |
| `disabled` em `<a href>` | precisa `class="disabled" aria-disabled="true" tabindex="-1"` |

```html
<!-- PARA -->
<button type="submit" class="btn btn-primary btn-sm" dusk="question-submit-create">
    Salvar Questão
</button>

<a href="{{ $href }}" class="btn btn-outline-secondary btn-sm" dusk="edit-module-3">Editar</a>
```

---

### 3.2 `<x-ui.input>`

- **Arquivo:** `resources/views/components/ui/input.blade.php`
- **Consumo:** **64 ocorrências em 21 arquivos**. Top: `users/edit` (6),
  `profile/edit` (6), `users/create` (5), `settings/edit` (5), `quizzes/edit` (5),
  `quizzes/create` (5), `convite/show` (5).

**API de props**

| Prop | Default | Efeito |
|---|---|---|
| `label` | `null` | `<label for="{{ $name }}">` |
| `name` | `null` | `id` + `name` + resolução automática de `$errors->has($name)` e `old($name, $value)` |
| `type` | `'text'` | `'textarea'` troca para `<textarea>` com `min-height: 90px`; qualquer outro valor vira `type` do `<input>` (usado: `text`, `number`, `email`, `password`, `date`) |
| `value` | `null` | valor default (sempre passa por `old()`) |
| `placeholder` | `null` | — |
| `error` | `null` | mensagem de erro explícita; sobrepõe `$errors` |
| `required` | `false` | atributo + asterisco `*` accent no label |
| `disabled` | `false` | atributo |
| `kicker` | `null` | eyebrow uppercase accent acima do label |
| `hint` | `null` | texto auxiliar abaixo do campo |

- **Slot:** nenhum (o componente é auto-contido).
- **Estilos inline:** wrapper `.field` (`display:flex; flex-direction:column; gap:6px; width:100%`),
  input (`border-radius:0; width:100%; height:40px; padding:8px 12px; font-size:14px;
  background: var(--color-surface); border: 1px solid var(--color-divider)`),
  borda accent quando há erro, mensagem de erro em `12px` accent bold.

**DE ↔ PARA**

```html
<!-- PARA: form-group Bootstrap completo -->
<div class="mb-3">
    @if($kicker)
        <span class="d-block text-uppercase fw-bold text-accent small-kicker">{{ $kicker }}</span>
    @endif

    <label for="{{ $name }}" class="form-label">
        {{ $label }}@if($required)<span class="text-danger ms-1">*</span>@endif
    </label>

    <input type="{{ $type }}"
           id="{{ $name }}" name="{{ $name }}"
           value="{{ old($name, $value) }}"
           class="form-control @error($name) is-invalid @enderror"
           @required($required) @disabled($disabled)
           aria-describedby="{{ $name }}-hint">

    @if($hint)<div id="{{ $name }}-hint" class="form-text">{{ $hint }}</div>@endif
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
```

| Elemento atual | Classe Bootstrap |
|---|---|
| `.field` wrapper flex | `.mb-3` (form-group) |
| `<label style="font-size:13px;font-weight:600">` | `.form-label` |
| `<input class="input" style="...">` | `.form-control` |
| `<textarea class="input">` | `.form-control` + `rows="4"` |
| borda accent em erro | `.is-invalid` (usa `--bs-form-invalid-border-color`) |
| `<span>` mensagem de erro | `.invalid-feedback` (exige `.is-invalid` no irmão para aparecer) |
| `hint` | `.form-text` |
| `kicker` | sem equivalente → utilitário custom `.form-kicker` (§8.5) |

> **Atenção:** `.invalid-feedback` tem `display:none` por padrão e só aparece via
> `.is-invalid ~ .invalid-feedback`. Ambos precisam ser emitidos juntos, senão a
> mensagem some — regressão silenciosa em ~20 telas de formulário.

---

### 3.3 `<x-ui.select>`

- **Arquivo:** `resources/views/components/ui/select.blade.php`
- **Consumo:** **9 ocorrências em 7 arquivos** (`users/edit` 2,
  `courses/completion-rules/index` 2, `users/import`, `users/create`,
  `quizzes/partials/_question-form`, `organizations/_form`, `modules/lessons/_form`).

**API de props:** `label`, `name`, `options` (array `valor => rótulo`), `selected`,
`placeholder` (default `'Selecione uma opção'`, renderizado como `<option value="" disabled>`),
`error`, `required`, `disabled`. **Slot default** é injetado *dentro* do `<select>`
(permite `<option>` extras).

**Estilos inline:** `appearance: none` em todos os prefixos + um `<svg>` chevron
absolutamente posicionado à direita (`right: 12px; top: 50%`), `padding-right: 34px`
para dar espaço à seta.

**DE ↔ PARA**

```html
<!-- PARA -->
<div class="mb-3">
    <label for="{{ $name }}" class="form-label">{{ $label }}</label>
    <select id="{{ $name }}" name="{{ $name }}"
            class="form-select @error($name) is-invalid @enderror"
            @required($required) @disabled($disabled)>
        @if($placeholder)<option value="" disabled @selected(is_null(old($name,$selected)))>{{ $placeholder }}</option>@endif
        @foreach($options as $val => $optLabel)
            <option value="{{ $val }}" @selected((string) old($name,$selected) === (string) $val)>{{ $optLabel }}</option>
        @endforeach
        {{ $slot }}
    </select>
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
```

> **Ganho:** `.form-select` já embute o chevron como `background-image` data-URI.
> O `<svg>` posicionado absolutamente e o wrapper `position: relative` **devem ser
> removidos**. Para manter a seta na cor Modernist, sobrescrever
> `$form-select-indicator-color: #201e1d` (SCSS) — não dá para mudar por CSS var.

---

### 3.4 `<x-ui.badge>`

- **Arquivo:** `resources/views/components/ui/badge.blade.php`
- **Consumo:** **27 ocorrências em 21 arquivos** (inclui uso interno por
  `x-ui.stat-card`).
- **Props:** `variant` ∈ `accent` (default) | `outline` | `neutral` | `accent-2`.
- **Slot:** default.
- **Estilos inline:** `display:inline-flex; padding:3px 8px; font-size:11px;
  font-weight:700; text-transform:uppercase; letter-spacing:.05em; border-radius:0;
  border:1px solid transparent` + par background/color por variante.

**DE ↔ PARA**

| `variant` atual | Bootstrap 5.3 |
|---|---|
| `accent` (default) | `badge text-bg-primary` (primary = `#ec3013`, ver §8) |
| `accent-2` | `badge text-bg-danger` |
| `neutral` | `badge text-bg-secondary` |
| `outline` | `badge border text-body bg-transparent` |

```html
<!-- PARA -->
<span {{ $attributes->merge(['class' => 'badge text-bg-primary text-uppercase']) }}>{{ $slot }}</span>
```

> **⚠️ Armadilha documentada nos 3 parciais de classroom
> (`_video`, `_pdf`, `_text-image`):** o badge é escondido com
> `style="display:none;"` porque o `display:inline-flex` inline vencia o atributo
> `hidden`. Após a migração, `display` passa a vir da classe `.badge`
> (`display:inline-block`), então **o atributo `hidden` volta a funcionar** e
> `LessonPlayer.js.reflectCompletion()` (que faz `style.display = 'inline-flex'`)
> **quebra**. É obrigatório: ou trocar por `class="badge ... d-none"` +
> `classList.remove('d-none')` no JS, ou manter `style="display:none"` no Blade.
> **Decisão recomendada:** usar `d-none` e atualizar `LessonPlayer.js`
> (`reflectCompletion()` → `badge.classList.remove('d-none')`).

---

### 3.5 `<x-ui.card>`

- **Arquivo:** `resources/views/components/ui/card.blade.php`
- **Consumo:** **26 ocorrências em 25 arquivos** (praticamente 1 por tela de
  formulário; `profile/edit` usa 2).

**API de props**

| Prop | Default | Efeito |
|---|---|---|
| `kicker` | `null` | eyebrow uppercase accent |
| `title` | `null` | `<h3 class="card-title">` |
| `meta` | `null` | rodapé de metadados com borda superior |
| `elevation` | `'none'` | `sm\|md\|lg` → `elev-sm/md/lg` (classes inexistentes) |
| `border` | `true` | `border: 1px solid var(--color-divider)` |
| `shadow` | `false` | com `elevation=none` força `box-shadow: none` |

**Slots nomeados:** `$image` (envolvido em `.card-image-slot.grayscale`),
`$kickerSlot`, `$titleSlot`, `$metaSlot`, `$footer`, além do default (conteúdo).

> Verificado: **nenhuma view atual usa `x-slot:image`, `x-slot:footer`,
> `x-slot:kickerSlot`, `x-slot:titleSlot` ou `x-slot:metaSlot`.** Os únicos
> `x-slot` do projeto são `:title` (do layout), `:actions` (do modal) e
> `:header` (do table, em `dashboard/index`). Os slots do card são **API morta**
> — podem ser simplificados na migração.

**DE ↔ PARA**

```html
<!-- PARA -->
<div {{ $attributes->merge(['class' => 'card']) }}>
    @isset($image)
        <div class="card-img-top grayscale overflow-hidden">{{ $image }}</div>
    @endisset

    <div class="card-body">
        @if($kicker)<div class="card-subtitle text-uppercase fw-bold text-primary small mb-1">{{ $kicker }}</div>@endif
        @if($title)<h3 class="card-title h5">{{ $title }}</h3>@endif
        <div class="card-text">{{ $slot }}</div>
    </div>

    @if($meta)<div class="card-footer bg-transparent small text-secondary">{{ $meta }}</div>@endif
    @isset($footer)<div class="card-footer">{{ $footer }}</div>@endisset
</div>
```

| Atual | Bootstrap |
|---|---|
| `div.card` + border/bg inline | `.card` (usa `--bs-card-*`) |
| `div.card-body` padding 20px | `.card-body` |
| `h3.card-title` | `.card-title` (+ `.h5` para tamanho) |
| `div.card-kicker` | `.card-subtitle` + utilitários |
| `div.card-meta` (border-top, margin-top:auto) | `.card-footer.bg-transparent` |
| `div.card-footer` (bg 4% neutral) | `.card-footer` |
| `elev-sm/md/lg` | `.shadow-sm` / `.shadow` / `.shadow-lg` |
| `border=false` | `.border-0` |

---

### 3.6 `<x-ui.stat-card>`

- **Arquivo:** `resources/views/components/ui/stat-card.blade.php`
- **Consumo:** **4 ocorrências, 1 arquivo** — `dashboard/index.blade.php`.
- **Props:** `kicker` (default `'Métrica'`), `value` (default `'0'`),
  `delta` (opcional), `deltaVariant` (default `'accent'`, repassado a `x-ui.badge`).
- **Slot:** nenhum. **Compõe `<x-ui.badge>` internamente.**
- **Atributos repassados:** `dusk="stat-{metric}"` (contrato de teste da SPEC-12).

**DE ↔ PARA**

```html
<!-- PARA -->
<div {{ $attributes->merge(['class' => 'card shadow-sm h-100']) }}>
    <div class="card-body d-flex flex-column gap-1">
        <div class="text-uppercase fw-bold text-secondary" style="font-size:.6875rem;letter-spacing:.08em">{{ $kicker }}</div>
        <div class="display-6 fw-bolder lh-1 font-heading">{{ $value }}</div>
        @if($delta)<div><span class="badge text-bg-primary">{{ $delta }}</span></div>@endif
    </div>
</div>
```

O dashboard passa a envolver os 4 cards em `.row.row-cols-1.row-cols-md-2.row-cols-xl-4.g-3`.

---

### 3.7 `<x-ui.table>`

- **Arquivo:** `resources/views/components/ui/table.blade.php`
- **Consumo:** **11 ocorrências em 11 arquivos** — `users/index`,
  `quizzes/attempts/pending`, `organizations/index`, `forum/moderation/index`,
  `dashboard/index`, `courses/invitation-links/index`, `courses/index`,
  `courses/enrollments/index`, `courses/completion-rules/index`,
  `certificates/index`, `audit-logs/index`.
- **Props:** `headers` (array de strings), `hoverable` (default `true`, **nunca lido**),
  `striped` (default `false`, **nunca lido**).
- **Slots:** `$header` (substitui a `<tr>` gerada; usado só em `dashboard/index`),
  default = `<tbody>`.
- **Bug latente:** `<tbody style="divide-y divide-color-divider;">` — isso é
  **classe Tailwind escrita dentro de um atributo `style`**. É CSS inválido e não
  produz efeito algum. As linhas da tabela não têm separador.

**DE ↔ PARA**

```html
<!-- PARA -->
<div class="table-responsive">
    <table {{ $attributes->merge(['class' => 'table table-hover align-middle mb-0'.($striped ? ' table-striped' : '')]) }}>
        @if(count($headers) || isset($header))
            <thead class="table-light">
                @isset($header)
                    {{ $header }}
                @else
                    <tr>@foreach($headers as $h)<th scope="col">{{ $h }}</th>@endforeach</tr>
                @endisset
            </thead>
        @endif
        <tbody>{{ $slot }}</tbody>
    </table>
</div>
```

| Atual | Bootstrap |
|---|---|
| `div.table-responsive` + border/overflow inline | `.table-responsive` (já existe no utilities build; ganha comportamento no build completo) |
| `table.table` + estilos inline | `.table` + `--bs-table-*` |
| `hoverable` (morto) | `.table-hover` |
| `striped` (morto) | `.table-striped` |
| `thead` bg 6% neutral | `.table-light` |
| `th` uppercase 11px 800 | override em `.table > thead th` (§8.5) |
| `divide-y` inválido | `.table` já emite `border-bottom` por linha |

> A prop `hoverable` deve passar a ser **efetivamente aplicada** (`table-hover`),
> o que muda o visual de 11 telas — validar com o design.

---

### 3.8 `<x-ui.modal>`

- **Arquivo:** `resources/views/components/ui/modal.blade.php`
- **Consumo:** **10 ocorrências em 7 arquivos** — `quizzes/edit` (2),
  `components/help-button` (2), `audit-logs/partials/_diff-modal` (1),
  `forum/show` (1), `forum/partials/_edit-history-modal` (1), `forum/index` (1),
  `certificates/index` (1).
- **Props:** `id`, `name`, `title` (default `'Confirmação'`), `dismissable`
  (default `true`), `size` ∈ `sm` (400px) | `md` (560px) | `lg` (720px).
- **Slots:** default (corpo) + `$actions` (rodapé).
- **Contrato JS (crítico):** `ModalManager.js` abre por
  `[data-modal-target="{id}"]` e fecha por `[data-modal-dismiss]`, procurando o
  ancestral `.dialog, [role="dialog"], .modal` e o backdrop `.dialog-backdrop`.
  Consumido programaticamente por `ForumEditHistory.js`, `ForumReportModal.js`,
  `AuditLogDiffModal.js` (todos recebem `ModalManager` por injeção).

**Markup atual (resumo)**

```html
<div class="dialog-backdrop" style="position:fixed;inset:0;z-index:100;display:none;...">
  <div id="{id}" class="dialog" role="dialog" aria-modal="true" style="width:560px;...">
    <div style="...header...">
      <h3 class="dialog-title">{title}</h3>
      <button data-modal-dismiss="true" class="btn btn-ghost btn-icon" aria-label="Fechar">…svg…</button>
    </div>
    <div class="dialog-body" style="padding:24px;max-height:70vh;overflow-y:auto">{slot}</div>
    <div class="dialog-actions">{actions}</div>
  </div>
</div>
```

**DE ↔ PARA**

```html
<!-- PARA -->
<div class="modal fade" id="{{ $modalId }}" tabindex="-1"
     aria-labelledby="{{ $modalId }}-label" aria-hidden="true"
     @unless($dismissable) data-bs-backdrop="static" data-bs-keyboard="false" @endunless>
  <div class="modal-dialog {{ ['sm'=>'modal-sm','lg'=>'modal-lg'][$size] ?? '' }} modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="{{ $modalId }}-label">{{ $title ?? 'Confirmação' }}</h5>
        @if($dismissable)
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        @endif
      </div>
      <div class="modal-body">{{ $slot }}</div>
      @isset($actions)<div class="modal-footer">{{ $actions }}</div>@endisset
    </div>
  </div>
</div>
```

| Atual | Bootstrap 5.3 |
|---|---|
| `.dialog-backdrop` (div própria) | gerada pelo JS do Bootstrap (`.modal-backdrop`) — **remover a div** |
| `.dialog` | `.modal > .modal-dialog > .modal-content` |
| `size=sm/md/lg` (px inline) | `.modal-sm` / (default) / `.modal-lg` |
| `.dialog-title` | `.modal-title` |
| `.dialog-body` + `max-height:70vh;overflow-y:auto` | `.modal-body` + `.modal-dialog-scrollable` |
| `.dialog-actions` | `.modal-footer` |
| botão X svg custom | `.btn-close` |
| `data-modal-target="x"` | `data-bs-toggle="modal" data-bs-target="#x"` |
| `data-modal-dismiss="true"` | `data-bs-dismiss="modal"` |
| `dismissable=false` | `data-bs-backdrop="static" data-bs-keyboard="false"` |
| `ModalManager.open(id)` (JS) | `bootstrap.Modal.getOrCreateInstance(el).show()` |

**Impacto obrigatório em JS** (não pode ser esquecido):

| Arquivo | Mudança |
|---|---|
| `resources/js/modules/ModalManager.js` | reescrever como *fachada fina* sobre `bootstrap.Modal` mantendo a API pública `open/close/toggle/closeAll` (os 3 módulos abaixo dependem dela) |
| `resources/js/modules/ForumEditHistory.js` | consome `ModalManager` — mantém API, sem mudança se a fachada for preservada |
| `resources/js/modules/ForumReportModal.js` | idem |
| `resources/js/modules/AuditLogDiffModal.js` | idem; preenche `[dusk=audit-diff-old/new]` antes de abrir |
| `resources/js/app.js` | adicionar `import * as bootstrap from 'bootstrap'; window.bootstrap = bootstrap;` |

> **Recomendação forte:** manter a fachada `window.ModalManager` com a mesma
> assinatura e apenas trocar a implementação interna. Assim os 3 módulos
> dependentes e todos os testes Dusk que clicam em `[data-modal-target]`
> continuam válidos — desde que o Blade emita **os dois** conjuntos de
> atributos durante a transição (`data-modal-target` **e**
> `data-bs-toggle`/`data-bs-target`), ou que a fachada continue escutando
> `[data-modal-target]` e delegue ao Bootstrap.

---

### 3.9 `<x-ui.alert>`

- **Arquivo:** `resources/views/components/ui/alert.blade.php`
- **Consumo:** **10 ocorrências em 3 arquivos** — `components/layout/alerts` (5),
  `student/quizzes/show` (4), `organizations/index` (1).
- **Props:** `variant` ∈ `accent` (default) | `accent-2` | `danger` | `warning`;
  `dismissable` (default `false`); `icon` (default `null`, **nunca lido**).
- **Slot:** default.
- **Ícone:** SVG "info circle" **hardcoded**, sempre renderizado, ignorando a prop `icon`.
- **Estado atual:** `x-data`/`x-show`/`@click="show = false"` → **inerte (BUG-004)**.

**DE ↔ PARA**

```html
<!-- PARA -->
<div {{ $attributes->merge(['class' => "alert alert-{$bsVariant} d-flex align-items-start gap-2".($dismissable ? ' alert-dismissible fade show' : '')]) }}
     role="alert">
    <svg class="flex-shrink-0 mt-1" width="18" height="18" …>…</svg>
    <div class="flex-grow-1">{{ $slot }}</div>
    @if($dismissable)
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar alerta"></button>
    @endif
</div>
```

| `variant` atual | Bootstrap |
|---|---|
| `accent` | `alert-primary` |
| `accent-2` / `danger` | `alert-danger` |
| `warning` | `alert-warning` (ou `alert-secondary` para manter o cinza Modernist) |

> ⚠️ `aria-label="Fechar alerta"` deve ser preservado — o `BUG-004` referencia
> esse seletor no roteiro de reprodução. **Esta migração fecha o BUG-004.**

---

### 3.10 `<x-ui.icon>`

- **Arquivo:** `resources/views/components/ui/icon.blade.php` (183 linhas)
- **Consumo:** **3 ocorrências em 2 arquivos** — `classroom/show` (2),
  `components/notifications-bell` (1). **Sub-utilizado**: o resto do sistema
  duplica SVGs inline (topbar, sidebar, alert, modal, select, help-button).
- **Props:** `name` (obrigatório), `size` (default `24`), `class` (default `''`),
  `strokeWidth` (default `2`).
- **Implementação:** `@switch($name)` com **28 ícones Lucide** inline:
  `bell, user, search, chevron-down, chevron-up, chevron-right, chevron-left,
  check, play, lock, upload, plus, clock, x, book-open, award, settings,
  file-text, message-square, log-out, home, arrow-left, grip-vertical, filter,
  eye, eye-off, edit|pencil, trash|trash-2, menu` + fallback (círculo com "!").

**DE ↔ PARA:** Bootstrap 5.3 **não fornece ícones** no bundle CSS/JS — o
`bootstrap-icons` é um pacote separado. **Decisão recomendada: manter
`<x-ui.icon>` como está** (zero dependência de rede, funciona com `currentColor`)
e **consolidar nele todos os SVGs duplicados** do topbar/sidebar/alert/modal/
select/help-button. Adicionar os ícones faltantes: `help-circle`, `alert-circle`,
`info`, `download`, `flag`, `pin`, `list`, `bar-chart`.

---

## 4. Inventário detalhado — `components/layout/*` e componentes de topo

### 4.1 `<x-layout.sidebar>`

- **Arquivo:** `resources/views/components/layout/sidebar.blade.php`
- **Consumo:** 1× em `layouts/app.blade.php`.
- **Props:** nenhuma. Consome `$navigationSections`, injetado por
  `NavigationComposer` (SPEC-17). Cada `$section` tem `->title` e `->items`;
  cada item é um array com `key`, `url`, `label`, `icon` (HTML SVG cru, injetado
  com `{!! !!}`), `active` (bool), `badge` (int|null).
- **Estrutura:** duas árvores duplicadas — `<aside class="d-none d-lg-flex">`
  (desktop, 240px) e um par backdrop + `<aside class="mobile-sidebar-drawer d-lg-none">`
  (mobile, 280px) controlado por `x-show="sidebarOpen"` **inerte**.
- **Dusk:** `sidebar-{key}-link` (desktop) e `sidebar-{key}-link-mobile` (mobile).
  Chaves em uso nos testes: `dashboard, courses, users, organizations, forum,
  forum-moderation, quiz-attempts, settings, audit-logs`.

**Estilos inline chave**

```
aside desktop : width:240px; background: var(--color-neutral-900);
                color: var(--color-neutral-400); min-height: calc(100vh - 60px)
título seção  : font-size:10px; letter-spacing:.1em; uppercase; color: neutral-600
item          : padding:11px 20px; font-size:13px; font-weight:600;
                border-left: 3px solid {accent|transparent};
                background: color-mix(accent 18%, transparent) quando ativo
badge         : min-width:18px; height:18px; background: accent; color: neutral-900
```

**DE ↔ PARA**

```html
<!-- PARA — desktop -->
<aside class="d-none d-lg-flex flex-column flex-shrink-0 sidebar bg-dark" style="width:240px">
  @foreach($sidebarSections as $section)
    <h6 class="sidebar-section-title text-uppercase px-3 pt-4 pb-2 mb-0">{{ $section->title }}</h6>
    <ul class="nav nav-pills flex-column mb-0">
      @foreach($section->items as $item)
        <li class="nav-item">
          <a href="{{ $item['url'] }}"
             dusk="sidebar-{{ $item['key'] }}-link"
             class="nav-link d-flex align-items-center gap-2 {{ $item['active'] ? 'active' : '' }}"
             @if($item['active']) aria-current="page" @endif>
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $item['icon'] !!}</svg>
            <span>{{ $item['label'] }}</span>
            @if($item['badge'] !== null)
              <span class="badge text-bg-primary ms-auto">{{ $item['badge'] }}</span>
            @endif
          </a>
        </li>
      @endforeach
    </ul>
  @endforeach
</aside>

<!-- PARA — mobile: offcanvas nativo do Bootstrap (resolve o drawer quebrado) -->
<div class="offcanvas offcanvas-start bg-dark d-lg-none" tabindex="-1"
     id="app-sidebar-offcanvas" aria-labelledby="app-sidebar-offcanvas-label">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="app-sidebar-offcanvas-label">{{ session('tenant_name') ?? config('app.name') }}</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Fechar menu"></button>
  </div>
  <div class="offcanvas-body p-0">
    {{-- mesma <ul class="nav nav-pills flex-column">, com dusk="sidebar-{key}-link-mobile" --}}
  </div>
</div>
```

| Atual | Bootstrap 5.3 |
|---|---|
| `<aside>` + flex inline | `.d-none.d-lg-flex.flex-column` + largura custom |
| `<nav>` com `<a class="sidebar-item">` | `.nav.nav-pills.flex-column > .nav-item > .nav-link` |
| `sidebar-item.active` (border-left accent + bg 18%) | `.nav-link.active` + override de `--bs-nav-pills-link-active-bg` e `border-left` custom (§8.5) |
| `.sidebar-badge` | `.badge.text-bg-primary.ms-auto` |
| backdrop custom + `x-show` **inerte** | `.offcanvas` + backdrop automático |
| botão fechar SVG | `.btn-close.btn-close-white` |
| bloco `@auth` de avatar/nome/papel | `<x-ui.avatar>` + texto (ver §9) |

> **Duplicação a eliminar:** desktop e mobile repetem o mesmo loop 2×. No alvo,
> extrair um parcial `components/layout/_nav-items.blade.php` que recebe
> `$sections` e um sufixo de `dusk` (`''` ou `'-mobile'`).

---

### 4.2 `<x-layout.topbar>`

- **Arquivo:** `resources/views/components/layout/topbar.blade.php`
- **Consumo:** 1× em `layouts/app.blade.php`.
- **Props:** nenhuma; consome `$brandUrl`, `$loginUrl`, `$logoutUrl`
  (`NavigationComposer`).
- **Composição:** `<x-help-button :key="Route::currentRouteName()">` +
  `<x-notifications-bell />`.
- **Dusk:** `mobile-menu-button`, `topbar-profile-link`.
- **Conteúdo:** botão hambúrguer (mobile, inerte), marca/tenant, campo de busca
  decorativo (**não submete para lugar nenhum — nem `<form>` tem**), help, sino,
  avatar 32×32 com iniciais, nome+papel, link "Meu Perfil", form POST "Sair".

**DE ↔ PARA**

```html
<!-- PARA -->
<nav class="navbar navbar-expand-lg bg-body border-bottom sticky-top px-3" style="height:60px">
  <div class="container-fluid gap-3">
    <button class="btn btn-link text-body d-lg-none p-1" type="button"
            data-bs-toggle="offcanvas" data-bs-target="#app-sidebar-offcanvas"
            aria-controls="app-sidebar-offcanvas" aria-label="Abrir menu"
            dusk="mobile-menu-button">
      <x-ui.icon name="menu" size="20" />
    </button>

    <a class="navbar-brand fw-bolder font-heading" href="{{ $homeUrl }}">
      {{ session('tenant_name') ?? config('app.name', 'Conselho EAD') }}
    </a>

    <form class="d-none d-md-flex flex-grow-1" style="max-width:400px" role="search" action="#">
      <div class="input-group input-group-sm">
        <span class="input-group-text bg-body"><x-ui.icon name="search" size="16" /></span>
        <input type="search" class="form-control" placeholder="Buscar cursos, aulas..." aria-label="Buscar">
      </div>
    </form>

    <div class="d-flex align-items-center gap-2 ms-auto">
      <x-help-button :key="Route::currentRouteName() ?? 'unknown'" />
      <x-notifications-bell />

      @auth
        <div class="dropdown">
          <button class="btn btn-link text-body d-flex align-items-center gap-2 text-decoration-none dropdown-toggle"
                  type="button" data-bs-toggle="dropdown" aria-expanded="false" dusk="topbar-user-menu">
            <x-ui.avatar :name="auth()->user()->name" size="32" />
            <span class="d-none d-sm-block text-start lh-sm">
              <span class="d-block fw-semibold small">{{ auth()->user()->name }}</span>
              <span class="d-block text-secondary" style="font-size:.6875rem">{{ auth()->user()->getRoleNames()->first() ?? 'Usuário' }}</span>
            </span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ route('profile.edit') }}" dusk="topbar-profile-link">Meu Perfil</a></li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <form method="POST" action="{{ $logoutUrl }}">@csrf
                <button type="submit" class="dropdown-item">Sair</button>
              </form>
            </li>
          </ul>
        </div>
      @else
        <a href="{{ $loginUrl }}" class="btn btn-primary btn-sm">Entrar</a>
      @endauth
    </div>
  </div>
</nav>
```

| Atual | Bootstrap |
|---|---|
| `<header class="nav">` flex inline | `.navbar.navbar-expand-lg` |
| marca `<a>` | `.navbar-brand` |
| busca com SVG absoluto | `.input-group` + `.input-group-text` |
| avatar+nome+link+form soltos | `.dropdown > .dropdown-toggle + .dropdown-menu.dropdown-menu-end` |
| hambúrguer `@click` inerte | `data-bs-toggle="offcanvas" data-bs-target="#app-sidebar-offcanvas"` |

> **⚠️ Risco Dusk alto:** `dusk="topbar-profile-link"` está hoje **sempre visível**.
> Se virar item de `.dropdown-menu`, ele fica `display:none` até o dropdown abrir,
> e `$browser->click('@topbar-profile-link')` **falha** (elemento não interagível).
> **Duas opções:** (a) manter "Meu Perfil" como link direto fora do dropdown; ou
> (b) migrar para dropdown e atualizar o teste Dusk para clicar primeiro em
> `@topbar-user-menu`. **Recomendação: (a) na primeira leva**, (b) só com
> atualização de teste no mesmo PR.

> **Campo de busca decorativo:** não tem `<form>`, não tem `name`, não tem rota.
> É um placeholder visual. Marcar como débito — ou implementar busca, ou remover.

---

### 4.3 `<x-layout.alerts>`

- **Arquivo:** `resources/views/components/layout/alerts.blade.php`
- **Consumo:** 2× (`layouts/app.blade.php`, `layouts/guest.blade.php`).
- **Props/slots:** nenhum. Lê `session('success'|'error'|'warning'|'status')` e
  `$errors->any()`, delegando a 5 `<x-ui.alert>`.
- **DE ↔ PARA:** o wrapper vira `<div class="mb-4">`; os erros de validação
  passam a `<ul class="mb-0 ps-3">`. Toda a conversão real acontece dentro de
  `x-ui.alert` (§3.9).

### 4.4 `<x-layout.footer>`

- **Arquivo:** `resources/views/components/layout/footer.blade.php` (12 linhas)
- **Consumo:** 1× (`layouts/app.blade.php`).
- **Conteúdo:** copyright + 3 links **mortos** (`href="#"`): Termos de Uso,
  Privacidade, Suporte.

```html
<!-- PARA -->
<footer class="bg-body border-top py-3 px-4 mt-auto small text-secondary">
  <div class="container-fluid d-flex flex-wrap align-items-center justify-content-between gap-3" style="max-width:1400px">
    <div>© {{ date('Y') }} <strong>{{ session('tenant_name') ?? config('app.name') }}</strong>. Todos os direitos reservados.</div>
    <nav class="d-flex gap-3"><a class="link-secondary text-decoration-none" href="#">Termos de Uso</a>…</nav>
  </div>
</footer>
```

### 4.5 `<x-help-button>`

- **Arquivo Blade:** `resources/views/components/help-button.blade.php`
- **Classe PHP:** `app/View/Components/HelpButton.php`
- **Prop:** `key` (string, obrigatória — normalmente `Route::currentRouteName()`).
  A classe resolve `$article` via `HelpArticleResolverService` (org-específico →
  global → null) com fallback de `org_id` para Admin (`session('active_org_id')`)
  e `null` para guest.
- **Consumo:** **8 ocorrências em 6 arquivos** — `profile/edit` (2),
  `landing/show` (2), `public/certificates/show`, `layouts/guest`,
  `convite/show`, `components/layout/topbar`.
- **Dusk:** `help-button-{key}`, `help-article-content-{key}`,
  `help-placeholder-content-{key}`. Testes citam
  `@help-button-landing` e `@help-button-student.courses.index`.
- **Estrutura:** dois ramos `@if($article) / @else` **quase idênticos** — apenas
  título do modal, texto e um `dusk` diferem. **Duplicação a eliminar.**

```html
<!-- PARA (ramo único) -->
@php
    $modalId = 'help-modal-'.str($key)->slug();
@endphp
<button type="button" class="btn btn-link text-body p-1"
        data-bs-toggle="modal" data-bs-target="#{{ $modalId }}"
        aria-label="Ajuda" dusk="help-button-{{ $key }}">
    <x-ui.icon name="help-circle" size="18" />
</button>

<x-ui.modal :id="$modalId" :title="$article?->title ?? 'Ajuda'" size="md">
    <div dusk="{{ $article ? 'help-article-content-'.$key : 'help-placeholder-content-'.$key }}"
         class="text-break" style="white-space:pre-wrap">
        {{ $article?->content ?? 'Estamos preparando o conteúdo de ajuda desta tela.' }}
    </div>
    <x-slot:actions>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
    </x-slot:actions>
</x-ui.modal>
```

### 4.6 `<x-notifications-bell>`

- **Arquivo:** `resources/views/components/notifications-bell.blade.php`
- **Consumo:** 1× (`components/layout/topbar`).
- **Props:** nenhuma. Faz as queries no próprio Blade
  (`$notifUser->notifications()->latest()->limit(10)->get()` e
  `unreadNotifications()->count()`), com gate `hasRole(GESTOR|ALUNO)`.
- **Contrato JS:** `NotificationBell.js` lê `[data-notifications-bell]`,
  `data-unread-count-url`, `data-mark-all-read-url`, e liga
  `[data-notifications-toggle]` ↔ `[data-notifications-dropdown]`
  (toggle manual de `display`), `[data-notifications-mark-all]`,
  `[data-notifications-item]` + `data-mark-read-url` + `data-notification-id`,
  `[data-notifications-badge]`. Polling de 30 s.
- **Dusk:** `notifications-bell`, `notifications-toggle`, `notifications-badge`,
  `notifications-dropdown`, `notifications-mark-all-read`,
  `notifications-item-{id}`, `notifications-empty` — **todos usados em testes**.

**DE ↔ PARA**

```html
<!-- PARA -->
<div class="dropdown" data-notifications-bell
     data-unread-count-url="{{ route('notifications.unread-count') }}"
     data-mark-all-read-url="{{ route('notifications.read-all') }}"
     dusk="notifications-bell">
  <button type="button" class="btn btn-link text-body position-relative p-1"
          data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
          data-notifications-toggle dusk="notifications-toggle" aria-label="Notificações">
    <x-ui.icon name="bell" size="18" />
    <span class="position-absolute top-0 start-100 translate-middle badge text-bg-danger {{ $unreadCount ? '' : 'd-none' }}"
          data-notifications-badge dusk="notifications-badge">
      {{ $unreadCount > 99 ? '99+' : $unreadCount }}
      <span class="visually-hidden">notificações não lidas</span>
    </span>
  </button>

  <div class="dropdown-menu dropdown-menu-end shadow overflow-auto"
       style="width:340px;max-width:90vw;max-height:420px"
       data-notifications-dropdown dusk="notifications-dropdown">
    <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
      <span class="fw-bold small">Notificações</span>
      <a href="#" class="small link-primary text-decoration-none"
         data-notifications-mark-all dusk="notifications-mark-all-read">marcar todas como lidas</a>
    </div>
    <div data-notifications-list>
      @forelse($recentNotifications as $notification)
        <a href="{{ $notification->data['action_url'] ?? '#' }}"
           class="dropdown-item py-2 border-bottom text-wrap {{ $notification->read_at ? '' : 'fw-semibold bg-primary-subtle' }}"
           data-notifications-item data-notification-id="{{ $notification->id }}"
           data-mark-read-url="{{ route('notifications.read', $notification->id) }}"
           dusk="notifications-item-{{ $notification->id }}">
          <div>{{ $notification->data['message'] ?? 'Nova notificação' }}</div>
          <div class="text-secondary" style="font-size:.6875rem">{{ $notification->created_at->diffForHumans() }}</div>
        </a>
      @empty
        <div class="text-center text-secondary small py-4" dusk="notifications-empty">Nenhuma notificação por aqui.</div>
      @endforelse
    </div>
  </div>
</div>
```

> **⚠️ Conflito JS obrigatório de resolver:** `NotificationBell.js.toggleDropdown()`
> manipula `dropdown.style.display` diretamente. O Bootstrap Dropdown controla a
> visibilidade via classe `.show`. Se ambos coexistirem, o dropdown fica preso
> (`style="display:none"` inline vence `.show`).
> **Escolher uma das duas rotas e aplicar integralmente:**
> - **(A) Bootstrap assume o toggle:** remover `toggleDropdown/closeDropdown` do
>   `NotificationBell.js`, manter apenas polling + mark-read/mark-all-read.
>   `data-bs-auto-close="outside"` evita fechar ao clicar em "marcar todas".
>   **Recomendada.**
> - **(B) JS custom continua:** não usar `.dropdown-menu` do Bootstrap, apenas
>   um `<div>` posicionado com utilitários. Menos ganho.
>
> Também trocar o toggle do badge de `style.display` para
> `classList.toggle('d-none')`.

---

## 5. Inventário detalhado — layouts

### 5.1 `layouts/app.blade.php`

- **Slots:** `$title` (via `<x-slot:title>`, usado em `audit-logs/index`,
  `settings/edit`, `dashboard/index`), `$slot` (conteúdo) e `@yield('content')`
  — **o layout suporta os dois estilos simultaneamente**.
- **Stacks:** `@stack('styles')` (head), `@stack('scripts')` (fim do body).
- **`x-data="{ sidebarOpen: false }"` no `<body>` — inerte.**

```html
<!-- PARA -->
<body class="d-flex flex-column min-vh-100">
  <x-layout.topbar />
  <div class="d-flex flex-fill position-relative">
    <x-layout.sidebar />
    <main class="flex-fill p-4 min-w-0">
      <x-layout.alerts />
      {{ $slot ?? '' }}
      @yield('content')
    </main>
  </div>
  <x-layout.footer />
  @stack('scripts')
</body>
```

| Atual | Bootstrap |
|---|---|
| `body style="background/color/font-family"` | `--bs-body-bg` / `--bs-body-color` / `--bs-body-font-family` (§8) |
| `.app-wrapper` flex column min-height 100vh | `.d-flex.flex-column.min-vh-100` |
| `.app-body` flex 1 | `.d-flex.flex-fill` |
| `.app-main` flex 1 padding 24px min-width 0 | `.flex-fill.p-4` + `min-width:0` custom |
| `x-data="{sidebarOpen:false}"` | **remover** (offcanvas do Bootstrap) |

### 5.2 `layouts/guest.blade.php`

- **Estrutura:** split 42%/58% — painel institucional escuro à esquerda
  (`d-none d-lg-flex`, `--color-neutral-900`, régua accent 56×2px, `<h1>` 36px)
  e área de formulário à direita (380px centralizado) com `<x-help-button>`
  posicionado absolutamente no canto superior direito.
- **Consumo:** telas de `auth/*`, `convite/show`, etc.

```html
<!-- PARA -->
<body class="min-vh-100">
  <div class="d-flex min-vh-100 w-100">
    <div class="d-none d-lg-flex flex-column bg-dark text-white p-5" style="width:42%;flex:none">
      <span class="fw-bolder fs-4 font-heading">{{ session('tenant_name') ?? config('app.name') }}</span>
      <div class="my-auto">
        <div class="bg-primary mb-4" style="width:56px;height:2px"></div>
        <h1 class="display-5 fw-bolder lh-1 mb-3 font-heading" style="max-width:10ch">Acesse a plataforma</h1>
        <p class="text-white-50" style="max-width:32ch">Capacitação técnica continuada, provas interativas e emissão de certificados oficiais.</p>
      </div>
      <div class="small text-white-50">© {{ date('Y') }} Plataforma EAD. Todos os direitos reservados.</div>
    </div>

    <div class="flex-fill d-flex flex-column justify-content-center align-items-center p-4 position-relative">
      <div class="position-absolute top-0 end-0 m-3"><x-help-button :key="Route::currentRouteName() ?? 'unknown'" /></div>
      <div class="w-100" style="max-width:380px">
        <x-layout.alerts />
        {{ $slot ?? '' }}
        @yield('content')
      </div>
    </div>
  </div>
  @stack('scripts')
</body>
```

> **Nota:** `layouts/guest.blade.php` **não tem `@stack('styles')`** — presente só
> no `app`. Padronizar os dois.

---

## 6. Inventário detalhado — parciais compartilhados

### 6.1 Parciais de formulário (`_form.blade.php`)

| Arquivo | Variável esperada | Campos | Componentes usados |
|---|---|---|---|
| `courses/_form.blade.php` | `$course` | `title`, `description` (textarea), `workload_hours` (number), `is_published` (checkbox cru) | 3× `x-ui.input` |
| `courses/modules/_form.blade.php` | `$module` | `title`, `description` | 2× `x-ui.input` |
| `organizations/_form.blade.php` | `$organization` | `name`, `slug`, `cnpj`, `status` (select), `logo` (file cru) | 3× `x-ui.input`, 1× `x-ui.select` |
| `modules/lessons/_form.blade.php` | `$lesson` | `title`, `type` (select), `content_text`, `image` (file), `pdf` (file), `youtube_url`, preview iframe, `is_published` | 3× `x-ui.input`, 1× `x-ui.select` + **JS inline em `@push('scripts')`** |

**Padrão comum a eliminar:** wrapper `<div style="display:flex;flex-direction:column;gap:20px;max-width:560px">`
→ `<div class="d-flex flex-column gap-3" style="max-width:35rem">` ou, melhor,
`<div class="row g-3">` com `col-12`/`col-md-6`.

**Checkbox cru (3 ocorrências)** — `courses/_form:29`, `modules/lessons/_form:77`,
`quizzes/partials/_question-form` (múltiplas):

```html
<!-- DE -->
<div class="field" style="display:flex;align-items:center;gap:8px">
  <input type="checkbox" id="is_published" name="is_published" value="1" @checked(...) style="width:16px;height:16px" />
  <label for="is_published" style="font-size:13px;font-weight:600">Publicado</label>
</div>

<!-- PARA -->
<div class="form-check">
  <input class="form-check-input" type="checkbox" id="is_published" name="is_published" value="1" @checked(...)>
  <label class="form-check-label" for="is_published">Publicado</label>
</div>
```

**File input cru (3 ocorrências)** — `organizations/_form:38` (`logo`),
`modules/lessons/_form:36` (`image`, `dusk="lesson-image-input"`),
`modules/lessons/_form:48` (`pdf`, `dusk="lesson-pdf-input"`):

```html
<!-- PARA -->
<div class="mb-3">
  <label for="logo" class="form-label">Logo</label>
  <input type="file" class="form-control @error('logo') is-invalid @enderror" id="logo" name="logo" accept="image/*">
  @if($organization->logo_path)<div class="form-text">Logo atual: {{ $organization->logo_path }}</div>@endif
  @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
```

**Preview de YouTube** (`modules/lessons/_form:66-73`):
`aspect-ratio: 16/9` inline → `.ratio.ratio-16x9` do Bootstrap.
O `style.display = 'flex'/'none'` do JS inline deve virar
`classList.toggle('d-none')`.

### 6.2 `courses/modules/_list.blade.php` e `quizzes/partials/_question-list.blade.php`

Estrutura idêntica: `<ul data-reorder-url>` com `<li draggable="true" data-id>`
consumido por `ModuleReorder.js`.

| Atual | Bootstrap |
|---|---|
| `<ul style="list-style:none;display:flex;flex-direction:column;gap:8px">` | `<ul class="list-group">` (o `data-reorder-url` **permanece**) |
| `<li style="display:flex;justify-content:space-between;border:1px solid;cursor:grab">` | `<li class="list-group-item d-flex align-items-center justify-content-between gap-2" style="cursor:grab">` |
| handle `⠿` com `opacity:.5` | `<x-ui.icon name="grip-vertical" class="opacity-50" />` |
| `<li>` vazio com `border: 1px dashed` | `<x-ui.empty-state>` (§9) — mas o `dusk="question-list-empty"` deve ser preservado |
| grupo de botões à direita | `.btn-group.btn-group-sm` |

> ⚠️ `data-reorder-url`, `data-id`, `draggable="true"` e todos os `dusk` são
> contrato de `ModuleReorder.js` + testes — **preservar verbatim**.

### 6.3 `forum/partials/_topic.blade.php` e `_reply.blade.php`

- `_topic`: linha clicável com badge "Fixado" + form POST de pin/unpin.
- `_reply`: cartão de resposta com autor, data, botão "Denunciar"
  (`data-forum-report-button`, `data-postable-type`, `data-postable-id`,
  `data-modal-target="report-modal"` — contrato de `ForumReportModal.js`) e
  form DELETE.

| Atual | Bootstrap |
|---|---|
| `<div style="border:1px solid;background:surface;margin-bottom:8px">` | `.list-group-item` (dentro de `.list-group`) ou `.card.mb-2 > .card-body` |
| metadados 12px neutral-600 | `.small.text-secondary` |
| `class="btn btn-ghost"` com padding inline | `.btn.btn-sm.btn-link` / `.btn-outline-danger` para "Apagar" |
| `data-modal-target="report-modal"` | `data-bs-toggle="modal" data-bs-target="#report-modal"` **ou** manter via fachada `ModalManager` (ver §3.8) |

### 6.4 `forum/partials/_edit-history-modal.blade.php`

- Emite um badge "Editado em …" + botão `ver histórico` +
  um `<x-ui.modal>` por post (`$modalId`, `$label`, `$editedAt`, `$history`).
- **Botão dentro de `<span>` dentro de `<div>` de metadados:** o botão usa
  `class="btn btn-ghost"` com `display:inline;padding:0;border:0;background:none`
  → **`class="btn btn-link p-0 align-baseline small"`**.
- Lista do histórico → `.list-group.list-group-flush` com
  `.list-group-item`; conteúdo anterior em `<pre class="mb-0 text-wrap small">`
  ou `<div class="text-break" style="white-space:pre-wrap">`.

### 6.5 `audit-logs/partials/_diff-modal.blade.php`

- Um **único** modal compartilhado por todas as 25 linhas da página, preenchido
  por `AuditLogDiffModal.js` a partir do `dataset` do botão clicado.
- `<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">` →
  `<div class="row g-3"><div class="col-md-6">…</div><div class="col-md-6">…</div></div>`.
- `<pre style="background:...5%;padding:12px;overflow-x:auto;white-space:pre-wrap">` →
  `<pre class="bg-body-secondary p-3 small mb-0 text-break" style="white-space:pre-wrap">`.
- `dusk="audit-diff-event|audit-diff-old|audit-diff-new"` — **preservar**.

### 6.6 Parciais de sala de aula (`classroom/partials/*`)

| Arquivo | Elemento | PARA |
|---|---|---|
| `_video.blade.php` | wrapper `padding-top:56.25%` + iframe absoluto | `.ratio.ratio-16x9` (mas **cuidado**: `LessonPlayer.js` substitui o conteúdo do `#youtube-player-{id}` pela YouTube IFrame API — manter o `id` e os `data-*`) |
| `_pdf.blade.php` | `<iframe style="width:100%;height:600px;border:1px solid">` | `<iframe class="w-100 border" style="height:600px">` |
| `_pdf.blade.php` | link "Baixar PDF" accent bold | `.btn.btn-link.fw-bold` ou `.link-primary` |
| `_text-image.blade.php` | `<img style="max-width:100%;height:auto;border:1px solid">` | `<img class="img-fluid border grayscale">` |
| `_quiz-placeholder.blade.php` | `<div style="padding:24px;text-align:center;border:1px dashed">` | `<x-ui.empty-state>` (§9) com `dusk="quiz-placeholder"` preservado |

Todos os três usam o par `x-ui.badge[data-completion-badge]` +
`x-ui.button[data-mark-complete-url]` — ver a armadilha de `display` em §3.4.

### 6.7 `quizzes/partials/_question-form.blade.php`

O parcial mais complexo (131 linhas). Contrato com `QuizBuilder.js`:
`data-question-form`, `data-question-type-select`, `data-essay-hint`,
`data-options-container`, `data-options-list`, `data-option-row`,
`data-option-id`, `data-correct-checkbox`, `data-remove-option-btn`,
`data-add-option-btn`, `data-option-template` (um `<template>` clonado com
placeholder `__INDEX__`). **Todos os `data-*` e os 10 `dusk` devem sobreviver.**

| Atual | Bootstrap |
|---|---|
| `.quiz-option-row` flex + gap 8px | `.input-group` (checkbox como `.input-group-text > .form-check-input` + `.form-control` + `.btn.btn-outline-danger`) |
| `<input type="checkbox" style="width:16px;height:16px">` | `.form-check-input.mt-0` |
| `<input type="text" style="flex:1;height:36px;...">` | `.form-control.form-control-sm` |
| botão `✕` `class="btn btn-ghost"` | `.btn.btn-outline-danger.btn-sm` com `<x-ui.icon name="x" size="14" />` |
| `<p data-essay-hint style="display:none">` | `.form-text.d-none` + `classList.toggle('d-none')` no `QuizBuilder.js` |

> ⚠️ `QuizBuilder.js` provavelmente manipula `style.display` do
> `[data-options-container]` e `[data-essay-hint]`. Auditar e trocar por
> `d-none` **no mesmo PR**.

---

## 7. Tabela mestra DE ↔ PARA

| Componente / Arquivo | Tag | Consumidores | Alvo Bootstrap 5.3 |
|---|---|---|---|
| `ui/button` | `<x-ui.button>` | 82 / 45 arq. | `.btn` + `.btn-primary`/`.btn-outline-secondary`/`.btn-link` + `.btn-sm`/`.btn-lg` + `.w-100` |
| `ui/input` | `<x-ui.input>` | 64 / 21 arq. | `.mb-3 > .form-label + .form-control + .form-text + .invalid-feedback` / `.is-invalid` |
| `ui/select` | `<x-ui.select>` | 9 / 7 arq. | `.form-select` (remover chevron SVG e wrapper `position:relative`) |
| `ui/badge` | `<x-ui.badge>` | 27 / 21 arq. | `.badge.text-bg-primary` / `.text-bg-danger` / `.text-bg-secondary` / `.border.bg-transparent` |
| `ui/card` | `<x-ui.card>` | 26 / 25 arq. | `.card > .card-body > .card-title/.card-subtitle/.card-text` + `.card-footer` + `.shadow-sm/.shadow/.shadow-lg` |
| `ui/stat-card` | `<x-ui.stat-card>` | 4 / 1 arq. | `.card.shadow-sm.h-100 > .card-body` + `.display-6` + `.badge` (grid: `.row.row-cols-md-2.row-cols-xl-4.g-3`) |
| `ui/table` | `<x-ui.table>` | 11 / 11 arq. | `.table-responsive > .table.table-hover.align-middle` + `thead.table-light` |
| `ui/modal` | `<x-ui.modal>` | 10 / 7 arq. | `.modal.fade > .modal-dialog(.modal-sm/.modal-lg/.modal-dialog-scrollable) > .modal-content > .modal-header/.modal-body/.modal-footer`; `data-bs-toggle="modal"`, `data-bs-dismiss="modal"`, `.btn-close` |
| `ui/alert` | `<x-ui.alert>` | 10 / 3 arq. | `.alert.alert-primary/.alert-danger/.alert-warning` + `.alert-dismissible.fade.show` + `.btn-close[data-bs-dismiss="alert"]` |
| `ui/icon` | `<x-ui.icon>` | 3 / 2 arq. | **Manter** (Bootstrap não fornece ícones); consolidar todos os SVGs duplicados |
| `layout/sidebar` | `<x-layout.sidebar>` | 1 | desktop `.nav.nav-pills.flex-column` + mobile `.offcanvas.offcanvas-start` |
| `layout/topbar` | `<x-layout.topbar>` | 1 | `.navbar.navbar-expand-lg` + `.navbar-brand` + `.input-group` + `.dropdown` |
| `layout/alerts` | `<x-layout.alerts>` | 2 | `.mb-4` wrapper; conversão em `ui/alert` |
| `layout/footer` | `<x-layout.footer>` | 1 | `<footer class="border-top py-3 mt-auto small text-secondary">` |
| `help-button` | `<x-help-button>` | 8 / 6 arq. | `.btn.btn-link` + `data-bs-toggle="modal"` + `<x-ui.modal>` |
| `notifications-bell` | `<x-notifications-bell>` | 1 | `.dropdown > .dropdown-toggle + .dropdown-menu.dropdown-menu-end` + badge `.position-absolute.top-0.start-100.translate-middle` |
| `layouts/app` | — | todas autenticadas | `.d-flex.flex-column.min-vh-100` |
| `layouts/guest` | — | auth/convite | `.d-flex.min-vh-100` + `bg-dark` split |
| `*/_form` (4) | `@include` | 8 telas | `.row.g-3` + `.form-check` + `.form-control[type=file]` |
| `*/_list` (2) | `@include` | 2 telas | `.list-group > .list-group-item` (manter `data-reorder-url`/`draggable`) |
| `forum/_topic`, `_reply` | `@include` | 2 telas | `.list-group-item` / `.card` |
| `forum/_edit-history-modal` | `@include` | 2 telas | `.btn.btn-link.p-0` + `<x-ui.modal>` + `.list-group-flush` |
| `audit-logs/_diff-modal` | `@include` | 1 tela | `<x-ui.modal size="lg">` + `.row.g-3` + `<pre class="bg-body-secondary">` |
| `classroom/_video` | `@include` | 1 tela | `.ratio.ratio-16x9` |
| `classroom/_pdf` | `@include` | 1 tela | `iframe.w-100.border` + `.btn.btn-link` |
| `classroom/_text-image` | `@include` | 1 tela | `.img-fluid.border.grayscale` |
| `classroom/_quiz-placeholder` | `@include` | 1 tela | `<x-ui.empty-state>` |

---

## 8. Camada de tema: overrides SCSS/CSS obrigatórios (Modernist)

### 8.1 Pré-requisito: instalar o pipeline SCSS

```bash
vendor/bin/sail npm i -D sass-embedded
```

Substituir `resources/css/app.css` por `resources/css/app.scss` e apontar o
`vite.config.js` para ele. **Remover `tailwindcss` e `@tailwindcss/vite`**
(dependência morta confirmada) — ou, se o time preferir remoção em PR separado,
ao menos retirar o plugin do `vite.config.js`.

### 8.2 Estrutura de `resources/css/app.scss`

```scss
// 1) fontes + tokens Modernist (mantidos: outros CSS/JS ainda leem --color-*)
@import "tokens";        // o :root atual, verbatim
@import "fonts";         // os 3 @font-face Archivo

// 2) overrides de variáveis ANTES do Bootstrap
@import "theme-variables";

// 3) Bootstrap completo
@import "bootstrap/scss/bootstrap";

// 4) camada custom mínima (o que Bootstrap não expressa)
@import "modernist-layer";
```

### 8.3 `_theme-variables.scss` — mandato Modernist

```scss
// ── Raio 0 em TODO o sistema (mandato de marca) ────────────────────────────
$border-radius:          0;
$border-radius-sm:       0;
$border-radius-lg:       0;
$border-radius-xl:       0;
$border-radius-xxl:      0;
$border-radius-pill:     0;     // sim, inclusive o pill
$enable-rounded:         false; // desliga o mixin border-radius() globalmente

// ── Tipografia Archivo ─────────────────────────────────────────────────────
$font-family-sans-serif: "Archivo", system-ui, -apple-system, sans-serif;
$headings-font-family:   "Archivo", system-ui, sans-serif;
$headings-font-weight:   800;                 // --font-heading-weight
$font-weight-bolder:     800;
$font-size-base:         0.875rem;            // 14px, o tamanho base real hoje
$headings-line-height:   1.2;

// ── Paleta Modernist ───────────────────────────────────────────────────────
$primary:    #ec3013;   // --color-accent
$secondary:  #7d7979;   // --color-neutral-600
$danger:     #e15b47;   // --color-accent-2
$warning:    #bab6b6;   // --color-neutral-400 (Modernist não tem amarelo)
$success:    #2d2b2b;   // Modernist é monocromático + accent; NÃO usar verde
$info:       #605d5d;   // --color-neutral-700
$light:      #f8f4f4;   // --color-neutral-100
$dark:       #2d2b2b;   // --color-neutral-900

$theme-colors: (
  "primary":   $primary,
  "secondary": $secondary,
  "success":   $success,
  "info":      $info,
  "warning":   $warning,
  "danger":    $danger,
  "light":     $light,
  "dark":      $dark,
  "accent-2":  #e15b47,   // variante extra usada por x-ui.badge/alert
);

// ── Superfícies ────────────────────────────────────────────────────────────
$body-bg:        #f3f2f2;                                   // --color-bg
$body-color:     #201e1d;                                   // --color-text
$body-secondary-bg: #eae9e9;                                // --color-surface
$border-color:   rgba(#201e1d, .4);                         // --color-divider
$border-width:   1px;

// ── Componentes ────────────────────────────────────────────────────────────
$card-bg:                    #eae9e9;
$card-border-color:          $border-color;
$card-spacer-y:              1.25rem;   // 20px
$card-spacer-x:              1.25rem;
$card-cap-bg:                rgba(#2d2b2b, .04);

$input-bg:                   #eae9e9;
$input-border-color:         $border-color;
$input-color:                #201e1d;
$input-height:               40px;
$input-focus-border-color:   $primary;
$input-focus-box-shadow:     none;      // Modernist usa outline, não glow
$form-select-indicator-color: #201e1d;

$table-bg:                   #eae9e9;
$table-border-color:         $border-color;
$table-hover-bg:             rgba(#2d2b2b, .04);
$table-th-font-weight:       800;

$modal-content-bg:           #eae9e9;
$modal-content-border-color: $border-color;
$modal-backdrop-bg:          #2d2b2b;
$modal-backdrop-opacity:     .65;       // igual ao color-mix atual
$modal-sm:                   400px;
$modal-md:                   560px;
$modal-lg:                   720px;

$offcanvas-bg-color:         #2d2b2b;
$offcanvas-color:            #bab6b6;
$offcanvas-horizontal-width: 280px;     // idêntico ao drawer atual

$box-shadow-sm: 0 1px 2px rgba(#2d2b2b, .14);
$box-shadow:    0 3px 10px rgba(#2d2b2b, .16);
$box-shadow-lg: 0 12px 32px rgba(#2d2b2b, .22);

$badge-font-size:      .6875rem;  // 11px
$badge-font-weight:    700;
$badge-padding-y:      .1875rem;  // 3px
$badge-padding-x:      .5rem;     // 8px

// ── Focus ──────────────────────────────────────────────────────────────────
$focus-ring-color:  $primary;
$focus-ring-width:  2px;
$focus-ring-opacity: 1;
$focus-ring-blur:   0;
```

### 8.4 CSS custom properties do Bootstrap 5.3 (ajustes em runtime)

O 5.3 expõe variáveis por componente — usar para casos pontuais **sem
recompilar**:

```css
:root {
  /* aliases globais */
  --bs-primary:            #ec3013;
  --bs-primary-rgb:        236, 48, 19;
  --bs-body-bg:            var(--color-bg);
  --bs-body-color:         var(--color-text);
  --bs-body-font-family:   var(--font-body);
  --bs-border-radius:      0;
  --bs-border-radius-sm:   0;
  --bs-border-radius-lg:   0;
  --bs-border-color:       var(--color-divider);
  --bs-emphasis-color:     var(--color-text);
  --bs-secondary-bg:       var(--color-surface);
}

/* botão: nível componente (5.3) */
.btn {
  --bs-btn-border-radius: 0;
  --bs-btn-font-weight: 600;
}
.btn-primary {
  --bs-btn-bg: #ec3013;
  --bs-btn-border-color: #ec3013;
  --bs-btn-hover-bg: #dd2b0f;     /* --color-accent-600 */
  --bs-btn-hover-border-color: #dd2b0f;
  --bs-btn-active-bg: #ae1800;    /* --color-accent-700 */
  --bs-btn-color: #f8f4f4;
}

/* nav-pills do sidebar */
.sidebar .nav-pills {
  --bs-nav-pills-border-radius: 0;
  --bs-nav-pills-link-active-bg: color-mix(in srgb, var(--color-accent) 18%, transparent);
  --bs-nav-pills-link-active-color: var(--color-neutral-100);
  --bs-nav-link-color: var(--color-neutral-400);
  --bs-nav-link-hover-color: var(--color-neutral-100);
  --bs-nav-link-padding-y: .6875rem;   /* 11px */
  --bs-nav-link-padding-x: 1.25rem;    /* 20px */
  --bs-nav-link-font-size: .8125rem;   /* 13px */
}

/* tabela */
.table {
  --bs-table-bg: var(--color-surface);
  --bs-table-hover-bg: color-mix(in srgb, var(--color-neutral-900) 4%, transparent);
  font-size: .8125rem; /* 13px, igual ao atual */
}

/* card, modal, alert */
.card   { --bs-card-border-radius: 0; --bs-card-inner-border-radius: 0; --bs-card-bg: var(--color-surface); }
.modal  { --bs-modal-border-radius: 0; --bs-modal-inner-border-radius: 0; --bs-modal-bg: var(--color-surface); }
.alert  { --bs-alert-border-radius: 0; }
.badge  { --bs-badge-border-radius: 0; }
.dropdown-menu { --bs-dropdown-border-radius: 0; --bs-dropdown-bg: var(--color-surface); }
.offcanvas     { --bs-offcanvas-bg: var(--color-neutral-900); --bs-offcanvas-color: var(--color-neutral-400); }
```

> **Regra de ouro:** `$enable-rounded: false` no SCSS já zera tudo. As variáveis
> `--bs-*-border-radius` acima são cinto-e-suspensório para o caso de o build
> completo ser adotado antes do pipeline SCSS (fase intermediária consumindo
> `bootstrap.min.css` do dist).

### 8.5 `_modernist-layer.scss` — o que Bootstrap **não** expressa

Estas regras **não** têm utilitário/variável equivalente e precisam de CSS custom:

```scss
// 1) Kicker/eyebrow (usado por card, input, stat-card, sidebar section title)
.kicker,
.form-kicker,
.card-kicker {
  font-size: .6875rem;          // 11px
  letter-spacing: .08em;
  text-transform: uppercase;
  font-weight: 700;
  color: var(--color-accent);
  line-height: 1;
}
.sidebar-section-title {
  font-size: .625rem;           // 10px
  letter-spacing: .1em;
  color: var(--color-neutral-600);
  font-weight: 700;
}

// 2) Barra accent à esquerda do item ativo do sidebar (Bootstrap não tem)
.sidebar .nav-link {
  border-left: 3px solid transparent;
  font-weight: 600;
  &.active { border-left-color: var(--color-accent); }
}

// 3) Botão-ícone quadrado (o BS não tem .btn-icon)
.btn-icon {
  --bs-btn-padding-x: .375rem;
  --bs-btn-padding-y: .375rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}

// 4) Cabeçalho de tabela Modernist
.table > thead th {
  font-size: .6875rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: var(--color-neutral-700);
}

// 5) Tipografia de heading (o $headings-font-family cobre h1-h6,
//    mas números de stat-card / navbar-brand precisam explícito)
.font-heading { font-family: var(--font-heading); font-weight: 800; letter-spacing: -.02em; }

// 6) Imagens em preto e branco (mandato Modernist) — já existe, MANTER
.grayscale { filter: grayscale(1) contrast(1.08); }
.org-logo, .grayscale.org-logo, img.org-logo { filter: none !important; }

// 7) Focus accent — já existe, MANTER (o $focus-ring do BS usa box-shadow,
//    o Modernist usa outline sólido)
:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 2px; }

// 8) A borda "40% opacidade sobre o texto" do divider não é uma cor sólida —
//    color-mix() não sobrevive ao Sass, por isso $border-color usa rgba().
```

### 8.6 Paginação — habilitar tema Bootstrap 5

**Obrigatório**, senão as 9 telas paginadas continuam sem estilo:

```php
// app/Providers/AppServiceProvider.php — boot()
use Illuminate\Pagination\Paginator;

public function boot(): void
{
    Paginator::useBootstrapFive();
}
```

---

## 9. Catálogo alvo de componentes pós-migração

Princípio: **um componente por padrão visual recorrente**. Componentes existentes
mantêm a tag (para não quebrar 260+ call-sites) e apenas trocam o markup interno.
Componentes novos absorvem inline styles hoje espalhados pelas telas.

### 9.1 Mantidos (mesma tag, markup novo)

| Tag | Props | Markup emitido | Substitui |
|---|---|---|---|
| `<x-ui.button>` | `variant=primary\|secondary\|outline\|ghost\|danger`, `size=sm\|md\|lg`, `block`, `icon`, `type`, `href`, `disabled`, `loading=false` | `.btn.btn-{variant}[.btn-{size}][.w-100]` + spinner opcional | 82 call-sites |
| `<x-ui.input>` | `label,name,type,value,placeholder,error,required,disabled,kicker,hint,rows` | `.mb-3 > .form-label + .form-control + .form-text + .invalid-feedback` | 64 call-sites |
| `<x-ui.select>` | `label,name,options,selected,placeholder,error,required,disabled,multiple` | `.mb-3 > .form-label + .form-select + .invalid-feedback` | 9 call-sites |
| `<x-ui.badge>` | `variant=primary\|danger\|secondary\|outline\|dark` | `.badge.text-bg-*` | 27 call-sites |
| `<x-ui.card>` | `title,kicker,meta,shadow=none\|sm\|md\|lg,border`, slots `image/footer` | `.card > .card-body > .card-title/.card-text` | 26 call-sites |
| `<x-ui.stat-card>` | `kicker,value,delta,deltaVariant,icon` | `.card.shadow-sm.h-100` | dashboard |
| `<x-ui.table>` | `headers,hoverable,striped,responsive=true,size`, slots `header/footer` | `.table-responsive > .table.table-hover` | 11 call-sites |
| `<x-ui.modal>` | `id,title,size=sm\|md\|lg\|xl,dismissable,static,scrollable,centered`, slot `actions` | `.modal.fade > .modal-dialog > .modal-content` | 10 call-sites |
| `<x-ui.alert>` | `variant,dismissable,icon,title` | `.alert.alert-*.alert-dismissible` | 10 call-sites |
| `<x-ui.icon>` | `name,size,class,strokeWidth` | `<svg>` Lucide inline | **expandir**: absorve ~14 SVGs duplicados |
| `<x-layout.topbar>` | — | `.navbar` | — |
| `<x-layout.sidebar>` | — | `.nav-pills` + `.offcanvas` | — |
| `<x-layout.alerts>` | — | wrapper | — |
| `<x-layout.footer>` | — | `<footer>` | — |
| `<x-help-button>` | `key` | `.btn-link` + modal | 8 call-sites |
| `<x-notifications-bell>` | — | `.dropdown` | 1 call-site |

### 9.2 Novos componentes (lacunas atuais)

#### `<x-ui.pagination :paginator="$users" />`
```blade
@props(['paginator'])
@if($paginator->hasPages())
    <nav aria-label="Paginação" class="mt-3">{{ $paginator->onEachSide(1)->links() }}</nav>
@endif
```
Bootstrap: `.pagination > .page-item > .page-link` (via `Paginator::useBootstrapFive()`).
**Substitui:** os 9 `{{ $x->links() }}` crus e hoje sem estilo.

#### `<x-ui.page-header title="…" subtitle="…" kicker="…">`
```blade
@props(['title', 'subtitle' => null, 'kicker' => null])
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    @if($kicker)<div class="kicker mb-1">{{ $kicker }}</div>@endif
    <h1 class="h3 font-heading mb-0">{{ $title }}</h1>
    @if($subtitle)<p class="text-secondary small mb-0 mt-1">{{ $subtitle }}</p>@endif
  </div>
  @isset($actions)<div class="d-flex gap-2">{{ $actions }}</div>@endisset
</div>
```
**Substitui:** o bloco `<h1 style="font-family:var(--font-heading);font-weight:800;…">`
+ botão de ação repetido em ~25 telas de index/create/edit.

#### `<x-ui.breadcrumb :items="[['label'=>'Cursos','url'=>route('courses.index')],['label'=>$course->title]]" />`
```blade
<nav aria-label="breadcrumb">
  <ol class="breadcrumb mb-3">
    @foreach($items as $item)
      <li class="breadcrumb-item {{ $loop->last ? 'active' : '' }}" @if($loop->last) aria-current="page" @endif>
        @if(!$loop->last && ($item['url'] ?? null))<a href="{{ $item['url'] }}">{{ $item['label'] }}</a>@else{{ $item['label'] }}@endif
      </li>
    @endforeach
  </ol>
</nav>
```
**Substitui:** os links "← Voltar" soltos (`x-ui.button` com `arrow-left`) nas telas
aninhadas (`modules/lessons/*`, `courses/modules/*`, `quizzes/*`, `forum/*`).

#### `<x-ui.empty-state icon="inbox" title="…" description="…">`
```blade
@props(['icon' => 'inbox', 'title', 'description' => null])
<div {{ $attributes->merge(['class' => 'text-center text-secondary border border-dashed p-5']) }}>
  <x-ui.icon :name="$icon" size="32" class="opacity-50 mb-3" />
  <p class="fw-semibold mb-1">{{ $title }}</p>
  @if($description)<p class="small mb-3">{{ $description }}</p>@endif
  @isset($action)<div>{{ $action }}</div>@endisset
</div>
```
**Substitui:** `courses/modules/_list:33`, `quizzes/partials/_question-list:51`
(`dusk="question-list-empty"`), `classroom/partials/_quiz-placeholder`
(`dusk="quiz-placeholder"`), `notifications-bell` empty (`dusk="notifications-empty"`),
e ~8 `@empty` de telas index. **Preservar o `dusk` via `$attributes`.**

#### `<x-ui.field name="…" label="…">` (wrapper genérico)
```blade
@props(['name', 'label' => null, 'hint' => null, 'required' => false, 'kicker' => null])
<div class="mb-3">
  @if($kicker)<span class="kicker d-block mb-1">{{ $kicker }}</span>@endif
  @if($label)<label for="{{ $name }}" class="form-label">{{ $label }}@if($required)<span class="text-danger ms-1">*</span>@endif</label>@endif
  {{ $slot }}
  @if($hint)<div class="form-text">{{ $hint }}</div>@endif
  @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
```
**Substitui:** os wrappers `<div class="field" style="…">` crus de
`courses/_form`, `organizations/_form`, `modules/lessons/_form` (checkbox e file).

#### `<x-ui.checkbox name="is_published" label="Publicado" :checked="…" />`
`.form-check > .form-check-input + .form-check-label`.
**Substitui:** 3 checkboxes crus + as ~8 checkboxes de opção do `_question-form`.

#### `<x-ui.file-upload name="logo" label="Logo" accept="image/*" :current="$organization->logo_path" />`
`.form-control[type=file]` + `.form-text` com o arquivo atual + `.invalid-feedback`.
**Substitui:** `organizations/_form:38`, `modules/lessons/_form:36`, `:48`
(preservar `dusk="lesson-image-input"` / `lesson-pdf-input`).

#### `<x-ui.dropdown label="…" align="end">`
`.dropdown > .btn.dropdown-toggle[data-bs-toggle="dropdown"] + .dropdown-menu[.dropdown-menu-end]`.
**Substitui:** o bloco de usuário do topbar; futuros menus "ações" das tabelas
(hoje botões soltos lado a lado em `users/index`, `courses/index`, `organizations/index`).

#### `<x-ui.offcanvas id="app-sidebar-offcanvas" placement="start" title="…">`
`.offcanvas.offcanvas-start > .offcanvas-header/.offcanvas-body`.
**Substitui:** todo o drawer mobile de `layout/sidebar` (backdrop + aside + transições Alpine mortas).

#### `<x-ui.tabs :items="[…]" />`
`.nav.nav-tabs > .nav-item > .nav-link[data-bs-toggle="tab"]` + `.tab-content > .tab-pane`.
**Necessário para:** `profile/edit` (hoje 2 cards empilhados: dados + senha),
`settings/edit` (5 inputs de contextos distintos), `quizzes/edit` (questões + config).

#### `<x-ui.toast>` + container
`.toast-container.position-fixed.bottom-0.end-0.p-3 > .toast`.
**Substitui:** o `NotificationService.js` (que hoje provavelmente injeta markup ad-hoc)
e complementa os flash alerts, que atualmente empurram o conteúdo da página.

#### `<x-ui.progress :value="60" label="Progresso do curso" />`
`.progress[role=progressbar] > .progress-bar`.
**Necessário para:** `student/courses/index` e `classroom/show` — o progresso do
aluno hoje é apenas texto/badge.

#### `<x-ui.list-group>` / `<x-ui.list-group-item>`
`.list-group / .list-group-item[.list-group-item-action]`.
**Substitui:** `courses/modules/_list`, `quizzes/partials/_question-list`,
`forum/partials/_topic`, `forum/partials/_reply`, `_edit-history-modal`.

#### `<x-ui.avatar :name="$user->name" size="32" :src="null" />`
Iniciais em quadrado (radius 0) `.grayscale` ou `<img class="rounded-0 grayscale">`.
**Substitui:** o bloco de iniciais duplicado em `layout/topbar:50` e `layout/sidebar:81`.

#### `<x-ui.spinner size="sm" />` + `loading` em `<x-ui.button>`
`.spinner-border.spinner-border-sm` / `<button class="btn" disabled><span class="spinner-border spinner-border-sm me-2"></span>…`.
**Necessário para:** `CsvImporter.js` (import em chunks), `SmartInvitationForm.js`,
`LessonPlayer.js` (marcar como concluída) — hoje nenhum feedback de carregamento.

#### `<x-ui.confirm-modal id="…" title="…" :action="…" method="DELETE">`
Modal + form embutido; dispara por `data-bs-toggle="modal"`.
**Substitui:** os ~12 `<form method="POST">@method('DELETE')<button>Remover</button></form>`
sem confirmação alguma (`courses/modules/_list:25`, `_question-list:43`,
`forum/_reply:47`, `organizations/index`, `users/index`, `certificates/index`…).
**Ganho de UX real:** hoje nenhuma exclusão pede confirmação.

#### `<x-ui.filter-bar :action="route('users.index')">`
`.card.mb-3 > .card-body > form.row.g-2.align-items-end` + `.col-md-*` + botões
"Filtrar"/"Limpar".
**Substitui:** as barras de filtro inline de `audit-logs/index`,
`courses/enrollments/index`, `users/index`, `certificates/index`.

#### `<x-layout.section-title>` / `<x-ui.divider>`
`<hr class="my-4">` + heading — pequenos, mas eliminam dezenas de
`<div style="border-top:1px solid var(--color-divider);margin:…">`.

### 9.3 Resumo do catálogo alvo (28 tags)

```
x-ui.alert          x-ui.avatar        x-ui.badge         x-ui.breadcrumb
x-ui.button         x-ui.card          x-ui.checkbox      x-ui.confirm-modal
x-ui.divider        x-ui.dropdown      x-ui.empty-state   x-ui.field
x-ui.file-upload    x-ui.filter-bar    x-ui.icon          x-ui.input
x-ui.list-group     x-ui.list-group-item                  x-ui.modal
x-ui.offcanvas      x-ui.page-header   x-ui.pagination    x-ui.progress
x-ui.select         x-ui.spinner       x-ui.stat-card     x-ui.table
x-ui.tabs           x-ui.toast
x-layout.alerts     x-layout.footer    x-layout.sidebar   x-layout.topbar
x-help-button       x-notifications-bell
```

---

## 10. Seletores `dusk` — contrato inviolável

**76 atributos `dusk` vivem na camada de componentes/parciais** (de 316 no total
do projeto). Todo markup reescrito **deve** reemitir o `dusk` no **mesmo
elemento semanticamente equivalente** e com **string idêntica**.

### 10.1 `components/help-button.blade.php`
| Linha | Seletor |
|---|---|
| 25, 50 | `help-button-{{ $key }}` |
| 36 | `help-article-content-{{ $key }}` |
| 61 | `help-placeholder-content-{{ $key }}` |

### 10.2 `components/layout/topbar.blade.php`
| Linha | Seletor | Risco |
|---|---|---|
| 19 | `mobile-menu-button` | baixo (vira toggle de offcanvas) |
| 62 | `topbar-profile-link` | **ALTO** se mover para dentro de `.dropdown-menu` (ver §4.2) |

### 10.3 `components/layout/sidebar.blade.php`
| Linha | Seletor |
|---|---|
| 31 | `sidebar-{{ $item['key'] }}-link` |
| 102 | `sidebar-{{ $item['key'] }}-link-mobile` |

Chaves comprovadamente usadas em `tests/Browser`:
`sidebar-dashboard-link`, `sidebar-courses-link`, `sidebar-users-link`,
`sidebar-organizations-link`, `sidebar-forum-link`, `sidebar-forum-moderation-link`,
`sidebar-quiz-attempts-link`, `sidebar-settings-link`, `sidebar-audit-logs-link`.

### 10.4 `components/notifications-bell.blade.php`
`notifications-bell`, `notifications-toggle`, `notifications-badge`,
`notifications-dropdown`, `notifications-mark-all-read`,
`notifications-item-{{ $notification->id }}`, `notifications-empty`
— **todos verificados em `tests/Browser`**.

### 10.5 Parciais

| Arquivo | Seletores |
|---|---|
| `forum/partials/_topic` | `topic-row-{id}`, `open-topic-{id}`, `pinned-badge-{id}`, `pin-form-{id}`, `pin-topic-{id}` |
| `forum/partials/_reply` | `reply-{id}`, `report-reply-{id}`, `delete-reply-form-{id}`, `delete-reply-{id}`, `reply-content-{id}` |
| `forum/partials/_edit-history-modal` | `edit-history-trigger-{modalId}`, `edit-history-entry-{id}`, `edit-history-empty-{modalId}` |
| `audit-logs/partials/_diff-modal` | `audit-diff-event`, `audit-diff-old`, `audit-diff-new` |
| `courses/modules/_list` | `module-list`, `module-row-{id}`, `manage-lessons-{id}`, `edit-module-{id}`, `delete-module-form-{id}`, `delete-module-{id}` |
| `quizzes/partials/_question-list` | `question-list`, `question-row-{id}`, `edit-question-{id}`, `delete-question-form-{id}`, `delete-question-{id}`, `question-list-empty` |
| `quizzes/partials/_question-form` | `question-form-{suffix}`, `question-text-{suffix}`, `question-type-{suffix}`, `option-correct-{suffix}-{i}`, `option-text-{suffix}-{i}`, `remove-option-{suffix}-{i}`, `add-option-{suffix}`, `question-submit-{suffix}` |
| `modules/lessons/_form` | `lesson-type-select`, `lesson-image-input`, `lesson-pdf-input`, `lesson-youtube-input`, `youtube-preview` (e o JS inline seleciona por `[dusk="lesson-type-select"]` e `[dusk="lesson-youtube-input"]` — **o JS depende do dusk**, não só o teste) |
| `classroom/partials/_video` | `video-player-{id}`, `lesson-completed-badge` |
| `classroom/partials/_pdf` | `pdf-viewer-{id}`, `pdf-download-{id}`, `lesson-completed-badge`, `mark-complete-button` |
| `classroom/partials/_text-image` | `lesson-image-{id}`, `lesson-content-{id}`, `lesson-completed-badge`, `mark-complete-button` |
| `classroom/partials/_quiz-placeholder` | `quiz-placeholder` (2×), `start-quiz` |

### 10.6 Regras operacionais para os agentes

1. **Nunca renomear, nunca remover** um `dusk`.
2. Ao trocar o elemento (ex.: `<span>` → `<div>`), o `dusk` acompanha o elemento
   que o teste **interage/assere**, não o wrapper novo.
3. `dusk` em componentes é repassado por `$attributes->merge()` — ao reescrever
   `x-ui.button`/`x-ui.badge`/`x-ui.modal`, **manter o `$attributes->merge()`**
   ou os 82+27+10 call-sites perdem seus seletores em massa.
4. Após cada PR de migração rodar, no mínimo:
   `vendor/bin/sail artisan dusk --filter=<SuiteDaTela>`.
5. Elementos que ficam ocultos por padrão (dropdown, offcanvas, modal) exigem
   `$browser->waitFor(...)`/`->click('@toggle')` antes — auditar cada teste que
   tocar `@topbar-profile-link`, `@notifications-*` e `@sidebar-*-link-mobile`.

---

## 11. Anti-padrões, riscos e lacunas de acessibilidade

### 11.1 Anti-padrões estruturais

| # | Anti-padrão | Onde | Ação |
|---|---|---|---|
| A1 | **Atributos Alpine.js sem Alpine instalado** (`x-data`, `x-show`, `x-cloak`, `x-transition:*`, `@click`, `@click.outside`, `@keydown.escape.window`) | `layouts/app`, `ui/modal`, `ui/alert`, `layout/sidebar`, `layout/topbar` | Remover **todos**; substituir por `data-bs-*` |
| A2 | Classe Tailwind dentro de `style=""` | `ui/table:25` (`style="divide-y divide-color-divider;"`) | Remover — CSS inválido |
| A3 | Props declaradas e nunca lidas | `ui/table` (`hoverable`, `striped`), `ui/alert` (`icon`) | Implementar como `.table-hover`/`.table-striped` e ícone por variante |
| A4 | Slots declarados e nunca usados | `ui/card` (`image`, `kickerSlot`, `titleSlot`, `metaSlot`, `footer`) | Simplificar API |
| A5 | Ramos `@if/@else` quase idênticos | `help-button` (44 linhas duplicadas) | Unificar com `$article?->title ?? 'Ajuda'` |
| A6 | Loop de navegação duplicado desktop/mobile | `layout/sidebar` (2× o mesmo `@foreach`) | Extrair `_nav-items.blade.php` |
| A7 | SVG inline duplicado em vez de `<x-ui.icon>` | topbar (3), sidebar (3), alert (2), modal (1), select (1), help-button (2) | Consolidar em `x-ui.icon` |
| A8 | Queries Eloquent dentro do Blade | `notifications-bell:23-24` (2 queries por request em **toda** tela autenticada) | Mover para um `ViewComposer` (fora do escopo desta migração, mas registrar) |
| A9 | Campo de busca decorativo sem `<form>`/rota | `layout/topbar:39` | Implementar ou remover |
| A10 | Links de rodapé mortos (`href="#"` ×3) | `layout/footer:7-9` | Implementar ou remover |
| A11 | Exclusões sem confirmação | 12+ forms `@method('DELETE')` | `<x-ui.confirm-modal>` (§9.2) |
| A12 | Paginação sem tema | 9 telas | `Paginator::useBootstrapFive()` |
| A13 | Tailwind + plugin Vite como dependência morta | `package.json`, `vite.config.js` | Remover |
| A14 | `bunny('Instrument Sans')` no `vite.config.js` baixa fonte externa nunca usada (o sistema usa Archivo self-hosted) | `vite.config.js` | Remover |

### 11.2 Estilos inline que **não** têm equivalente em utilitário Bootstrap
(precisam da camada `_modernist-layer.scss` de §8.5)

| Estilo | Onde | Por quê |
|---|---|---|
| `letter-spacing: .08em / .1em / .05em` | kickers, sidebar titles, badges, `th` | Bootstrap não tem utilitário de letter-spacing |
| `font-size: 10px / 11px` | kickers, badges, sidebar titles | menor que `.small` (0.875em) |
| `border-left: 3px solid var(--color-accent)` no item ativo | sidebar | `.border-start` é 1px fixo |
| `background: color-mix(in srgb, var(--color-accent) 18%, transparent)` | sidebar ativo | `.bg-primary-subtle` do 5.3 usa outra fórmula; validar visualmente |
| `filter: grayscale(1) contrast(1.08)` | `.grayscale` | sem utilitário |
| `width: 240px` / `280px` / `340px` / `380px` / `42%` | sidebar, dropdown, guest layout | fora da escala de utilitários |
| `min-height: calc(100vh - 60px)` | sidebar desktop | sem utilitário |
| `aspect-ratio` com `padding-top: 56.25%` | `_video` | **tem** equivalente: `.ratio.ratio-16x9` |
| `backdrop-filter: blur(2px)` | modal backdrop, sidebar backdrop | sem utilitário; o BS usa opacidade sólida |
| `letter-spacing: -.02em` em números grandes | stat-card | sem utilitário |
| `white-space: pre-wrap` | help-button, `_reply`, `_edit-history-modal`, `_diff-modal` | sem utilitário |
| `cursor: grab` | `_list` reorderáveis | sem utilitário |
| `max-width: 10ch / 32ch / 420px` | guest layout, `_question-list` | sem utilitário |

### 11.3 Riscos de quebra de JavaScript (checklist obrigatório por PR)

| Módulo | Dependência do markup atual | Mudança exigida |
|---|---|---|
| `ModalManager.js` | `.dialog-backdrop`, `.dialog`, `[data-modal-target]`, `[data-modal-dismiss]`, `body.modal-open` | Reescrever como fachada de `bootstrap.Modal` mantendo API pública `open/close/toggle/closeAll` |
| `ForumEditHistory.js` | injeta `ModalManager` | OK se a fachada for preservada |
| `ForumReportModal.js` | injeta `ModalManager`, `[data-forum-report-button]`, `data-postable-type/-id` | idem; `data-*` preservados |
| `AuditLogDiffModal.js` | injeta `ModalManager`, `[dusk=audit-diff-*]` | idem |
| `NotificationBell.js` | `dropdown.style.display` manual, `badge.style.display` | **Conflito com `.dropdown` do Bootstrap** — ver §4.6; trocar por `.d-none`/deixar o BS controlar |
| `LessonPlayer.js` | `badge.style.display = 'inline-flex'` | trocar por `classList.remove('d-none')` |
| `QuizBuilder.js` | `[data-options-container]`, `[data-essay-hint]` com toggle de `style.display` | auditar e trocar por `d-none` |
| `ModuleReorder.js` | `[data-reorder-url]`, `[data-id]`, `draggable` | **sem mudança** se os atributos forem preservados no `.list-group` |
| `CsvImporter.js`, `SmartInvitationForm.js` | markup das telas (fora deste escopo) | validar em PR próprio |
| `modules/lessons/_form` (script inline) | `[dusk="lesson-type-select"]`, `[dusk="lesson-youtube-input"]`, `contentFields.style.display` | trocar toggles por `d-none`; **JS acoplado a `dusk`** — risco duplo |
| `app.js` | não importa `bootstrap` | adicionar `import * as bootstrap from 'bootstrap'; window.bootstrap = bootstrap;` |

### 11.4 Lacunas de acessibilidade identificadas

| # | Problema | Onde | Correção com Bootstrap |
|---|---|---|---|
| ACC-1 | Modal sem `aria-labelledby` apontando ao título; `aria-modal` presente mas sem `tabindex="-1"` nem focus trap real | `ui/modal` | `.modal[tabindex="-1"][aria-labelledby]` + focus trap nativo do `bootstrap.Modal` |
| ACC-2 | Alert sem `role="alert"` — leitores de tela não anunciam flash messages | `ui/alert` | adicionar `role="alert"` (o BS já espera isso) |
| ACC-3 | Badge de notificações não lidas sem texto alternativo (só número) | `notifications-bell:47` | `<span class="visually-hidden">notificações não lidas</span>` |
| ACC-4 | Dropdown de notificações sem `aria-expanded`/`aria-haspopup`; abre por JS custom | `notifications-bell` | `.dropdown-toggle[data-bs-toggle="dropdown"][aria-expanded]` |
| ACC-5 | `<th>` sem `scope="col"` em todas as 11 tabelas | `ui/table:16` | `<th scope="col">` |
| ACC-6 | Item ativo do sidebar sem `aria-current="page"` (só cor/borda) | `layout/sidebar` | `aria-current="page"` no `.nav-link.active` |
| ACC-7 | Navegação por links soltos sem `<ul>/<li>` semânticos | `layout/sidebar`, `layout/footer` | `.nav > .nav-item > .nav-link` |
| ACC-8 | Campo de busca do topbar sem `<label>` nem `aria-label` | `layout/topbar:39` | `aria-label="Buscar"` + `role="search"` no form |
| ACC-9 | Mensagem de erro do input não vinculada ao campo (`aria-describedby` ausente) | `ui/input`, `ui/select` | `.invalid-feedback` + `aria-describedby="{name}-error"` |
| ACC-10 | `hint` não vinculado por `aria-describedby` | `ui/input` | idem |
| ACC-11 | Botões de ícone com só SVG e sem texto — alguns têm `aria-label`, outros não (`_question-form` `✕`, `_list` handle `⠿`) | vários | `aria-label` obrigatório + `<span class="visually-hidden">` |
| ACC-12 | Drawer mobile sem `role="dialog"`, sem retorno de foco, sem `Esc` (e inerte) | `layout/sidebar` | `.offcanvas` resolve tudo nativamente |
| ACC-13 | Contraste: `--color-neutral-600` (#7d7979) sobre `--color-surface` (#eae9e9) ≈ 3.6:1 — **abaixo de 4.5:1 (WCAG AA)** para texto de 11–12px, usado em metadados de card, footer, hints, timestamps | transversal | escurecer para `--color-neutral-700` (#605d5d ≈ 5.8:1) nos textos pequenos |
| ACC-14 | `<iframe>` de PDF e de preview do YouTube sem `title` | `_pdf:17`, `_form:69`, `_video:29` | adicionar `title="…"` |
| ACC-15 | Ordem de foco: o modal é irmão do botão que o abre, mas o backdrop `position:fixed` não faz trap — hoje é possível tabular para trás do modal | `ui/modal` | resolvido pelo `bootstrap.Modal` |

---

## 12. Ordem de execução recomendada

| Fase | Escopo | Motivo |
|---|---|---|
| **F0** | Pipeline: `sass-embedded`, `app.scss` com `_theme-variables` + Bootstrap completo + `_modernist-layer`; `import bootstrap` em `app.js`; remover Tailwind e `bunny()`; `Paginator::useBootstrapFive()` | Sem isso nenhuma classe Bootstrap tem efeito |
| **F1** | Átomos sem JS: `ui/button`, `ui/badge`, `ui/card`, `ui/table`, `ui/input`, `ui/select`, `ui/stat-card` | Maior alcance (219 call-sites), risco de JS zero |
| **F2** | `ui/alert` + `layout/alerts` (**fecha BUG-004**) | Ganho visível, JS trivial |
| **F3** | `ui/modal` + fachada `ModalManager.js` + 3 módulos dependentes + `_edit-history-modal` + `_diff-modal` + `help-button` | Bloco JS acoplado — um PR único |
| **F4** | `layout/sidebar` (offcanvas, **corrige o drawer mobile morto**) + `layout/topbar` + `notifications-bell` + `NotificationBell.js` | Alto risco de Dusk — PR isolado com suíte completa |
| **F5** | `layouts/app` + `layouts/guest` + `layout/footer` | Depende de F1–F4 |
| **F6** | Parciais `_form` / `_list` / forum / classroom / quizzes | Depende de F1 |
| **F7** | Componentes novos (§9.2) e refatoração das telas para usá-los | Última — é onde os 634 inline styles das views morrem |

**Portão de qualidade por fase:** `vendor/bin/sail npm run build` +
`vendor/bin/sail artisan test --compact` + `vendor/bin/sail artisan dusk`
(suíte completa nas fases F3 e F4).
