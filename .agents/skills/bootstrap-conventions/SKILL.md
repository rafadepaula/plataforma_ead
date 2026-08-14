---
name: bootstrap-conventions
description: >
  Padrões e guardrails para escrever UI Bootstrap 5.3 na Plataforma EAD:
  wrapper de componente anônimo com `$attributes->merge()`, regra de nome
  `<x-ui.*>` vs `<x-layout.*>`, lista fechada de padrões proibidos (sem
  `style=` inline, sem JS artesanal de modal/toast/dropdown, sem classe
  Tailwind, sem classe CSS inventada onde existe utility), validação com
  `.is-invalid`/`.invalid-feedback`, preservação de `dusk=`, árvore de
  decisão utility-primeiro, bloco de override SCSS. Use ao escrever ou
  migrar view Blade, componente `<x-ui.*>`/`<x-layout.*>`, partial SCSS ou
  módulo JS que dirige widget Bootstrap.
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

Todo componente de UI é **componente anônimo** (só `.blade.php`, sem classe PHP) em `resources/views/components/`. Padrão canônico = 4 partes: `@props`, bloco `@php` que resolve classes, `$attributes->merge()`, slots.

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

Regras:

1. **`$attributes->merge(['class' => ...])` sempre**, nunca `class="{{ $classes }}"` solto. `merge()` deixa a tela somar utilities (`<x-ui.button class="mt-3 w-100">`) e faz o `dusk="..."` da tela chegar ao elemento renderizado.
2. **Nunca** `$attributes->merge(['style' => ...])`. `style` não entra em componente nenhum (ver §3).
3. `@props` documenta a API. Prop com default = opcional; prop sem default (`'title'`) = obrigatória, falha ruidosamente.
4. Variante mapeia para **classe real do Bootstrap** via `match()`. Sem equivalente Bootstrap, use classe da camada 3 — nunca `style=`.
5. Ordem das classes no `implode`: base, variante, tamanho, layout/utilities.
6. Componente que embrulha widget JS do Bootstrap emite os `data-bs-*` **ele mesmo**. Tela nunca escreve `data-bs-toggle` à mão.

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

Uso na tela — gatilho declarativo, zero JS:

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
| `<x-ui.*>` | `resources/views/components/ui/` | Widget reutilizável. Não conhece rota, role nem sessão. Recebe tudo por prop. | Renderiza em teste isolado só com props = `ui`. |
| `<x-layout.*>` | `resources/views/components/layout/` | Peça do chrome da aplicação, singular por página, ciente de `auth()`, `route()`, roles Spatie, `session('active_org_id')`. | Chama `auth()->user()`, `request()->routeIs()` ou `@role` = `layout`. |

- `ui`: `button`, `card`, `modal`, `badge`, `input`, `select`, `textarea`,
  `checkbox`, `table`, `stat-card`, `icon`, `alert`, `toast`, `pagination`,
  `empty-state`, `tabs`, `dropdown`, `progress`, `avatar`, `breadcrumb`.
- `layout`: `topbar`, `sidebar`, `footer`, `alerts` (container de flash),
  `page-header`.
- Componente de domínio que não é chrome nem widget genérico (`<x-help-button>`) fica na **raiz** de `components/`, como hoje.
- Arquivo em `kebab-case`. Sem subpasta dentro de `ui/`.

---

## 3. Padrões proibidos (lista fechada)

Cada item = motivo de **rejeição** em review. Não é questão de gosto.

1. **`style="..."` em view, componente ou layout.** Zero exceção em `resources/views/`, salvo `certificates/pdf.blade.php` (dompdf, ver `bootstrap-maintenance`) e valor genuinamente dinâmico em runtime (`style="width: {{ $pct }}%"`) — e mesmo esse prefere `.progress-bar` + `aria-valuenow`.
   Checagem: `grep -rn 'style="' resources/views --include='*.blade.php'`.
2. **JS artesanal de modal, toast ou dropdown.** Nada de `ModalManager`, `NotificationService` artesanal, `document.addEventListener('click', …)` para abrir menu. Use `bootstrap.Modal`, `bootstrap.Toast`, `bootstrap.Dropdown` ou os `data-bs-*`.
3. **Classe Tailwind.** `flex`, `grid`, `gap-4`, `text-sm`, `bg-white`, `rounded-lg`, `px-4`, `hidden`, `space-y-*`, `w-full`. Tailwind está morto no projeto e será removido. Cuidado com colisão: `gap-4` e `border` existem nos dois mundos com semântica diferente; `d-none` (Bootstrap) ≠ `hidden`; `w-100` ≠ `w-full`.
4. **Classe CSS inventada onde existe utility.** Nada de `.mt-large`, `.flex-center`, `.text-muted-custom`. Use `mt-4`, `d-flex align-items-center justify-content-center`, `text-body-secondary`.
5. **Classe fantasma do sistema antigo.** `.btn-ghost`, `.btn-block`, `.btn-icon`, `.dialog`, `.dialog-backdrop`, `.dialog-title`, `.tag-accent`, `.tag-outline`, `.tag-neutral`, `.tag-accent-2`, `.field`, `.input`, `.elev-sm|md|lg`. Nenhuma existe em CSS algum — resíduo. Mapeamento em §4.
6. **`var(--color-*)` em código novo.** Custom properties viraram output de `_variables.scss`. CSS novo usa `$variáveis` SCSS ou `--bs-*`.
7. **Hex hardcoded** em view ou SCSS de componente. Só `_variables.scss` tem literal de cor.
8. **`border-radius` diferente de zero**, `rounded`, `rounded-*`, `rounded-pill`, `rounded-circle`. Mandato Modernist = canto reto sistêmico. Exceção única: `.rounded-circle` em avatar, **só** com aprovação explícita. Padrão: avatar quadrado.
9. **Bootstrap Icons / CDN externo.** Ícone continua Lucide inline via `<x-ui.icon name="..."/>`.
10. **`!important`** fora de `.org-logo` (exceção histórica documentada).
11. **`<table>` sem `.table-responsive`** no wrapper.
12. **Markup Bootstrap cru em tela** quando existe (ou deveria existir) um `<x-ui.*>` — ver mandato de componentização em `bootstrap-architecture`.

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

