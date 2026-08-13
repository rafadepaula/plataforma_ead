---
name: bootstrap-conventions
description: >
  Concrete code patterns, snippets, and guardrails for writing Bootstrap 5.3
  UI in the Plataforma EAD: the `$attributes->merge()` anonymous-component
  wrapper pattern, the `<x-ui.*>` vs `<x-layout.*>` naming rule, the
  forbidden-patterns list (no inline `style=`, no hand-rolled
  modal/toast/dropdown JS, no Tailwind classes, no invented CSS class when a
  Bootstrap utility exists), the `.is-invalid`/`.invalid-feedback` Laravel
  validation pattern, the `dusk=` preservation rule, the
  utility-first-then-component-class decision tree, and the exact SCSS
  override block. Use whenever writing or migrating a Blade view, a
  `<x-ui.*>`/`<x-layout.*>` component, a SCSS partial, or a JS module that
  drives a Bootstrap widget.
license: MIT
metadata:
  feature: bootstrap
  role: conventions
  specs:
    - spec/front_migration/06-skills-and-agents.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Bootstrap Conventions

## 1. Componente Blade anônimo que embrulha markup Bootstrap

Todo componente de UI é um **componente anônimo** (só o `.blade.php`, sem classe
PHP) em `resources/views/components/`. O padrão canônico tem 4 partes: `@props`,
bloco `@php` que resolve as classes, `$attributes->merge()`, e slots.

```blade
{{-- resources/views/components/ui/button.blade.php --}}
@props([
    'variant' => 'primary',   // primary | secondary | ghost | danger
    'size' => 'md',           // sm | md | lg
    'block' => false,
    'icon' => null,
    'type' => 'button',
    'href' => null,
    'disabled' => false,
])

@php
    $variantClass = match ($variant) {
        'secondary' => 'btn-outline-secondary',
        'ghost' => 'btn-link text-body text-decoration-none',
        'danger' => 'btn-danger',
        default => 'btn-primary',
    };

    $classes = collect([
        'btn',
        $variantClass,
        $size === 'sm' ? 'btn-sm' : ($size === 'lg' ? 'btn-lg' : null),
        $block ? 'w-100' : null,
        'd-inline-flex align-items-center justify-content-start gap-2 text-start',
    ])->filter()->implode(' ');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon) <x-ui.icon :name="$icon" /> @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" @disabled($disabled) {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon) <x-ui.icon :name="$icon" /> @endif
        {{ $slot }}
    </button>
@endif
```

Regras do padrão:

1. **`$attributes->merge(['class' => ...])` sempre**, nunca `class="{{ $classes }}"`
   solto. `merge()` é o que permite a tela acrescentar utilities
   (`<x-ui.button class="mt-3 w-100">`) e — crucialmente — o que faz o
   `dusk="..."` passado pela tela chegar ao elemento renderizado.
2. **Nunca** `$attributes->merge(['style' => ...])`. `style` não entra em
   componente nenhum (ver §3).
3. `@props` documenta a API do componente. Prop com valor default = opcional;
   prop sem default (`'title'`) = obrigatória e falha ruidosamente se faltar.
4. Variantes mapeam para **classes reais do Bootstrap** via `match()`. Se a
   variante pedida não tem equivalente Bootstrap, a resposta é uma classe da
   camada 3 do projeto — nunca um `style=`.
5. Ordem das classes no `implode`: base → variante → tamanho → layout/utilities.
6. Componente que embrulha um widget JS do Bootstrap emite os `data-bs-*`
   **ele mesmo**; a tela nunca escreve `data-bs-toggle` à mão.

### Modal (wrapper de `bootstrap.Modal`)

