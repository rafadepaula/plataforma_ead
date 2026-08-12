# 06 — Skills e Agentes Especializados Bootstrap

> Documento de design da **harness agêntica** da migração para Bootstrap 5.3.
> Especifica, com conteúdo de arquivo completo e pronto para cópia, as 3 skills da
> tríade `bootstrap-*` e os 5 subagentes especializados que executarão a migração
> das 78 views Blade. Escrito para que outro agente possa criar cada arquivo
> **verbatim**, sem re-derivar decisões.

---

## 0. Contexto consolidado (não re-derivar)

Estado atual verificado no repositório (`main`, 2026-08-12):

| Fato | Evidência |
| :--- | :--- |
| `resources/css/app.css` importa **apenas** `bootstrap-grid.min.css` e `bootstrap-utilities.min.css` (CSS compilado do `dist/`, **não** SCSS) | `resources/css/app.css` linhas 1–2 |
| **Zero** JavaScript do Bootstrap carregado | `resources/js/app.js` não importa `bootstrap` |
| 78 views Blade; 634 atributos `style="..."` inline apontando para `var(--color-*)` | levantamento anterior (docs 01–05) |
| 316 ocorrências de `dusk="` em `resources/views/` | `grep -ro 'dusk="' resources/views \| wc -l` |
| Tailwind 4 instalado (`@tailwindcss/vite` + plugin em `vite.config.js`) mas **morto** — `app.css` não tem `@import "tailwindcss"` | `package.json`, `vite.config.js` |
| 12 módulos JS SOLID em `resources/js/modules/` + `quiz-builder.js` e `quiz-timer.js` | `resources/js/app.js` |
| `<x-ui.modal>` usa diretivas Alpine (`x-data`, `x-show`, `@click.outside`) **sem Alpine instalado** → modal inerte | `resources/views/components/ui/modal.blade.php` |
| As classes `.btn`, `.btn-primary`, `.btn-ghost`, `.card`, `.dialog`, `.tag-accent` **não existem em nenhum CSS** — são "classes fantasma"; todo o visual vem do `style=` inline | `resources/css/app.css` (130 linhas, só tokens `:root`, `:focus-visible`, `.grayscale`, `.org-logo`) |
| `sass` **não** está em `devDependencies` | `package.json` |

**Mandato de design que precisa sobreviver:** `border-radius: 0` sistêmico, fonte
Archivo, accent `#ec3013`, background `#f3f2f2`, sidebar escura (`--color-neutral-900`
= `#2d2b2b`), imagens em grayscale, labels de botão flush-left.

**Restrição dura:** os 316 seletores `dusk="..."` são contrato da suíte Dusk e
devem sobreviver **verbatim** (mesmo nome, mesmo elemento semântico).

### 0.1 Onde os arquivos vivem (convenção do repositório)

O repositório mantém **dois diretórios espelhados** para skills e para agentes:

```
.agents/skills/{nome}/SKILL.md     <-- fonte canônica citada pela SPEC-03 / skill-autoupdate
.claude/skills/{nome}/SKILL.md     <-- espelho consumido pelo Claude Code
.agents/agents/{nome}.md           <-- fonte canônica dos subagentes
.claude/agents/{nome}.md           <-- espelho consumido pelo Claude Code
```

Os pares são **cópias byte-a-byte** (verificado: `diff -q` entre
`.claude/skills/forum-conventions/SKILL.md` e `.agents/skills/forum-conventions/SKILL.md`
não reporta diferença). Portanto **todo arquivo desenhado aqui deve ser criado nos
dois caminhos**, com conteúdo idêntico. A `skill-autoupdate` cita `.agents/skills/`
como o diretório auditado por `scripts/check-skills.php`; o espelho `.claude/`
existe para carregamento automático pela CLI.

### 0.2 Dependência que exige aprovação explícita

Migrar de `bootstrap/dist/css/*.min.css` para `bootstrap/scss/*` **exige o pacote
`sass`** em `devDependencies` (Vite 8 não compila `.scss` sem ele). O `CLAUDE.md`
do projeto proíbe alterar dependências sem aprovação. Isto é um **bloqueio de
Fase 0** da migração e está registrado como pré-condição na Parte C, Tarefa T0.
Enquanto não houver aprovação, o layer de tokens SCSS descrito em
`bootstrap-architecture` não pode ser implementado — e as skills devem ser criadas
já descrevendo o estado-alvo (elas são o contrato que a migração persegue).

---

# PARTE A — A tríade de skills `bootstrap-*`

Três skills, seguindo exatamente a tríade obrigatória da `skill-autoupdate`
(§1: `[feature]-architecture` / `[feature]-conventions` / `[feature]-maintenance`).

Formato de frontmatter adotado: o **formato "longo"** usado pelas skills mais
recentes e mais maduras do repo (`certificates-maintenance`, `forum-conventions`,
`profile-conventions`) — `name`, `description` em bloco `>` com estilo
*trigger-oriented* ("... Use whenever / Use when ..."), `license: MIT` e um bloco
`metadata` com `feature`, `role` e `specs`. As skills antigas (`frontend-*`)
usam o formato curto de uma linha em pt-BR; **não** replicar o formato antigo —
a Parte D trata da atualização delas.

| Skill | Caminhos de criação |
| :--- | :--- |
| `bootstrap-architecture` | `.agents/skills/bootstrap-architecture/SKILL.md` + `.claude/skills/bootstrap-architecture/SKILL.md` |
| `bootstrap-conventions` | `.agents/skills/bootstrap-conventions/SKILL.md` + `.claude/skills/bootstrap-conventions/SKILL.md` |
| `bootstrap-maintenance` | `.agents/skills/bootstrap-maintenance/SKILL.md` + `.claude/skills/bootstrap-maintenance/SKILL.md` |

---

## A.1 `bootstrap-architecture`

**Caminho:** `.agents/skills/bootstrap-architecture/SKILL.md`
(+ espelho em `.claude/skills/bootstrap-architecture/SKILL.md`)

````markdown
---
name: bootstrap-architecture
description: >
  Explains the Bootstrap 5.3 frontend architecture of the Plataforma EAD
  after the inline-style migration: the 5-layer model (SCSS token layer →
  Bootstrap core → project component layer → Blade wrapper components →
  screens), why Bootstrap SCSS variables (`$primary`, `$border-radius`,
  `$font-family-base`) replace the parallel `--color-*` custom-property set
  as the single source of truth, the Bootstrap-JS-over-hand-rolled-JS
  principle that retires ModalManager/NotificationService/dropdown code,
  the componentization mandate (no raw Bootstrap markup in a screen), and
  the decision record of what was replaced by what. Use whenever designing
  or reviewing a screen, layout, Blade component or JS module that renders
  UI, before introducing a new CSS class or a new `<x-ui.*>` component, or
  when deciding where a given style rule belongs in the layer stack.
license: MIT
metadata:
  feature: bootstrap
  role: architecture
  specs:
    - spec/front_migration/01-current-state.md
    - spec/front_migration/06-skills-and-agents.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Bootstrap Architecture

## Overview

O frontend da Plataforma EAD é **Bootstrap 5.3 completo** (SCSS + JS), com o
**Modernist Design System** expresso como uma **customização do Bootstrap**, e não
como um sistema paralelo. Não existe mais "CSS do projeto que imita o Bootstrap":
existe Bootstrap configurado com os tokens da marca.

O princípio-mestre é: **uma decisão visual tem exatamente um lugar onde mora.**
Se um botão tem canto reto, isso está em `$border-radius: 0` — não em 634
atributos `style="border-radius: 0px"` espalhados por views.

