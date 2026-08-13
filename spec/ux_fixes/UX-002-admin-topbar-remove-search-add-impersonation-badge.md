# UX-002: Top bar exibe barra de busca inoperante e não sinaliza o Impersonate Org ativo

> **Revalidado em 2026-08-13 (pós-migração Bootstrap 5.3, commit 3088d99):** AINDA VÁLIDO, integralmente. A migração apenas trocou as classes do campo de busca (`.input` → `form-control ps-5 w-100 h-36 fs-6`); ele continua sem `<form>`, sem `name`, sem `action` e sem handler JS. O badge de Impersonate segue inexistente na topbar e `ImpersonateOrgController@destroy` segue com `back()`.

## 1. Executive Summary & Impact
- **ID:** UX-002
- **Severity:** High (o Admin não tem indicação persistente de em qual Organização está agindo)
- **Affected Role(s):** admin (o campo de busca inoperante afeta todos os perfis)
- **Tenant Context:** Admin-global vs. Impersonate Org
- **Summary:** A top bar mostra um campo "Buscar cursos, aulas..." que não pertence a formulário algum e não dispara nenhuma ação — ocupa espaço nobre e promete uma funcionalidade inexistente. Ao mesmo tempo, quando o Admin assume o contexto de uma Organização, nada na top bar indica isso: o único aviso vive dentro de `/organizations`, de modo que em qualquer outra tela o Admin age sobre uma Organização sem saber. A demanda pede remover a busca e colocar na top bar um badge da Organização ativa com a opção de encerrar o impersonate, redirecionando para `admin/dashboard`.

## 2. Step-by-Step Reproduction Guide
### Pre-conditions:
1. Usuário com role `admin`.
2. Pelo menos uma Organização ativa cadastrada.

### Reproduction Steps:
1. Fazer login como `admin`.
2. Observar a top bar: campo de busca ao centro, botão de ajuda, sino, nome/perfil, "Meu Perfil", "Sair".
3. Digitar qualquer termo no campo de busca e pressionar `Enter`. Nada acontece.
4. Acessar `/organizations` e clicar em "Entrar como" numa Organização.
5. Navegar para qualquer outra tela (ex.: `/users`, `/courses`) e observar a top bar.

### Expected Behavior (Happy Path):
- A top bar **não** exibe campo de busca.
- Sem Impersonate Org ativo, a top bar mostra: botão de ajuda, sino de notificações, identificação do usuário, "Meu Perfil", "Sair".
- Com Impersonate Org ativo, além dos itens acima, um **badge** com o nome da Organização ativa, acompanhado de um controle de "Sair do contexto"/"Desabilitar impersonate", visível em **todas** as telas.
- Ao encerrar o impersonate por esse controle, o usuário é redirecionado para `admin/dashboard` (`admin.dashboard`), e não de volta à tela anterior.

### Actual Behavior (UX atual):
- Campo de busca sempre presente no wrapper `d-none d-md-block flex-grow-1 max-w-400 mx-5` (`topbar.blade.php:39`), sem `<form>`, sem `name`, sem `action`, sem handler JS — puramente decorativo.
- Nenhum indicativo de Impersonate Org na top bar. O único aviso existe em `organizations/index.blade.php`.
- `ImpersonateOrgController@destroy` responde com `back()`, devolvendo o Admin à tela onde estava — que pode ser uma tela dependente do contexto que acabou de ser encerrado.

## 3. Codebase & Architectural Mapping
- **Route Name / URL:** agnóstico — a top bar é renderizada em todas as rotas de `layouts.app`.
- **Blade View / Component (pós-Bootstrap 5.3):**
  - `resources/views/components/layout/topbar.blade.php:39-47` — bloco do campo de busca a remover (wrapper `d-none d-md-block flex-grow-1 max-w-400 mx-5` + `<svg>` de lupa + `<input class="form-control ps-5 w-100 h-36 fs-6">`).
  - `resources/views/components/layout/topbar.blade.php:49-82` — cluster direito (`d-flex align-items-center gap-3`) onde o badge deve ser inserido, entre `<x-notifications-bell />` (`:52`) e o bloco `@auth` (`:54`).
  - `resources/views/layouts/app.blade.php` — monta `<x-layout.topbar />`.
  - `resources/views/organizations/index.blade.php:10-20` — banner de contexto existente, já migrado para `<x-ui.alert variant="warning" dismissable>`, com `dusk="exit-impersonation-form"` (`:14`) e `dusk="exit-impersonation"` (`:17`). Referência de copy; avaliar se permanece após o badge global entrar.
  - `resources/views/components/ui/badge.blade.php` — componente existente (`@props(['variant'])`, variantes `accent`/`outline`/`neutral`/`accent-2` mapeadas para `badge text-bg-*`), a reutilizar; **não criar um novo**.