```blade
{{-- resources/views/components/ui/modal.blade.php --}}
@props([
    'id',
    'title' => 'Confirmação',
    'size' => 'md',          // sm | md | lg | xl
    'dismissable' => true,
    'static' => false,       // backdrop estático (não fecha ao clicar fora)
])

@php
    $sizeClass = match ($size) {
        'sm' => 'modal-sm',
        'lg' => 'modal-lg',
        'xl' => 'modal-xl',
        default => '',
    };
@endphp

<div class="modal fade"
     id="{{ $id }}"
     tabindex="-1"
     aria-labelledby="{{ $id }}-label"
     aria-hidden="true"
     @if ($static) data-bs-backdrop="static" data-bs-keyboard="false" @endif
     {{ $attributes->merge(['dusk' => 'modal-'.$id]) }}>
    <div class="modal-dialog modal-dialog-centered {{ $sizeClass }}">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="{{ $id }}-label">{{ $title }}</h2>
                @if ($dismissable)
                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Fechar"
                            dusk="modal-{{ $id }}-close"></button>
                @endif
            </div>

            <div class="modal-body">
                {{ $slot }}
            </div>

            @isset($actions)
                <div class="modal-footer">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    </div>
</div>
```

Uso na tela — o gatilho é declarativo, sem uma linha de JS:

```blade
<x-ui.button variant="danger" data-bs-toggle="modal" data-bs-target="#confirm-delete"
             dusk="open-delete-modal">
    Excluir aluno
</x-ui.button>

<x-ui.modal id="confirm-delete" title="Confirmar exclusão">
    <p class="mb-0">Tem certeza de que deseja remover este aluno?</p>
    <x-slot:actions>
        <x-ui.button variant="ghost" data-bs-dismiss="modal" dusk="cancel-delete">Cancelar</x-ui.button>
        <form method="POST" action="{{ route('users.destroy', $user) }}">
            @csrf @method('DELETE')
            <x-ui.button variant="danger" type="submit" dusk="confirm-delete">Excluir</x-ui.button>
        </form>
    </x-slot:actions>
</x-ui.modal>
```

---

## 2. Nomenclatura: `<x-ui.*>` vs `<x-layout.*>`

| Namespace | Diretório | O que é | Critério objetivo |
| :--- | :--- | :--- | :--- |
| `<x-ui.*>` | `resources/views/components/ui/` | Widget reutilizável, sem conhecimento de rota, de papel (role) ou de sessão. Recebe tudo por prop. | Se você consegue renderizá-lo num teste isolado só passando props, é `ui`. |
| `<x-layout.*>` | `resources/views/components/layout/` | Peça estrutural do chrome da aplicação, singular por página, ciente de `auth()`, `route()`, roles Spatie e `session('active_org_id')`. | Se ele chama `auth()->user()`, `request()->routeIs()` ou `@role`, é `layout`. |

- `ui`: `button`, `card`, `modal`, `badge`, `input`, `select`, `textarea`,
  `checkbox`, `table`, `stat-card`, `icon`, `alert`, `toast`, `pagination`,
  `empty-state`, `tabs`, `dropdown`, `progress`, `avatar`, `breadcrumb`.
- `layout`: `topbar`, `sidebar`, `footer`, `alerts` (container de flash),
  `page-header`.
- Componentes de domínio que não são nem chrome nem widget genérico
  (`<x-help-button>`) ficam na **raiz** de `components/`, como já é hoje.
- Nomes de arquivo em `kebab-case`; nada de subpastas dentro de `ui/`.

---

## 3. Padrões proibidos (lista fechada)

Cada item abaixo é motivo de **rejeição** em review — sem discussão sobre gosto.

1. **`style="..."` em qualquer view, componente ou layout.** Zero exceções em
   `resources/views/`, com uma única ressalva: `certificates/pdf.blade.php`
   (dompdf, ver `bootstrap-maintenance`) e valores genuinamente dinâmicos
   computados em runtime (barra de progresso: `style="width: {{ $pct }}%"`) —
   e mesmo esses preferem `.progress-bar` + `aria-valuenow`.
   Verificação: `grep -rn 'style="' resources/views --include='*.blade.php'`.
2. **JS artesanal de modal, toast ou dropdown.** Nada de `ModalManager`,
   `NotificationService` artesanal, `document.addEventListener('click', …)` para
   abrir menu. Use `bootstrap.Modal`, `bootstrap.Toast`, `bootstrap.Dropdown`, ou
   os atributos `data-bs-*`.
3. **Classes Tailwind.** `flex`, `grid`, `gap-4`, `text-sm`, `bg-white`,
   `rounded-lg`, `px-4`, `hidden`, `space-y-*`, `w-full`. O Tailwind está morto no
   projeto e será removido. Atenção às colisões: `gap-4` e `border` existem nos
   dois mundos com semântica diferente; `d-none` (Bootstrap) ≠ `hidden`;
   `w-100` (Bootstrap) ≠ `w-full`.