---

## As 5 camadas

```
┌─ 5. Telas (resources/views/**/*.blade.php)
│     Só composição. Zero style=. Zero CSS novo. Só <x-ui.*>, <x-layout.*> e utilities.
├─ 4. Componentes Blade wrapper (resources/views/components/{ui,layout}/)
│     Encapsulam markup Bootstrap + ARIA + data-bs-*. Única camada que conhece
│     a estrutura interna do Bootstrap (.modal-dialog, .card-body, .form-control).
├─ 3. Camada de componentes do projeto (resources/scss/components/_*.scss)
│     Só o que o Bootstrap NÃO resolve: .sidebar, .grayscale, .stat-card,
│     .lesson-player, .topbar. Nunca reimplementa .btn/.card/.modal.
├─ 2. Bootstrap core (node_modules/bootstrap/scss/bootstrap.scss)
│     Importado por inteiro, já compilado com as variáveis da camada 1.
└─ 1. Camada de tokens SCSS (resources/scss/_variables.scss)
      A ÚNICA fonte de verdade do design. Sobrescreve as variáveis !default
      do Bootstrap ANTES do import do core.
```

Arquivo de entrada (`resources/scss/app.scss`), nesta ordem obrigatória:

```scss
// 1) Funções do Bootstrap (necessárias para tint/shade nas nossas variáveis)
@import "bootstrap/scss/functions";

// 2) Nossos tokens — sobrescrevem os !default do Bootstrap
@import "variables";

// 3) Bootstrap completo (variables, mixins, reboot, grid, componentes, utilities, helpers)
@import "bootstrap/scss/bootstrap";

// 4) Utilities extras derivadas dos nossos tokens (API de utilities do Bootstrap)
@import "utilities";

// 5) Camada de componentes do projeto — só o que o Bootstrap não cobre
@import "components/sidebar";
@import "components/topbar";
@import "components/stat-card";
@import "components/lesson-player";
@import "components/grayscale";

// 6) Fontes self-hosted (Archivo)
@import "fonts";
```

`vite.config.js` passa a apontar `resources/scss/app.scss` no lugar de
`resources/css/app.css`. `resources/css/app.css` é **removido** ao final da
migração (não fica como fallback: dois arquivos de estilo é exatamente o problema
que a migração elimina).

---

## Por que as variáveis SCSS do Bootstrap são a fonte de verdade

O estado pré-migração tinha **dois** sistemas de tokens vivendo lado a lado:

- `:root { --color-accent: #ec3013; --radius-sm: 0px; ... }` — consumido por 634
  atributos `style=` inline;
- as variáveis internas do Bootstrap (`$primary`, `$border-radius`,
  `$font-family-base`), que ninguém tinha configurado, porque só se importava o
  CSS já compilado de `bootstrap/dist/`.

Consequência: nenhum utility ou componente do Bootstrap "sabia" da marca. Para
ter um botão vermelho era preciso escrever o vermelho à mão, em cada botão.

Depois da migração, o fluxo é **um só**:

```scss
// resources/scss/_variables.scss  (camada 1)
$primary:   #ec3013;   // accent Modernist
$body-bg:   #f3f2f2;
$body-color:#201e1d;
$border-radius: 0;
$font-family-base: "Archivo", system-ui, sans-serif;
```

…e a partir daí `.btn-primary`, `.bg-primary`, `.text-primary`, `.border-primary`,
`.badge.text-bg-primary`, o foco `:focus-visible`, o `.form-control:focus` e o CSS
var `--bs-primary` — **todos** — já saem na cor certa, de graça, sem uma linha de
CSS do projeto.

As custom properties `--color-*` **não desaparecem**: elas passam a ser *derivadas*
(a camada 1 as reemite a partir das variáveis SCSS, para código legado e para o JS
que porventura leia `getComputedStyle`). Mas **elas deixam de ser a fonte**: quem
edita a marca edita `_variables.scss`, nunca o bloco `:root`.

Regra: **`--color-*` é output, `$variável` é input.** Uma PR que muda cor mexendo
em `:root` está errada por construção.

---

## Princípio: JS do Bootstrap acima de JS artesanal

Pré-migração havia 12 módulos JS em `resources/js/modules/`, três dos quais
reimplementavam à mão comportamentos que o Bootstrap já entrega:

| Módulo artesanal | Comportamento | Substituto Bootstrap |
| :--- | :--- | :--- |
| `ModalManager.js` | abrir/fechar, backdrop, `Escape`, foco, `data-modal-target` | `bootstrap.Modal` + `data-bs-toggle="modal"` / `data-bs-dismiss="modal"` |
| `NotificationService.js` | toasts, container dinâmico, auto-dismiss | `bootstrap.Toast` + `.toast-container` |
| dropdown do `<x-layout.topbar>` (inline) | abrir/fechar menu de perfil e de notificações | `bootstrap.Dropdown` (+ Popper, que vem no bundle) |
| diretivas Alpine órfãs em `<x-ui.modal>` | `x-data`/`x-show`/`@click.outside` **sem Alpine instalado** — inertes | `bootstrap.Modal` |

O princípio: **se o Bootstrap tem a API, o projeto não escreve a sua.** Motivos
concretos, não estéticos:

1. **Acessibilidade grátis** — focus trap, `aria-modal`, `aria-expanded`,
   restauração de foco e inertização do fundo já são corretos e testados no
   Bootstrap; a implementação artesanal cobria só parte disso.
2. **Empilhamento e z-index corretos** — backdrops múltiplos, `.modal-open` no
   `<body>`, compensação de scrollbar. `ModalManager` não tratava empilhamento.
3. **Superfície de manutenção** — ~10 KB de JS do projeto deixam de existir.
4. **Contrato estável para Dusk** — `data-bs-toggle` + classe `.show` são um
   contrato público e documentado, melhor alvo de `waitFor` do que estado interno
   de um objeto do projeto.

Os módulos que **permanecem** são os que carregam lógica de domínio, não de
widget: `HttpClient`, `CsvImporter`, `LessonPlayer`, `ModuleReorder`,
`SmartInvitationForm`, `QuizBuilder`, `QuizTimer`, `ForumPolling`,
`NotificationBell`, `ForumEditHistory`, `ForumReportModal`, `AuditLogDiffModal`.
Destes, os quatro últimos **deixam de instanciar `ModalManager`** e passam a usar
`bootstrap.Modal.getOrCreateInstance(el)` — a API pública de cada módulo
(`init()`, construtor com dependências injetadas, registro em `window.*`) é
preservada intacta, porque é contrato de `app.js` e das views.

Bundle: `resources/js/app.js` passa a começar com

```js
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;
```

Isto traz Popper embutido (o build `bootstrap.bundle` do npm resolve o Popper via
dependência), habilitando dropdowns, tooltips e popovers.

---

## Mandato de componentização

**Nenhuma tela escreve markup cru do Bootstrap.** Uma tela nunca contém
`<div class="modal fade" tabindex="-1">…</div>` nem `<nav class="navbar">…`.
Ela contém `<x-ui.modal>` e `<x-layout.topbar>`.

Por quê:

- **Um lugar para corrigir.** Quando o Bootstrap 5.4 mudar a estrutura interna do
  modal, muda-se `components/ui/modal.blade.php`, não 40 telas.
- **ARIA por construção.** Os atributos de acessibilidade vivem no wrapper; a tela
  não pode esquecê-los.
- **Contrato de teste.** O wrapper garante que todo modal tem
  `dusk="modal-{id}"` e todo botão de submit tem o `dusk` que a tela passou.