Escala de espaçamento (com `$spacer: 1rem`): `1`=4px, `2`=8px, `3`=12px, `4`=16px, `5`=24px. Configurável em `_variables.scss` para casar com `--space-*` do Modernist (4/8/12/16/24/32). Ver §7.

---

## 5. Árvore de decisão: utility primeiro, classe de componente depois

Precisa de estilo? Percorra nesta ordem, **pare no primeiro que resolve**:

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

Regra prática: **mais de 5 utilities no mesmo elemento, repetidas em mais de uma tela** = componente faltando. **Menos de 3 utilities** = nunca vale classe nova.

---

## 6. Validação Laravel: `.is-invalid` + `.invalid-feedback`

Padrão único para todo campo do sistema. Componente resolve o erro sozinho a partir de `$errors`; tela só passa `name`.

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

- `.invalid-feedback` só aparece quando o input irmão imediatamente anterior tem `.is-invalid` — regra CSS do Bootstrap (`.is-invalid ~ .invalid-feedback`). **Não** ponha `<div>` extra entre input e feedback.
- `dusk="error-{campo}"` = contrato de teste para asserção de erro.
- `<select>`: mesma lógica com `.form-select is-invalid`.
- checkbox/radio: `.form-check-input is-invalid` + `.invalid-feedback` dentro do `.form-check`.
- Erro vindo de JSON (AJAX: `CsvImporter`, `SmartInvitationForm`) aplica `.is-invalid` via JS no campo e escreve o texto no `.invalid-feedback` existente. Nunca cria markup de erro novo.
- Nunca `<x-ui.alert>` para erro de campo. Alert é para erro de formulário inteiro ou flash de sessão.

---

## 7. O bloco exato de override SCSS

> **ATENÇÃO — bloco abaixo é o ESBOÇO original, não o código em produção.**
> Implementação real vive em **`resources/scss/app.scss`** (`_variables.scss` nunca foi criado) e diverge em três pontos que mudam o que você escreve na Blade:
>
> 1. **`$spacers` é o mapa PADRÃO do Bootstrap** (`0..5`, `$spacer: 1rem`) mesclado com as chaves `1x 2x 3x 4x 6x 8x` (4/8/12/16/24/32px). Escala numérica: `1`=4px, `2`=8px, `3`=**16px**, `4`=**24px**, `5`=48px. **`p-6`/`g-6` não existem.** Valor exato do Modernist usa chave `x`: 12px = `gap-3x`, 16px = `mb-4x`, 24px = `mt-6x`, 32px = `p-8x`. `mb-4` vale 24px, não 16px.
> 2. **Superfície é `--bs-tertiary-bg`**: o projeto define `$body-tertiary-bg: $modernist-surface`. Para `--color-surface` use **`bg-body-tertiary`**, não `bg-body-secondary`.
> 3. **`$success: $modernist-accent`** (vermelho) por mandato Modernist, e `$danger: $modernist-accent-2`. `alert-success` e `alert-danger` são dois vermelhos próximos — não use verde/vermelho para diferenciar estado.
>
> Confira `resources/scss/app.scss` antes de usar degrau de espaçamento ou token de cor. Bloco abaixo fica como registro da intenção de design:

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

> **`$enable-rounded: false` é o guardrail mais importante do arquivo.** Remove `border-radius` de *todo* componente do Bootstrap de uma vez. Nenhum canto arredondado escapa por componente não revisado.

---

## 8. Preservação de `dusk=`

Regra absoluta em qualquer migração de markup:

1. **Nunca** renomear, remover ou duplicar um `dusk="..."`.
2. Atributo acompanha o **elemento semanticamente equivalente**: `dusk` de `<button>` vai para o `<button>` novo; de container de lista vai para o novo container, não para o card interno.
3. `dusk` em elemento que a estrutura Bootstrap elimina (ex.: `div.dialog-backdrop` do modal antigo) migra para o elemento de papel equivalente (`div.modal`) e **entra no receipt** da migração.
4. Componente propaga `dusk` sozinho porque usa `$attributes->merge()`. Componente que monta `class` sem `merge()` perde o `dusk` em silêncio — modo de falha nº 1 da migração.
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
- Instância: **sempre** `getOrCreateInstance`, nunca `new` direto. `new` em elemento já inicializado por `data-bs-toggle` cria duas instâncias e duplica backdrop:
  ```js
  const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('confirm-delete'));
  modal.show();
  ```
- Prefira **markup declarativo** (`data-bs-toggle`/`data-bs-target`). API imperativa só quando o modal abre por resposta AJAX.
- Eventos de ciclo de vida: `shown.bs.modal`, `hidden.bs.modal`, `hidden.bs.toast`. Limpeza de estado em `hidden.bs.modal`, não em `click`.
- `window.NotificationService` mantém a fachada (`success(msg)`, `error(msg)`, `info(msg)`) reimplementada sobre `bootstrap.Toast` — 6 módulos a recebem por injeção. **Não mude assinatura pública de módulo durante a migração.**
- Toast vive em um único container fixo renderizado por `<x-layout.alerts>`:
  ```html
  <div class="toast-container position-fixed bottom-0 end-0 p-3" id="notification-container"></div>
  ```
  Id `#notification-container` preservado — `NotificationService` e testes Dusk dependem dele.