4. **Classe CSS inventada onde existe utility do Bootstrap.** Não crie
   `.mt-large`, `.flex-center`, `.text-muted-custom`. Use `mt-4`,
   `d-flex align-items-center justify-content-center`, `text-body-secondary`.
5. **Classes fantasma do sistema antigo.** `.btn-ghost`, `.btn-block`,
   `.btn-icon`, `.dialog`, `.dialog-backdrop`, `.dialog-title`, `.tag-accent`,
   `.tag-outline`, `.tag-neutral`, `.tag-accent-2`, `.field`, `.input`,
   `.elev-sm|md|lg`. Nenhuma existe em CSS algum — são resíduo. Mapeamento em §4.
6. **`var(--color-*)` em novo código.** As custom properties passam a ser output
   de `_variables.scss`. Novo CSS usa `$variáveis` SCSS ou as
   `--bs-*` do Bootstrap.
7. **Hex hardcoded** em view ou em SCSS de componente. Só `_variables.scss`
   contém literais de cor.
8. **`border-radius` diferente de zero**, `rounded`, `rounded-*`, `rounded-pill`,
   `rounded-circle`. O mandato Modernist é canto reto sistêmico. Exceção
   única: `.rounded-circle` em avatar, **se e somente se** aprovado
   explicitamente — por padrão, avatar é quadrado.
9. **Bootstrap Icons / CDNs externos.** Ícones continuam sendo Lucide inline via
   `<x-ui.icon name="..."/>`.
10. **`!important`** fora de `.org-logo` (exceção histórica documentada).
11. **`<table>` sem `.table-responsive`** no wrapper.
12. **Markup Bootstrap cru numa tela** quando existe (ou deveria existir) um
    `<x-ui.*>` para ele — ver mandato de componentização em
    `bootstrap-architecture`.

---

## 4. Tabela de tradução (sistema antigo → Bootstrap)

| Antigo (classe fantasma / inline) | Novo |
| :--- | :--- |
| `.btn.btn-primary` + inline padding | `btn btn-primary` |
| `.btn.btn-secondary` | `btn btn-outline-secondary` |
| `.btn.btn-ghost` | `btn btn-link text-body text-decoration-none` |
| `.btn-block` | `w-100` |
| `.btn-icon` | `btn` + `d-inline-flex align-items-center gap-2` |
| `.card` + inline border/shadow | `card` (+ `shadow-sm`) |
| `.elev-sm` / `.elev-md` / `.elev-lg` | `shadow-sm` / `shadow` / `shadow-lg` |
| `.dialog` / `.dialog-backdrop` | `modal` / gerado pelo `bootstrap.Modal` |
| `.tag-accent` | `badge text-bg-primary` |
| `.tag-outline` | `badge border border-secondary text-body` |
| `.tag-neutral` | `badge text-bg-secondary` |
| `.tag-accent-2` | `badge text-bg-danger` |
| `.field` (wrapper de label+input) | `mb-3` |
| `.input` | `form-control` |
| select com chevron SVG + `appearance:none` | `form-select` |
| `.table` custom | `table` dentro de `.table-responsive` |
| `style="display:flex; gap:12px"` | `d-flex gap-3` |
| `style="margin-bottom:16px"` | `mb-4` (spacer 4 = `1rem`) |
| `style="color: var(--color-accent)"` | `text-primary` |
| `style="background: var(--color-surface)"` | `bg-body-secondary` |
| `style="text-align:left"` | `text-start` |
| `style="filter: grayscale(1)"` | `grayscale` (classe do projeto, camada 3) |

Escala de espaçamento do Bootstrap (com `$spacer: 1rem`): `1`=4px, `2`=8px,
`3`=12px, `4`=16px, `5`=24px — configurável em `_variables.scss` para casar com
`--space-*` do Modernist (4/8/12/16/24/32). Ver §7.

---

## 5. Árvore de decisão: utility primeiro, classe de componente depois

Ao precisar de um estilo, percorra nesta ordem e **pare no primeiro que resolve**:

```
1. Existe utility do Bootstrap?            → use (d-flex, mb-3, text-primary, gap-2)
2. Existe componente do Bootstrap?         → use (.card, .modal, .nav, .table)
3. Dá para expressar com 2–4 utilities?    → use as utilities na tela
4. O mesmo conjunto se repete em ≥3 telas? → vire um <x-ui.*> (agente
                                              bootstrap-component-author)
5. É estrutura visual única do produto     → classe da camada 3, em
   (sidebar, player, stat-card)?             resources/scss/components/_*.scss
6. Nada acima serve?                       → gere a utility pela Utility API do
                                              Bootstrap em resources/scss/_utilities.scss
7. Ainda nada?                             → PARE. Isto é sinal de decisão de
                                              design faltando; escale, não improvise.
```

Regra prática: **mais de 5 classes utilitárias no mesmo elemento repetidas em mais
de uma tela** = componente faltando. **Menos de 3 utilities** = nunca vale uma
classe nova.

---

## 6. Validação Laravel: `.is-invalid` + `.invalid-feedback`

Padrão único para todo campo de formulário do sistema. O componente resolve o
estado de erro sozinho a partir de `$errors`; a tela só passa `name`.

```blade
{{-- resources/views/components/ui/input.blade.php --}}
@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'required' => false,
    'help' => null,
])

@php
    $id = $attributes->get('id', $name);
    $hasError = $errors->has($name);
    $describedBy = collect([
        $help ? "{$id}-help" : null,
        $hasError ? "{$id}-error" : null,
    ])->filter()->implode(' ');
@endphp

<div class="mb-3">
    @if ($label)
        <label for="{{ $id }}" class="form-label">
            {{ $label }}@if ($required) <span class="text-primary" aria-hidden="true">*</span>@endif
        </label>
    @endif

    <input type="{{ $type }}"
           name="{{ $name }}"
           id="{{ $id }}"
           value="{{ old($name, $value) }}"
           @required($required)
           @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
           @if ($hasError) aria-invalid="true" @endif
           {{ $attributes->merge(['class' => 'form-control'.($hasError ? ' is-invalid' : '')]) }}>

    @if ($help)
        <div id="{{ $id }}-help" class="form-text">{{ $help }}</div>
    @endif

    @error($name)
        <div id="{{ $id }}-error" class="invalid-feedback" dusk="error-{{ $name }}">{{ $message }}</div>
    @enderror
</div>
```

Regras:

- `.invalid-feedback` só aparece quando o input irmão imediatamente anterior tem
  `.is-invalid` — é regra de CSS do Bootstrap (`.is-invalid ~ .invalid-feedback`).
  Portanto **não** envolva o input num `<div>` extra entre ele e o feedback.
- `dusk="error-{campo}"` é o contrato de teste para asserção de erro de validação.
- Para `<select>`: mesma lógica com `.form-select is-invalid`.
- Para checkbox/radio: `.form-check-input is-invalid` + `.invalid-feedback` dentro
  do `.form-check`.
- Erros vindos de resposta JSON (fluxos AJAX: `CsvImporter`, `SmartInvitationForm`)
  aplicam `.is-invalid` via JS no campo e escrevem o texto no
  `.invalid-feedback` correspondente — nunca criam markup de erro novo.
- Nunca usar `<x-ui.alert>` para erro de campo; alert é para erro de formulário
  inteiro / flash de sessão.

---

## 7. O bloco exato de override SCSS

> **ATENÇÃO — o bloco abaixo é o ESBOÇO original, não o código em produção.**
> A implementação real vive em **`resources/scss/app.scss`** (`_variables.scss`
> nunca foi criado) e diverge em três pontos que mudam o que você escreve na
> Blade:
>
> 1. **`$spacers` é o mapa PADRÃO do Bootstrap** (`0..5`, `$spacer: 1rem`)
>    mesclado com as chaves `1x 2x 3x 4x 6x 8x` (4/8/12/16/24/32px).
>    Escala numérica: `1`=4px, `2`=8px, `3`=**16px**, `4`=**24px**, `5`=48px —
>    e **`p-6`/`g-6` não existem**. Para valores exatos do Modernist use as
>    chaves `x`: 12px = `gap-3x`, 16px = `mb-4x`, 24px = `mt-6x`, 32px = `p-8x`.
>    Nunca assuma que `mb-4` vale 16px; vale 24px.
> 2. **Superfície é `--bs-tertiary-bg`**: o projeto define
>    `$body-tertiary-bg: $modernist-surface`. Para `--color-surface` use
>    **`bg-body-tertiary`**, não `bg-body-secondary`.
> 3. **`$success: $modernist-accent`** (vermelho) por mandato Modernist, e
>    `$danger: $modernist-accent-2`. `alert-success` e `alert-danger` são dois
>    vermelhos próximos — não conte com verde/vermelho para diferenciar estado.
>
> Antes de usar qualquer degrau de espaçamento ou token de cor, confira em
> `resources/scss/app.scss`. O bloco a seguir fica como registro da intenção
> de design:

```scss
// =====================================================================
// Modernist Design System → Bootstrap 5.3 variable overrides
// Este arquivo é a ÚNICA fonte de verdade do design. Nada de hex fora daqui.
// Importado ENTRE bootstrap/functions e bootstrap/bootstrap.
// =====================================================================

// ---------- Paleta base ----------
$white:    #ffffff;
$black:    #000000;

$modernist-bg:        #f3f2f2;
$modernist-surface:   #eae9e9;
$modernist-text:      #201e1d;
$modernist-accent:    #ec3013;
$modernist-accent-2:  #e15b47;

// Rampa neutra 100–900 (Modernist)
$neutral-100: #f8f4f4;
$neutral-200: #eae7e7;
$neutral-300: #d7d3d3;
$neutral-400: #bab6b6;
$neutral-500: #9b9797;
$neutral-600: #7d7979;
$neutral-700: #605d5d;
$neutral-800: #444141;
$neutral-900: #2d2b2b;

// ---------- Mapeamento semântico do Bootstrap ----------
$primary:   $modernist-accent;
$secondary: $neutral-700;
$success:   #2f7d4f;
$info:      $neutral-600;
$warning:   #b8860b;
$danger:    $modernist-accent-2;
$light:     $neutral-100;
$dark:      $neutral-900;

$body-bg:               $modernist-bg;
$body-color:            $modernist-text;
$body-secondary-bg:     $modernist-surface;
$body-secondary-color:  $neutral-700;
$border-color:          rgba($modernist-text, .4);

// ---------- MANDATO: canto reto sistêmico ----------
$enable-rounded:        false;   // desliga todo border-radius do Bootstrap
$border-radius:         0;
$border-radius-sm:      0;
$border-radius-lg:      0;
$border-radius-xl:      0;
$border-radius-xxl:     0;
$border-radius-pill:    0;

// ---------- Tipografia ----------
$font-family-sans-serif: "Archivo", system-ui, -apple-system, "Segoe UI", sans-serif;
$font-family-base:       $font-family-sans-serif;
$font-size-base:         .875rem;   // 14px, base do Modernist
$line-height-base:       1.5;

$headings-font-family:   $font-family-sans-serif;
$headings-font-weight:   800;       // --font-heading-weight
$headings-color:         $modernist-text;

// ---------- Espaçamento (casa com --space-*) ----------
$spacer: 1rem;
$spacers: (
  0: 0,
  1: $spacer * .25,   //  4px
  2: $spacer * .5,    //  8px
  3: $spacer * .75,   // 12px
  4: $spacer,         // 16px
  5: $spacer * 1.5,   // 24px
  6: $spacer * 2,     // 32px
);

// ---------- Sombras (Modernist) ----------
$enable-shadows:  false;
$box-shadow-sm:   0 1px 2px  rgba($neutral-900, .14);
$box-shadow:      0 3px 10px rgba($neutral-900, .16);
$box-shadow-lg:   0 12px 32px rgba($neutral-900, .22);

// ---------- Foco ----------
$focus-ring-width:   2px;
$focus-ring-color:   $primary;
$focus-ring-opacity: 1;
$focus-ring-blur:    0;

// ---------- Componentes ----------
$btn-font-weight:        600;
$btn-padding-y:          .625rem;
$btn-padding-x:          1.125rem;
$input-btn-focus-width:  2px;

$card-bg:                $white;
$card-border-color:      $border-color;
$card-cap-bg:            transparent;

$modal-content-bg:       $modernist-surface;
$modal-content-border-color: $border-color;
$modal-backdrop-bg:      $neutral-900;
$modal-backdrop-opacity: .65;

$table-hover-bg:         rgba($primary, .06);
$table-border-color:     $border-color;

$input-bg:               $white;
$input-border-color:     $border-color;
$input-focus-border-color: $primary;

// ---------- Reemissão como custom properties (OUTPUT, não fonte) ----------
:root {
  --color-bg:        #{$body-bg};
  --color-surface:   #{$body-secondary-bg};
  --color-text:      #{$body-color};
  --color-accent:    #{$primary};
  --color-accent-2:  #{$danger};
  --color-divider:   #{$border-color};
  --color-neutral-100: #{$neutral-100};
  --color-neutral-200: #{$neutral-200};
  --color-neutral-300: #{$neutral-300};
  --color-neutral-400: #{$neutral-400};
  --color-neutral-500: #{$neutral-500};
  --color-neutral-600: #{$neutral-600};
  --color-neutral-700: #{$neutral-700};
  --color-neutral-800: #{$neutral-800};
  --color-neutral-900: #{$neutral-900};
  --font-heading: #{$headings-font-family};
  --font-body:    #{$font-family-base};
  --radius-sm: 0px;
  --radius-md: 0px;
  --radius-lg: 0px;
}
```