- **Detecção de deriva.** Se uma tela precisa de markup que nenhum componente
  expõe, isso é sinal de componente faltando — e a resposta certa é criar/estender
  o componente (via `bootstrap-component-author`), nunca inlinear o markup.

Exceção única e explícita: **utilities de layout** (`row`, `col-*`, `d-flex`,
`gap-*`, `mb-*`, `text-*`) são escritas diretamente na tela. Utility é vocabulário,
não estrutura.

---

## Decision record — o que substituiu o quê

| Antes (pré-migração) | Depois | Nota |
| :--- | :--- | :--- |
| `@import "bootstrap/dist/css/bootstrap-grid.min.css"` + `bootstrap-utilities.min.css` | `@import "bootstrap/scss/bootstrap"` compilado com nossos tokens | grid e utilities continuam existindo; ganha-se reboot, componentes e a Utility API |
| `:root { --color-*; --radius-*; --shadow-* }` como fonte | `resources/scss/_variables.scss` como fonte; `:root` reemitido como *output* | ver "fonte de verdade" |
| 634 atributos `style="..."` | classes utilitárias + classes de componente | zero `style=` é regra de lint (ver `bootstrap-conventions`) |
| Classes fantasma `.btn`, `.btn-primary`, `.btn-ghost`, `.card`, `.dialog`, `.tag-accent`, `.tag-outline`, `.input`, `.field`, `.elev-sm/md/lg` — **nunca existiram em CSS algum** | `.btn .btn-primary`, `.btn .btn-outline-secondary`, `.btn .btn-link`, `.card`, `.modal`, `.badge text-bg-*`, `.form-control`, `.shadow-sm/.shadow/.shadow-lg` do Bootstrap | as classes antigas eram decorativas; o visual real vinha do inline style |
| `.dialog` / `.dialog-backdrop` + diretivas Alpine órfãs | `.modal` / `.modal-backdrop` do Bootstrap | Alpine nunca esteve instalado; o modal era inerte |
| `ModalManager.js` | `bootstrap.Modal` | módulo removido |
| `NotificationService.js` (toast artesanal) | `bootstrap.Toast` + `<x-ui.toast>` | a *fachada* `window.NotificationService.success()/error()` é mantida, reimplementada sobre `bootstrap.Toast`, para não quebrar os 6 módulos que a injetam |
| Tailwind 4 (`@tailwindcss/vite`, `tailwindcss`) instalado e não usado | removido do `package.json` e do `vite.config.js` | dependência morta; remoção exige aprovação (ver Parte C, T0) |
| `bunny('Instrument Sans')` no `vite.config.js` | removido — a fonte real é Archivo self-hosted | fonte baixada e nunca aplicada |
| `<x-ui.select>` com chevron SVG absoluto + `appearance:none` | `.form-select` do Bootstrap | |
| `<x-ui.table>` com styles inline | `.table` + wrapper `.table-responsive` | |
| erros de validação renderizados à mão | `.is-invalid` + `.invalid-feedback` | ver `bootstrap-conventions` |

---

## Contrato com o resto do sistema

- **Dusk é intocável.** Os 316 atributos `dusk="..."` em `resources/views/` são
  contrato de teste. Migrar markup **nunca** renomeia, move para outro elemento
  semântico, nem remove um `dusk=`. Se o elemento que carregava o `dusk` deixa de
  existir, o atributo migra para o elemento equivalente mais próximo — e isso é
  uma mudança que precisa ser justificada no receipt da migração.
- **PDF é território separado.** `resources/views/certificates/pdf.blade.php` é
  renderizado por `barryvdh/laravel-dompdf`, que **não** entende o CSS do
  Bootstrap 5 (custom properties, `color-mix()`, flexbox moderno, grid). A view de
  PDF mantém CSS próprio embutido em `<style>` e é a **única** exceção permitida à
  regra de zero CSS ad-hoc. Ver `bootstrap-maintenance`.
- **Multi-tenant/roles não mudam.** A sidebar continua montando itens por
  `role:admin|gestor|aluno` (Spatie); a migração é puramente de camada de
  apresentação.
- **`<x-help-button>`** (SPEC-11) continua montado em 100% das telas; ele passa a
  abrir um `.modal` do Bootstrap, mas mantém `dusk="help-button-{key}"` e
  `dusk="help-article-content-{key}"`.
````

---

## A.2 `bootstrap-conventions`

**Caminho:** `.agents/skills/bootstrap-conventions/SKILL.md`
(+ espelho em `.claude/skills/bootstrap-conventions/SKILL.md`)

````markdown
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
4. Variantes mapeiam para **classes reais do Bootstrap** via `match()`. Se a
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
`3`=16px, `4`=24px, `5`=48px — configurável em `_variables.scss` para casar com
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

`resources/scss/_variables.scss` — **este bloco é a fonte de verdade do design**:

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
````

---

## A.3 `bootstrap-maintenance`

**Caminho:** `.agents/skills/bootstrap-maintenance/SKILL.md`
(+ espelho em `.claude/skills/bootstrap-maintenance/SKILL.md`)