- **Controller / Action:** `App\Http\Controllers\ImpersonateOrgController@destroy` — `app/Http/Controllers/ImpersonateOrgController.php:55-77` (retorna `back()` em `:76`; deve passar a redirecionar para `admin.dashboard`).
- **Rotas envolvidas:** `impersonate-org.destroy` (`DELETE /impersonate-org`) e `admin.dashboard`.
- **Injeção de dados na top bar:** `App\Http\View\Composers\NavigationComposer::compose()` — `app/Http/View/Composers/NavigationComposer.php:29-37` (hoje injeta `$navigationSections`, `$brandUrl`, `$loginUrl`, `$logoutUrl`) — ponto natural para expor a Organização ativa sem consultar o banco dentro do Blade.
- **Model:** `App\Models\Organization`
- **Auditoria:** `AuditService::log(event: 'impersonate.stop', ...)` — `app/Http/Controllers/ImpersonateOrgController.php:62-71`, deve permanecer intacta.

## 4. Root Cause Analysis (apresentação / markup)
**Campo de busca.** Em `resources/views/components/layout/topbar.blade.php:39-47` há um `<input type="text" class="form-control ps-5 w-100 h-36 fs-6" placeholder="Buscar cursos, aulas..." />` isolado — sem `<form>`, sem `name`, sem `action` e sem qualquer módulo JS correspondente em `resources/js/modules/` que o escute (a lista de módulos vivos não contém nenhum de busca). A migração para Bootstrap trocou `.input` por `.form-control` e nada mais: continua sendo um resquício de mockup, uma affordance que promete busca global e não entrega nada.

**Badge de impersonate.** A top bar hoje ignora completamente `session('active_org_id')`. O feedback de contexto foi implementado apenas no ponto de origem da ação (`organizations/index.blade.php:10-20`), o que resolve a confirmação imediata mas não a **persistência** do sinal ao longo da navegação.

**Redirect ao encerrar.** `ImpersonateOrgController@destroy` termina com:

```php
// app/Http/Controllers/ImpersonateOrgController.php:76
return back()->with('success', 'Contexto de Organização encerrado.');
```

`back()` era adequado quando o único gatilho vivia em `/organizations`. Com um controle global na top bar, `back()` pode devolver o Admin a uma tela cujo conteúdo dependia do contexto recém-encerrado (ex.: `/courses` sob impersonate). O destino determinístico pedido é `admin.dashboard`.

Direção da correção (vocabulário Bootstrap 5.3 — zero `style=`, zero classe fantasma):
1. Remover integralmente o bloco `topbar.blade.php:39-47`, inclusive o `<svg>` de lupa. Com ele fora, o `d-flex align-items-center justify-content-between` do `<header>` (`:13`) já redistribui marca e cluster direito — nenhum ajuste de largura é necessário.
2. Expor a Organização ativa via `NavigationComposer` (ex.: `$activeOrganization`), evitando `\App\Models\Organization::find(...)` dentro do Blade — o padrão usado em `organizations/index.blade.php:13` não deve ser replicado numa view global renderizada em toda requisição.
3. Renderizar, quando `$activeOrganization` não for nulo, o par badge + controle no cluster direito (`topbar.blade.php:49-53`, logo após `<x-notifications-bell />`), reutilizando os componentes existentes:

```blade
@if ($activeOrganization ?? null)
    <div class="d-flex align-items-center gap-2 pe-3 border-end" dusk="topbar-impersonation">
        <x-ui.badge variant="accent-2" dusk="topbar-active-org-badge">
            {{ $activeOrganization->name }}
        </x-ui.badge>

        <form method="POST" action="{{ route('impersonate-org.destroy') }}">
            @csrf
            @method('DELETE')
            <x-ui.button type="submit"
                         variant="ghost"
                         size="sm"
                         class="text-decoration-underline"
                         title="Sair do contexto"
                         dusk="topbar-exit-impersonation">Sair do contexto</x-ui.button>
        </form>
    </div>
@endif
```

   Notas de conformidade: `border-end`/`pe-3`/`gap-2` são utilities do Bootstrap (`pe-3` = 16px na escala do projeto, `resources/scss/app.scss:68` + `:102-109`); `<x-ui.button variant="ghost">` já resolve para `btn btn-link text-body text-decoration-none` — **não** escrever `.btn-ghost`; o `dusk=` chega ao elemento porque ambos os componentes usam `$attributes->merge()` (`badge.blade.php:19`, `button.blade.php`). Nada de `style=`, `rounded*`, hex ou `var(--color-*)`.
4. Trocar `back()` por `redirect()->route('admin.dashboard')` em `ImpersonateOrgController.php:76`, mantendo a flash message e o `AuditService::log('impersonate.stop')` (`:62-71`).
5. Garantir que o badge não vaze para perfis não-Admin (só o Admin pode ter `active_org_id`, mas o composer deve resolver `$activeOrganization` como `null` fora desse caso).

## 5. Test Specification Plan (TDD Blueprint)
- **Feature test (PHPUnit):** `tests/Feature/ImpersonateOrgTest.php` (arquivo existente — acrescentar casos)
  - admin com `active_org_id`: `GET /users` (ou outra tela autenticada) → `assertSee` do nome da Organização ativa na resposta.
  - admin sem `active_org_id`: a mesma tela **não** contém o badge.
  - `DELETE /impersonate-org` → `assertRedirect(route('admin.dashboard'))` e `session` sem `active_org_id`.
  - a linha de auditoria `impersonate.stop` continua sendo gravada (não-regressão).
  - nenhuma tela autenticada contém mais o `placeholder="Buscar cursos, aulas..."`.
- **Browser test (Dusk):** `tests/Browser/Navigation/AdminTopbarTest.php`
  - `dusk="topbar-active-org-badge"`: visível após "Entrar como", em pelo menos duas telas diferentes.
  - `dusk="topbar-exit-impersonation"`: clicar leva a `/admin/dashboard` (`assertPathIs('/admin/dashboard')`) e o badge desaparece.
  - `assertMissing('input[placeholder="Buscar cursos, aulas..."]')` em qualquer tela autenticada.
  - o layout da top bar não quebra em viewport estreita com o badge presente (`resize` + `assertVisible` dos controles de perfil/sair).

## 6. Acceptance Criteria for Fix Verification
- [ ] A barra de busca não existe mais em nenhuma tela autenticada.
- [ ] Com Impersonate Org ativo, o badge com o nome da Organização aparece na top bar em todas as telas.
- [ ] O controle de encerrar impersonate está ao lado do badge e é acionável por teclado.
- [ ] Encerrar o impersonate redireciona para `/admin/dashboard`.
- [ ] Sem impersonate ativo, nenhum badge nem controle de encerramento é renderizado.
- [ ] A Organização ativa é resolvida no composer, não via query dentro do Blade.
- [ ] O evento de auditoria `impersonate.stop` continua sendo registrado.
- [ ] Botão de ajuda, sino de notificações, "Meu Perfil" e "Sair" permanecem funcionais e inalterados.
- [ ] O markup novo usa apenas `<x-ui.badge>`, `<x-ui.button>` e utilities do Bootstrap — nenhum `style=`, nenhuma classe Tailwind, nenhuma classe fantasma (`.btn-ghost`, `.tag-*`), nenhum `rounded*`, nenhum hex e nenhum `var(--color-*)`.
- [ ] O `dusk="mobile-menu-button"` e o `data-bs-target="#mobile-sidebar"` do drawer (`topbar.blade.php:19-32`) permanecem intactos.
- [ ] `vendor/bin/sail artisan test --compact --filter=ImpersonateOrgTest` passa.
- [ ] `vendor/bin/sail artisan dusk --filter=AdminTopbarTest` passa.
- [ ] `vendor/bin/sail bin pint --dirty --format agent` limpo.

## Resolution Status
- **Status:** OPEN
- **Reproduction Tests:** —
- **Fixed In Files:** —