> **`$enable-rounded: false` é o guardrail mais importante do arquivo.** Ele
> remove `border-radius` de *todo* componente do Bootstrap de uma vez, tornando
> impossível um canto arredondado escapar por um componente que ninguém revisou.

---

## 8. Preservação de `dusk=`

Regra absoluta durante qualquer migração de markup:

1. **Nunca** renomear, remover ou duplicar um `dusk="..."`.
2. O atributo acompanha o **elemento semanticamente equivalente**: o `dusk` de um
   `<button>` vai para o `<button>` novo; o de um container de lista vai para o
   novo container de lista — não para o card interno.
3. Quando o `dusk` estava num elemento que a estrutura Bootstrap elimina (ex.:
   o `div.dialog-backdrop` do modal antigo), mova-o para o elemento Bootstrap de
   papel equivalente (`div.modal`) e **registre isso no receipt** da migração.
4. Componentes propagam `dusk` automaticamente porque usam
   `$attributes->merge()`. Se um componente construir `class` sem `merge()`, o
   `dusk` some silenciosamente — este é o modo de falha nº 1 da migração.
5. Antes/depois obrigatório em cada arquivo migrado:
   ```bash
   git show HEAD:resources/views/x.blade.php | grep -o 'dusk="[^"]*"' | sort > /tmp/before.txt
   grep -o 'dusk="[^"]*"' resources/views/x.blade.php | sort > /tmp/after.txt
   diff /tmp/before.txt /tmp/after.txt   # deve ser vazio
   ```

---

## 9. Convenções de JavaScript

- `resources/js/app.js` importa o Bootstrap **primeiro** e o publica em `window`:
  ```js
  import * as bootstrap from 'bootstrap';
  window.bootstrap = bootstrap;
  ```
- Para obter uma instância, **sempre** `getOrCreateInstance` (nunca `new` direto —
  `new` num elemento já inicializado por `data-bs-toggle` cria duas instâncias e
  duplica backdrops):
  ```js
  const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('confirm-delete'));
  modal.show();
  ```
- Prefira **markup declarativo** (`data-bs-toggle`/`data-bs-target`) e só recorra
  à API imperativa quando o modal for aberto como resultado de uma resposta AJAX.
- Eventos do ciclo de vida: `shown.bs.modal`, `hidden.bs.modal`,
  `hidden.bs.toast`. Faça limpeza de estado em `hidden.bs.modal`, não em `click`.
- `window.NotificationService` mantém a fachada existente
  (`success(msg)`, `error(msg)`, `info(msg)`) reimplementada sobre
  `bootstrap.Toast`, porque 6 módulos a recebem por injeção. **Não mude a
  assinatura pública de nenhum módulo durante a migração.**
- Toasts vivem num único container fixo renderizado por `<x-layout.alerts>`:
  ```html
  <div class="toast-container position-fixed bottom-0 end-0 p-3" id="notification-container"></div>
  ```
  O id `#notification-container` é preservado porque `NotificationService` e
  testes Dusk dependem dele.