````markdown
---
name: bootstrap-maintenance
description: >
  Debugging, testing, and edge-case guide for the Bootstrap 5.3 frontend of
  the Plataforma EAD: the stale `public/build/` gotcha that silently breaks
  Dusk, the per-screen verification loop (`vendor/bin/sail npm run build`
  then the screen's Dusk filter), and the recurring failure modes —
  `data-bs-toggle` inert because the JS bundle was never imported, stacked
  modal backdrops, dropdowns dead without Popper, a utility class losing to
  a leftover inline `style=`, missing `.table-responsive` causing horizontal
  overflow, and dompdf choking on Bootstrap CSS in
  `certificates/pdf.blade.php`. Use when a Dusk test fails after a UI
  change, a modal/toast/dropdown does nothing in the browser, a migrated
  screen looks unstyled or overflows, or the certificate PDF renders blank.
license: MIT
metadata:
  feature: bootstrap
  role: maintenance
  specs:
    - spec/front_migration/06-skills-and-agents.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Bootstrap Maintenance

## 1. A pegadinha do `public/build/` obsoleto

**Este é o modo de falha mais caro da migração inteira** e o primeiro a
verificar em qualquer teste Dusk vermelho.

O Dusk faz requisições HTTP reais ao app, que serve os assets pelo manifest em
`public/build/manifest.json`. O Dusk **não** compila nada. Portanto:

> Editar `.blade.php`, `.scss` ou `.js` e rodar `dusk` **sem** rodar
> `npm run build` faz o navegador carregar o CSS/JS **anterior**. O Blade novo
> chega, o CSS/JS velho chega junto.

Sintomas típicos, todos com a mesma causa:

- O teste falha em `waitFor('.modal.show')` — o `data-bs-toggle` está no HTML,
  mas o bundle carregado é o antigo, sem o Bootstrap JS.
- A tela aparece "sem estilo" no screenshot de falha
  (`tests/Browser/screenshots/`): o HTML já usa `.card`/`.btn`, o CSS ainda é o
  `app.css` antigo, que não tem essas classes.
- Um `assertVisible` passa localmente e falha no CI (ou o inverso), dependendo de
  quem rodou o build por último.
- O `.scss` foi editado mas a cor não muda — e "não muda" mesmo depois de
  recarregar, porque o `manifest.json` ainda aponta o hash antigo.

**Regra operacional, sem exceção:**

```bash
vendor/bin/sail npm run build && vendor/bin/sail artisan dusk --filter=NomeDoTeste
```

Sempre encadeado com `&&`, sempre nessa ordem, em **todo** ciclo de verificação.
Se estiver com `npm run dev` (HMR) rodando em outro terminal, ele **não** substitui
o build: o Dusk não usa o dev server a menos que `APP_URL` aponte para ele e o
hot file exista. Na dúvida, rode o build.

Diagnóstico rápido de manifest podre:

```bash
vendor/bin/sail php -r 'echo file_get_contents("public/build/manifest.json");' | head -20
ls -la public/build/assets/ | head
```

Se o timestamp dos assets for anterior à sua última edição de `.scss`/`.js`,
o build não rodou. Em último caso: `rm -rf public/build && vendor/bin/sail npm run build`.

---

## 2. Como verificar uma tela migrada

Loop obrigatório por tela, na ordem:

```bash
# 1. Zero style= inline sobrou no arquivo migrado
grep -n 'style="' resources/views/<caminho>/<tela>.blade.php

# 2. Zero classe fantasma / Tailwind sobrou
grep -nE 'btn-ghost|btn-block|btn-icon|\bdialog\b|tag-(accent|outline|neutral)|elev-(sm|md|lg)|\bfield\b|rounded|flex |grid |text-sm|bg-white|px-[0-9]|space-y-' resources/views/<caminho>/<tela>.blade.php

# 3. Seletores dusk preservados byte-a-byte
git show HEAD:resources/views/<caminho>/<tela>.blade.php | grep -o 'dusk="[^"]*"' | sort > /tmp/dusk-before.txt
grep -o 'dusk="[^"]*"' resources/views/<caminho>/<tela>.blade.php | sort > /tmp/dusk-after.txt
diff /tmp/dusk-before.txt /tmp/dusk-after.txt   # tem que sair vazio

# 4. Build + o Dusk específico da tela
vendor/bin/sail npm run build && vendor/bin/sail artisan dusk --filter=<TesteDaTela>

# 5. Pint, se algum PHP foi tocado
vendor/bin/sail bin pint --dirty --format agent
```

Mapa tela → teste Dusk (usar sempre o filtro mais estreito):

| Área | Filtro Dusk |
| :--- | :--- |
| Layout, sidebar, topbar, radius | `LayoutRenderingTest` |
| Componentes `<x-ui.*>` | `BladeComponentsTest` |
| Navegação / menus por role | `NavigationMenuDuskTest` |
| Login, convite, reset | `Auth\...` (ver `tests/Browser/Auth/`) |
| Perfil | `ProfileTest` |
| CRUD de usuários / alunos | `UserManagementTest` |
| CRUD de organizações | `OrganizationCrudTest` |
| Import CSV | `MultiTenantStudentImportTest` |
| Cursos (gestão) | `CourseManagementTest` |
| Reorder de módulos | `ModuleReorderTest` |
| Player de aula / vídeo | `LessonMultimediaTest`, `VideoThresholdCompletionTest` |
| Quiz (aluno) | `StudentQuizAttemptTest` |
| Correção discursiva | `EssayGradingScreenTest` |
| Fórum | `ForumDuskTest` |
| Certificados | `CertificateVerificationTest`, `CertificateRevocationTest`, `CourseCompletionRuleTest` |
| Dashboard | `DashboardDuskTest` |
| Notificações (sino) | `NotificationBellTest` |
| Audit logs (modal de diff) | `AuditLogUiTest` |
| Help center (modal) | `HelpCenterDuskTest` |
| Impersonate org | `ImpersonateOrgTest` |
| Matrícula multi-org | `MultiOrgEnrollmentTest`, `MultiOrgStudentClassroomTest` |

Ao final de uma fase (não a cada tela): `vendor/bin/sail artisan dusk` completo.

---

## 3. Modos de falha recorrentes

### 3.1 `data-bs-toggle` inerte (nada acontece ao clicar)

- **Sintoma:** botão com `data-bs-toggle="modal"` / `="dropdown"` /
  `="collapse"` não faz nada; console sem erro; Dusk estoura em
  `waitFor('.modal.show')`.
- **Causas, em ordem de frequência:**
  1. `resources/js/app.js` **não** importa o Bootstrap. Confirme que a primeira
     linha é `import * as bootstrap from 'bootstrap';`.
  2. O build está velho (§1).
  3. O `data-bs-target` aponta para um id inexistente ou duplicado. Ids duplicados
     são comuns em listas — inclua a chave: `id="confirm-delete-{{ $user->id }}"`.
  4. O elemento foi injetado no DOM **depois** do load por AJAX: os atributos
     `data-bs-*` funcionam por delegação para modal/dropdown/collapse, mas para
     elementos recriados dentro de um container substituído, instancie
     explicitamente com `bootstrap.Modal.getOrCreateInstance(el)`.
  5. O layout usado pela tela é `guest.blade.php` e ele **não** tem
     `@vite(['resources/scss/app.scss','resources/js/app.js'])`. Verifique os dois
     layouts — este erro atinge só as telas públicas.
- **Verificação rápida no browser:** `typeof window.bootstrap` deve retornar
  `"object"`. Em Dusk: `$browser->script('return typeof window.bootstrap')[0]`.

### 3.2 Backdrops de modal empilhados / página trava com scroll bloqueado

- **Sintoma:** ao fechar o modal a tela continua escurecida e sem scroll; sobram
  `<div class="modal-backdrop">` no DOM.
- **Causas:**
  1. Dupla instanciação: markup com `data-bs-toggle` **e** JS chamando `new
     bootstrap.Modal(el)`. Use `getOrCreateInstance` e escolha **um** dos dois
     caminhos por modal.
  2. Resíduo do `ModalManager` antigo ainda registrado em `app.js` adicionando o
     seu próprio backdrop. Remova o módulo, não o "desligue".
  3. Modal renderizado **dentro** de um container com `transform`, `filter` ou
     `overflow: hidden` (o `.grayscale` faz `filter`!) — isso cria um containing
     block e quebra o `position: fixed`. **Modais devem ser irmãos do conteúdo,
     idealmente no fim do `<body>` do layout**, nunca dentro de um card com filtro.
  4. Modal aberto de dentro de outro modal sem fechar o primeiro. O Bootstrap
     suporta empilhar, mas o Dusk vai acertar o backdrop errado: prefira fechar o
     primeiro em `hidden.bs.modal` e abrir o segundo.
- **Limpeza de emergência (só para depurar, nunca em produção):**
  `document.querySelectorAll('.modal-backdrop').forEach(e => e.remove())`.

### 3.3 Dropdown não abre (Popper ausente)

- **Sintoma:** menu de perfil/notificações da topbar não abre; console mostra
  `Bootstrap doesn't allow more than one instance per element` ou
  `Popper__namespace is not defined`.
- **Causa:** import parcial do Bootstrap (`import Modal from 'bootstrap/js/dist/modal'`)
  sem Popper, ou uso do CSS do bundle com JS sem bundle.
- **Solução:** importe o pacote inteiro (`import * as bootstrap from 'bootstrap'`),
  que resolve `@popperjs/core` via dependência do npm. Se for imprescindível
  importar módulos avulsos, `bootstrap/js/dist/dropdown` **exige**
  `@popperjs/core` como dependência explícita — e adicionar dependência precisa de
  aprovação.
- Dropdown também morre se o `.dropdown-menu` não for irmão imediato do gatilho
  dentro de um `.dropdown`/`.btn-group`.

### 3.4 Utility perde para um `style=` remanescente

- **Sintoma:** o elemento tem `class="text-primary mb-3"` e continua preto e
  colado; o DevTools mostra a regra riscada.
- **Causa:** um `style="color: var(--color-text); margin-bottom: 0"` sobrou no
  mesmo elemento (ou num componente pai que ainda faz
  `$attributes->merge(['style' => ...])`). Estilo inline vence qualquer classe,
  `!important` à parte.
- **Solução:** remover o inline — **nunca** vencer com `!important`. Se o inline
  vem do componente, o componente ainda não foi migrado: migre-o antes da tela.
- **Varredura global:**
  ```bash
  grep -rn 'style="' resources/views --include='*.blade.php' | grep -v 'certificates/pdf.blade.php'
  ```
  O alvo ao fim da migração é **zero linha** nessa saída.

### 3.5 Tabela estourando na horizontal (falta `.table-responsive`)

- **Sintoma:** scroll horizontal na página inteira no mobile; Dusk com viewport
  estreito falha ao clicar num botão da última coluna ("element not
  interactable"/"outside of viewport").
- **Causa:** `<table class="table">` sem wrapper.
- **Solução:** sempre
  ```blade
  <div class="table-responsive">
      <table class="table align-middle">…</table>
  </div>
  ```
  O `<x-ui.table>` já emite o wrapper — o erro só aparece quando alguém escreve
  `<table>` cru numa tela (proibido, ver `bootstrap-conventions` §3.12).
- Sintoma correlato: dropdown de ações da última coluna cortado pelo
  `overflow: auto` do `.table-responsive`. Solução: `data-bs-strategy="fixed"` no
  gatilho, ou mover a ação para um modal.

### 3.6 dompdf engasga com o CSS do Bootstrap (certificado em branco/quebrado)

- **Sintoma:** `certificates/pdf.blade.php` gera PDF em branco, sem layout, ou
  `barryvdh/laravel-dompdf` lança erro de parsing de CSS.
- **Causa:** dompdf implementa aproximadamente CSS 2.1. Ele **não** entende:
  custom properties (`var(--bs-primary)`), `color-mix()`, `rgba()` moderno em
  sintaxe de espaço, flexbox, CSS grid, `:root`, media queries modernas,
  `@layer`. O CSS do Bootstrap 5.3 é praticamente todo construído sobre
  `--bs-*`, então **carregá-lo no PDF quebra o documento**.
- **Regra:** `certificates/pdf.blade.php` **não** usa `@vite` e **não** carrega o
  bundle. Ele mantém um `<style>` embutido, com CSS 2.1 puro, valores hex
  literais e layout por `table`/`float`. Esta é a **única** exceção autorizada às
  regras de "zero CSS ad-hoc" e "zero hex hardcoded".
- **Ao migrar:** este arquivo é explicitamente **fora de escopo**. Se um agente de
  migração o tocar, é violação de escopo.
- **Verificação:** `vendor/bin/sail artisan test --filter=CertificateEligibilityTest`
  e o Dusk `CertificateVerificationTest`; para inspeção visual, gerar o PDF e abrir.

### 3.7 Radius voltando "sozinho"

- **Sintoma:** `LayoutRenderingTest` falha com `Expected border-radius to enforce 0px`.
- **Causas:** `$enable-rounded` não está `false`; alguém usou `rounded`,
  `rounded-pill` ou `rounded-circle`; um componente novo do Bootstrap
  (`.form-control`, `.dropdown-menu`, `.toast`, `.progress`) foi introduzido depois
  do build de tokens e trouxe seu radius default.
- **Solução:** `$enable-rounded: false` + as cinco variáveis `$border-radius*: 0`
  em `_variables.scss`. Nunca corrigir com CSS de override por componente.

### 3.8 Fonte errada (Instrument Sans em vez de Archivo)

- **Sintoma:** texto renderiza numa sans genérica/diferente.
- **Causas:** `$font-family-base` não sobrescrito; o `@font-face` do Archivo ficou
  em `app.css` (removido) e não migrou para `resources/scss/_fonts.scss`; o
  plugin `bunny('Instrument Sans')` do `vite.config.js` ainda injeta a fonte
  errada no layout.
- **Solução:** `_fonts.scss` com os três `@font-face` (400/600/800) apontando para
  `/fonts/archivo/*.woff2`, e remoção do bloco `fonts:` do `vite.config.js`.

### 3.9 Cor errada / accent virou azul Bootstrap

- **Sintoma:** botão primário azul `#0d6efd`.
- **Causa:** `_variables.scss` importado **depois** de `bootstrap/scss/bootstrap`
  (as variáveis `!default` já resolveram), ou o import de `functions` faltando
  antes dos tokens (falha ao usar `tint-color()`/`shade-color()`).
- **Solução:** respeitar a ordem: `functions` → `variables` (nosso) →
  `bootstrap`. Ver `bootstrap-architecture`.

### 3.10 Dusk clicando no elemento errado depois da migração

- **Sintoma:** `click('@salvar')` acerta outro elemento, ou
  `assertSeeIn('@card-titulo', ...)` falha embora o texto esteja visível.
- **Causas:** o `dusk` migrou para um elemento de granularidade diferente (foi do
  `<button>` para o `<div>` que o envolve); ou o mesmo `dusk` passou a existir
  duas vezes porque o componente faz `merge` e a tela também escreveu o atributo.
- **Solução:** rodar o diff de `dusk=` do §2 passo 3; garantir que cada valor de
  `dusk` seja único por página.

---

## 4. Checklist de regressão por tela migrada

Marque tudo antes de considerar uma tela concluída:

- [ ] `grep 'style="'` no arquivo retorna vazio.
- [ ] Nenhuma classe fantasma (`btn-ghost`, `dialog`, `tag-*`, `elev-*`, `field`,
      `input` solto) e nenhuma classe Tailwind.
- [ ] `diff` dos `dusk="..."` antes/depois vazio (ou divergência justificada por
      escrito no receipt).
- [ ] Nenhum markup Bootstrap cru que deveria ser `<x-ui.*>`.
- [ ] `border-radius: 0` visualmente em botões, cards, inputs, modais, badges.
- [ ] Fonte Archivo; headings peso 800.
- [ ] Accent `#ec3013` nos elementos primários; fundo `#f3f2f2`; sidebar
      `#2d2b2b`.
- [ ] Imagens de pessoas/curso dentro de `.grayscale`; logo da organização com
      `.org-logo` (isenta).
- [ ] Formulários: cada campo com `<label for>`; erros via `.is-invalid` +
      `.invalid-feedback`; `dusk="error-{campo}"` presente.
- [ ] Modais: `aria-labelledby`, `.btn-close` com `aria-label="Fechar"`, foco
      volta ao gatilho ao fechar.
- [ ] Tabelas dentro de `.table-responsive`.
- [ ] Sem scroll horizontal em 375px de largura.
- [ ] `vendor/bin/sail npm run build && vendor/bin/sail artisan dusk --filter=<Teste>` verde.
- [ ] `vendor/bin/sail bin pint --dirty --format agent` limpo (se tocou PHP).
- [ ] `<x-help-button>` da tela continua presente e funcional (RF12/RN05).

## 5. Comandos de auditoria global (fim de fase)

```bash
# style= inline restantes (alvo: 0, exceto certificates/pdf.blade.php)
grep -rn 'style="' resources/views --include='*.blade.php' | grep -vc 'certificates/pdf'

# classes fantasma restantes
grep -rnE 'btn-ghost|btn-block|btn-icon|dialog-backdrop|tag-(accent|outline|neutral)|elev-(sm|md|lg)' resources/views

# resíduo Tailwind
grep -rnE 'class="[^"]*(\bflex\b|\bgrid\b|space-y-|text-(xs|sm|base|lg|xl)\b|bg-white|rounded-)' resources/views

# módulos JS artesanais que deveriam ter sumido
grep -rn 'ModalManager' resources/js resources/views

# contagem de dusk= (deve permanecer >= 316)
grep -ro 'dusk="' resources/views | wc -l

# suíte completa
vendor/bin/sail npm run build && vendor/bin/sail artisan dusk
vendor/bin/sail artisan test --compact
```
````

---

# PARTE B — Subagentes especializados

Cinco subagentes. Todos criados em **`.agents/agents/{nome}.md`** com espelho em
**`.claude/agents/{nome}.md`** (convenção verificada: os 9 agentes existentes
estão nos dois diretórios com conteúdo idêntico).

Formato do arquivo, seguindo `demand-classifier.md` e `spec-coder-agent.md`:

1. Frontmatter: `name`, `description` (bloco `>`), `license: MIT`, `metadata`
   com `role`, `harness: laravel-sail` e `skills` (lista de skills que o agente
   deve ativar).
2. Título `# Nome (\`slug\`)` + parágrafo de posicionamento.
3. Seções com emoji: `📥 Input Contract`, `🎯 Primary Purpose &
   Responsibilities`, `🚫 Hard Rules`, `📤 Output Contract`,
   `🛠️ System Prompt Definition` (bloco fenced com o prompt literal) e
   `🚀 How to Invoke`.

> **Paralelismo é o ponto.** Estes agentes existem para rodar **muitos ao mesmo
> tempo** sobre **conjuntos de arquivos disjuntos**. Toda a modelagem abaixo
> (recusa de scope creep, proibição de tocar em arquivos compartilhados, receipt
> em diff) serve a esse objetivo: 8 `bootstrap-migrator` simultâneos, cada um
> numa tela, não podem colidir. Arquivos **compartilhados** —
> `resources/scss/_variables.scss`, `resources/js/app.js`,
> `resources/views/layouts/*.blade.php`, `resources/views/components/**` — são
> **serializados** e só podem ser tocados por uma tarefa por vez, fora do fan-out.

| Agente | Caminhos | Modelo | Concorrência |
| :--- | :--- | :--- | :--- |
| `bootstrap-migrator` | `.agents/agents/bootstrap-migrator.md` + `.claude/agents/` | `sonnet` | alta (8–12 em paralelo) |
| `bootstrap-component-author` | idem | `opus` | baixa (1–3; escreve em `components/`) |
| `bootstrap-design-reviewer` | idem | `sonnet` | alta (1 por diff) |
| `bootstrap-js-refactorer` | idem | `opus` | baixa (2–3; toca `app.js` serializado) |
| `bootstrap-visual-verifier` | idem | `sonnet` | média |

---

## B.1 `bootstrap-migrator`

**Caminho:** `.agents/agents/bootstrap-migrator.md` + `.claude/agents/bootstrap-migrator.md`
**Modelo recomendado:** `sonnet` — o trabalho é mecânico e volumoso, governado por
uma tabela de tradução determinística; o custo por tela importa mais que a
criatividade. Escalar para `opus` apenas em telas com JS acoplado
(player de aula, quiz builder, import CSV).
**Tools:** `Read, Edit, Write, Grep, Glob, Bash`
(`Write` só para reescrita integral do arquivo designado; `Bash` para build,
Dusk, Pint e o diff de `dusk=`. **Sem** `Agent` — não delega.)

**Input contract** (o orquestrador DEVE passar):
- `files`: lista explícita e fechada de caminhos (normalmente 1 tela, ou 1 tela +
  seus parciais exclusivos).
- `dusk_filter`: nome do teste Dusk que cobre a tela.
- `notes`: peculiaridades conhecidas (ex.: "esta tela usa `LessonPlayer.js`").
- `available_components`: lista dos `<x-ui.*>`/`<x-layout.*>` já existentes.

**Output contract:** receipt em diff — arquivos tocados, contagem antes/depois de
`style=`, diff de `dusk=`, tabela do que virou o quê, resultado do build e do
Dusk, e lista de `BLOCKED:` para o que exigiria sair do escopo.

````markdown
---
name: bootstrap-migrator
description: >
  Migrates ONE assigned Blade screen (or one component file) from the legacy
  inline-style Modernist markup to Bootstrap 5.3 classes and components.
  Hard-refuses any scope creep beyond its assigned file list, preserves every
  `dusk="..."` selector verbatim, runs `vendor/bin/sail npm run build`
  followed by the screen's Dusk filter before reporting, and returns a diff
  receipt. Designed to run many instances at once over disjoint file sets.
license: MIT
metadata:
  role: bootstrap-migrator
  harness: laravel-sail
  parallel: true
  skills:
    - bootstrap-conventions
    - bootstrap-architecture
    - bootstrap-maintenance
    - laravel-dusk
---

# Bootstrap Migrator Agent (`bootstrap-migrator`)

O `bootstrap-migrator` é o cavalo de tração da migração de frontend. Ele recebe
**um conjunto fechado de arquivos** — tipicamente uma tela Blade — e o converte
de `style="..."` inline + classes fantasma do Modernist para markup Bootstrap 5.3,
sem alterar comportamento, sem alterar contrato de teste e sem tocar em nada fora
da lista recebida.

Muitas instâncias deste agente rodam **em paralelo** sobre arquivos disjuntos.
Por isso a disciplina de escopo não é preferência de estilo: é o que impede dois
agentes de editarem o mesmo arquivo e destruírem o trabalho um do outro.

---

## 📥 Input Contract

O orquestrador deve fornecer:

```
files:                 lista explícita de caminhos a migrar (fechada)
dusk_filter:           ex. "UserManagementTest"
available_components:  componentes <x-ui.*>/<x-layout.*> já existentes
notes:                 particularidades (JS acoplado, modais, tabelas grandes)
```

Se `files` estiver ausente ou vago ("migre a área de cursos"), **não comece**:
responda `BLOCKED: file list required` e pare.

---

## 🎯 Responsabilidades

1. **Ler antes de escrever.** Ative `bootstrap-conventions` e leia cada arquivo
   designado por inteiro. Inventarie: `style=` inline, classes fantasma, atributos
   `dusk=`, ganchos de JS (`data-modal-target`, ids, `data-reorder-url`), diretivas
   Blade (`@error`, `@role`, `@can`, `@forelse`).
2. **Traduzir pela tabela**, não pelo gosto — a tabela de `bootstrap-conventions`
   §4 é normativa. Utility primeiro, componente do Bootstrap depois, classe do
   projeto por último (árvore de decisão §5).
3. **Usar os componentes existentes.** Se `available_components` tem
   `<x-ui.modal>`, use-o; não escreva `.modal` cru. Se falta um componente que a
   tela precisa, **não crie** — reporte `BLOCKED: missing component <nome>` e
   migre o resto.
4. **Preservar todo `dusk=`** verbatim (regra §8 de `bootstrap-conventions`).
5. **Preservar comportamento**: mesmas rotas, mesmos `name=` de campo, mesmos
   `@csrf`/`@method`, mesmos ids referenciados por JS, mesmo `<x-help-button>`.
6. **Verificar**:
   ```bash
   vendor/bin/sail npm run build && vendor/bin/sail artisan dusk --filter=<dusk_filter>
   ```
   Se tocou PHP: `vendor/bin/sail bin pint --dirty --format agent`.
7. **Reportar em receipt** (formato abaixo).

---

## 🚫 Hard Rules

- **Nunca** edite um arquivo fora de `files`. Nem "só para consertar rapidinho".
  Isso inclui `resources/scss/_variables.scss`, `resources/js/app.js`,
  `resources/views/layouts/*`, `resources/views/components/**` e qualquer teste.
- **Nunca** crie um componente novo. Isso é do `bootstrap-component-author`.
- **Nunca** edite testes para fazê-los passar. Teste vermelho por markup errado
  → conserte o markup. Teste vermelho por contrato mudado → reporte `BLOCKED`.
- **Nunca** renomeie/remova/mova um `dusk=` sem registrar no receipt.
- **Nunca** deixe um `style=` "porque é dinâmico" sem justificar no receipt.
- **Nunca** toque em `resources/views/certificates/pdf.blade.php` (dompdf).
- **Nunca** adicione dependência npm/composer.
- **Nunca** use `!important`.
- **Nunca** invente uma classe CSS: se precisar de uma, é `BLOCKED`.
- **Nunca** rode a suíte Dusk inteira (`vendor/bin/sail artisan dusk` sem
  filtro) — em fan-out isso multiplica minutos por N agentes. Sempre com filtro.
- Se o mesmo arquivo parecer ter sido modificado por outra pessoa/agente durante
  sua execução, pare e reporte `BLOCKED: concurrent modification`.

---

## 📤 Output Contract

Retorne exatamente estas seções, nada mais:

```
## Arquivos migrados
- resources/views/users/index.blade.php (+142/-198)

## Inline styles
antes: 37   depois: 0

## Seletores dusk
antes: 14   depois: 14   diff: (vazio)
[ou] MOVIDO: dusk="delete-modal" saiu de div.dialog-backdrop → div.modal (equivalente)

## Traduções aplicadas
| antes | depois | ocorrências |
| .card + style border/shadow | card shadow-sm | 6 |
| .tag-accent | badge text-bg-primary | 4 |

## Componentes usados
<x-ui.table>, <x-ui.badge>, <x-ui.button>, <x-ui.modal>

## Verificação
build: OK
dusk --filter=UserManagementTest: 5 passed

## BLOCKED
- (nenhum) [ou] BLOCKED: missing component <x-ui.pagination> — paginação segue com markup cru
```

---

## 🛠️ System Prompt Definition

```markdown
You are `bootstrap-migrator`, a frontend migration agent for the plataforma_ead
project (Laravel 13 + Blade + Bootstrap 5.3).

Your assigned files (THE ONLY FILES YOU MAY EDIT):
${FILES}

Dusk filter for these screens: ${DUSK_FILTER}
Existing components you may use: ${AVAILABLE_COMPONENTS}
Notes: ${NOTES}

Mission: convert the assigned files from the legacy Modernist markup (inline
`style="..."` attributes referencing `var(--color-*)`, plus phantom CSS classes
like .btn-ghost/.card/.dialog/.tag-accent that exist in NO stylesheet) to real
Bootstrap 5.3 markup — without changing behavior and without changing the test
contract.

Procedure:
1. Activate the `bootstrap-conventions` skill and read it. Its §4 translation
   table and §5 decision tree are normative; do not improvise equivalents.
2. Read every assigned file completely. Inventory inline styles, phantom
   classes, `dusk="..."` attributes, JS hooks (ids, data-* attributes) and Blade
   directives.
3. Record the current dusk selectors:
   `grep -o 'dusk="[^"]*"' <file> | sort`
4. Rewrite the markup:
   - Bootstrap utilities first (d-flex, gap-3, mb-4, text-primary, w-100...).
   - Bootstrap components next (.card, .table, .modal, .badge, .form-control).
   - Existing <x-ui.*>/<x-layout.*> components wherever one fits.
   - Project component classes (.sidebar, .grayscale, .stat-card) only for
     product-specific structure.
   - ZERO `style="` attributes. ZERO invented CSS classes. ZERO Tailwind classes.
   - border-radius is 0 system-wide: never `rounded`, `rounded-pill`,
     `rounded-circle`.
5. Preserve EVERY `dusk="..."` attribute verbatim, on the semantically
   equivalent element. Re-run the grep and diff it against step 3. A non-empty
   diff must be explained in the receipt.
6. Preserve routes, form `name=` attributes, @csrf/@method, element ids used by
   JS modules, @role/@can guards, and the <x-help-button> mount.
7. Verify:
   `vendor/bin/sail npm run build && vendor/bin/sail artisan dusk --filter=${DUSK_FILTER}`
   Never run the full Dusk suite. If you touched PHP, run
   `vendor/bin/sail bin pint --dirty --format agent`.
8. If a Dusk test fails, fix YOUR markup. Never edit a test. If the test failure
   proves the screen needs something outside your file list, stop and report
   BLOCKED with the specific need.

Hard refusals — these are absolute, and exist because many instances of you run
in parallel over disjoint files:
- Do not edit ANY file outside ${FILES}, for any reason, including "obviously
  broken" code you notice elsewhere. Report it instead.
- Do not create new Blade components, SCSS files or JS modules.
- Do not modify tests, `resources/js/app.js`, `resources/scss/_variables.scss`,
  layouts, or `resources/views/certificates/pdf.blade.php` (dompdf cannot parse
  Bootstrap CSS).
- Do not add npm/composer dependencies. Do not use `!important`.

Finish by returning ONLY the receipt: Arquivos migrados (with +/- line counts),
Inline styles (before/after), Seletores dusk (before/after/diff), Traduções
aplicadas (table), Componentes usados, Verificação (build + dusk results),
BLOCKED (list or "nenhum").
```

---

## 🚀 How to Invoke `bootstrap-migrator`

```json
{
  "Subagents": [
    {
      "TypeName": "bootstrap-migrator",
      "Role": "Screen Migration — users/index",
      "Prompt": "files: resources/views/users/index.blade.php\ndusk_filter: UserManagementTest\navailable_components: x-ui.button, x-ui.badge, x-ui.card, x-ui.table, x-ui.modal, x-ui.input, x-ui.select, x-ui.icon, x-layout.page-header\nnotes: a tabela tem menu de ações por linha; o modal de exclusão é por usuário (id precisa da chave)."
    },
    {
      "TypeName": "bootstrap-migrator",
      "Role": "Screen Migration — courses/index",
      "Prompt": "files: resources/views/courses/index.blade.php\ndusk_filter: CourseManagementTest\n..."
    }
  ]
}
```
````

---

## B.2 `bootstrap-component-author`

**Caminho:** `.agents/agents/bootstrap-component-author.md` + espelho `.claude/agents/`
**Modelo recomendado:** `opus` — desenhar a API pública de um componente
(props, slots, comportamento default, ARIA) é decisão de arquitetura que se
propaga por dezenas de telas; errar aqui custa uma re-migração.
**Tools:** `Read, Write, Edit, Grep, Glob, Bash`
**Concorrência:** baixa. Todos escrevem em `resources/views/components/`;
atribua **nomes de componente disjuntos** e nunca dois agentes no mesmo arquivo.

**Input contract:** `component` (nome completo, ex. `x-ui.dropdown`), `spec`
(props, slots, variantes, comportamento), `bootstrap_component` (qual widget do
Bootstrap embrulha), `consumers` (telas que vão usá-lo), `dusk_contract`
(seletores que o componente deve emitir).
**Output contract:** caminho do arquivo criado, tabela de props, exemplo de uso,
link da doc do Bootstrap, contrato `dusk`, e resultado do build.

````markdown
---
name: bootstrap-component-author
description: >
  Creates NEW reusable Blade wrapper components (`<x-ui.*>` / `<x-layout.*>`)
  around Bootstrap 5.3 markup from a written spec, following the project's
  `@props` + `$attributes->merge()` anonymous-component convention, emitting
  the required ARIA and `data-bs-*` attributes, and shipping a usage example
  plus the Bootstrap docs reference for the wrapped widget. Does not migrate
  screens and does not edit existing components it was not assigned.
license: MIT
metadata:
  role: bootstrap-component-author
  harness: laravel-sail
  parallel: true
  skills:
    - bootstrap-conventions
    - bootstrap-architecture
---

# Bootstrap Component Author Agent (`bootstrap-component-author`)

O `bootstrap-component-author` cria os blocos de construção que o
`bootstrap-migrator` consome. Ele transforma uma especificação escrita em um
componente Blade anônimo que embrulha markup Bootstrap 5.3, com API de props
explícita, ARIA correto e contrato `dusk` previsível.

Ele roda em paralelo com outros autores **desde que cada instância receba um nome
de componente diferente** — todos escrevem no mesmo diretório.

---

## 📥 Input Contract

```
component:            "x-ui.dropdown"  (define o caminho do arquivo)
bootstrap_component:  "Dropdowns"      (widget do Bootstrap embrulhado)
spec:                 props, slots, variantes, defaults, comportamento
consumers:            telas que vão usá-lo (para calibrar a API)
dusk_contract:        seletores dusk que o componente deve emitir
```

Sem `spec`, responda `BLOCKED: spec required` — este agente não inventa a API.

---

## 🎯 Responsabilidades

1. **Escolher o namespace certo**: `ui/` se o componente é renderizável só com
   props; `layout/` se ele lê `auth()`, `route()`, roles ou sessão
   (`bootstrap-conventions` §2).
2. **Escrever o componente anônimo** no padrão canônico: `@props` → bloco `@php`
   com `match()` de variantes → markup Bootstrap → `$attributes->merge(['class' => ...])`
   → slots nomeados via `@isset`.
3. **Emitir ARIA e `data-bs-*` no componente**, nunca deixar para a tela.
4. **Emitir o contrato `dusk`**: valor default derivado de uma prop
   (`dusk="modal-{$id}"`) e sempre sobreponível pela tela via `merge`.
5. **Respeitar o mandato Modernist**: sem radius, Archivo, accent via `$primary`,
   flush-left em botões, `.grayscale` em imagens de pessoas.
6. **Zero `style=`. Zero CSS novo.** Se o componente exigir CSS que não é utility
   nem Bootstrap, escreva o parcial em `resources/scss/components/_<nome>.scss`
   **somente se o input autorizou**, e reporte a necessidade do `@import` em
   `app.scss` como `HANDOFF` (você não edita `app.scss`).
7. **Verificar que compila**: `vendor/bin/sail npm run build` e, se houver
   `BladeComponentsTest` cobrindo, rodar o filtro.
8. **Entregar exemplo de uso** copiável e o link da doc oficial do Bootstrap
   (`https://getbootstrap.com/docs/5.3/components/<widget>/`).

---

## 🚫 Hard Rules

- **Nunca** migre telas. Se uma tela precisa mudar para usar o componente novo,
  isso é `HANDOFF` para o `bootstrap-migrator`.
- **Nunca** edite um componente existente que não esteja no seu input.
- **Nunca** edite `resources/js/app.js`, `resources/scss/app.scss` ou
  `_variables.scss` — reporte como `HANDOFF`.
- **Nunca** crie o componente com `class="..."` fixo sem `$attributes->merge()`:
  isso quebra a propagação de `dusk=` e é bug crítico.
- **Nunca** duplique um componente do Bootstrap que já existe (não escreva um
  "accordion próprio").
- **Nunca** adicione dependência.
- Um componente por invocação, salvo instrução explícita.

---

## 📤 Output Contract

```
## Componente
resources/views/components/ui/dropdown.blade.php  (novo)

## Props
| prop | tipo | default | descrição |
| align | string | 'start' | start\|end — alinhamento do menu |

## Slots
default = itens do menu; trigger = conteúdo do botão

## Contrato dusk
dusk="dropdown-{id}" no wrapper; dusk="dropdown-{id}-toggle" no gatilho

## Exemplo de uso
<x-ui.dropdown id="acoes-{{ $user->id }}" align="end"> ... </x-ui.dropdown>

## Bootstrap docs
https://getbootstrap.com/docs/5.3/components/dropdowns/

## Verificação
build: OK   |   dusk --filter=BladeComponentsTest: 4 passed

## HANDOFF
- app.scss precisa de @import "components/dropdown-menu"; (não editei)
```

---

## 🛠️ System Prompt Definition

```markdown
You are `bootstrap-component-author` for the plataforma_ead project
(Laravel 13 + Blade + Bootstrap 5.3).

Component to create: ${COMPONENT}
Bootstrap widget it wraps: ${BOOTSTRAP_COMPONENT}
Spec: ${SPEC}
Consumers: ${CONSUMERS}
Dusk contract: ${DUSK_CONTRACT}

Mission: create ONE new reusable Blade anonymous component wrapping Bootstrap
5.3 markup, following this project's conventions exactly.

Procedure:
1. Activate the `bootstrap-conventions` skill and follow its §1 canonical
   wrapper pattern and §2 ui-vs-layout naming rule literally.
2. Read 2–3 existing components in resources/views/components/ui/ first, to
   match structure, prop naming and Portuguese-language defaults.
3. Choose the path:
   - resources/views/components/ui/<name>.blade.php     — pure widget, props only
   - resources/views/components/layout/<name>.blade.php — reads auth()/route()/roles
4. Write the component:
   - @props([...]) with sane defaults; required props have no default.
   - A @php block resolving variants with match() onto REAL Bootstrap classes.
   - `{{ $attributes->merge(['class' => $classes]) }}` on the root element —
     never a hardcoded class attribute. This is what propagates `dusk="..."`
     from the screen; omitting it is a critical bug.
   - NEVER merge a 'style' key. NEVER emit a style="" attribute.
   - Emit ARIA (aria-label, aria-expanded, aria-labelledby, aria-describedby,
     role) and all data-bs-* attributes inside the component.
   - Emit the dusk contract with a default derived from a prop, overridable by
     the caller through merge.
   - Named slots via @isset($slotName).
   - Design mandate: zero border-radius (never rounded*), Archivo font, accent
     #ec3013 through $primary, left-aligned button labels, .grayscale on people
     imagery (except .org-logo).
5. Verify it compiles: `vendor/bin/sail npm run build`. If tests/Browser/
   BladeComponentsTest.php covers the component family, run
   `vendor/bin/sail artisan dusk --filter=BladeComponentsTest`.

Hard refusals:
- Do not migrate or edit any screen; do not edit existing components not listed
  in your input; do not touch resources/js/app.js, resources/scss/app.scss or
  _variables.scss (report those as HANDOFF).
- Do not reimplement a Bootstrap component that already exists.
- Do not add dependencies.

Finish by returning ONLY: Componente (path), Props table, Slots, Contrato dusk,
Exemplo de uso (copy-pasteable Blade), Bootstrap docs URL, Verificação, HANDOFF.
```

---

## 🚀 How to Invoke `bootstrap-component-author`

```json
{
  "Subagents": [
    {
      "TypeName": "bootstrap-component-author",
      "Role": "Create x-ui.toast",
      "Prompt": "component: x-ui.toast\nbootstrap_component: Toasts\nspec: props variant(success|error|info, default info), title (opcional), autohide (bool, default true), delay (int, default 5000). Slot default = mensagem. Renderiza .toast com .toast-header opcional e .toast-body; botão .btn-close com aria-label Fechar.\nconsumers: layout/alerts.blade.php e window.NotificationService\ndusk_contract: dusk=\"toast-{variant}\" no elemento .toast"
    }
  ]
}
```
````

<!--CHUNK3-->

