# BUG-005: Admin não consegue acessar `/users` sem Impersonate Org ativo (`UnresolvedOrgContextException`)

> **Revalidado em 2026-08-13 (pós-migração Bootstrap 5.3, commit 3088d99):** AINDA VÁLIDO — `UserController@index` continua usando a resolução estrita ([UserController.php:35](file:///home/rafael/projects/cursos/plataforma_ead/app/Http/Controllers/UserController.php#L35) → [ResolvesOrgContext.php:16-27](file:///home/rafael/projects/cursos/plataforma_ead/app/Http/Controllers/Concerns/ResolvesOrgContext.php#L16-L27)) e o `NavigationService` segue exibindo o item `users` para o Admin sem qualquer checagem de contexto ([NavigationRegistry.php:59-67](file:///home/rafael/projects/cursos/plataforma_ead/app/Services/Navigation/NavigationRegistry.php#L59-L67)). Única mudança de sintoma: a exception é content-negotiated em [bootstrap/app.php:66-73](file:///home/rafael/projects/cursos/plataforma_ead/bootstrap/app.php#L66-L73), então o Admin vê um `302 back()` com alerta "Selecione uma Organização ativa antes de continuar." em vez de uma página de erro — a tela continua inalcançável.

## 1. Executive Summary & Impact
- **ID:** BUG-005
- **Severity:** High
- **Affected Role(s):** admin
- **Tenant Context:** Admin-global (falha ocorre exatamente quando **não** há `session('active_org_id')`)
- **Summary:** O item "Alunos & Usuários" aparece no menu lateral do Admin (o `NavigationRegistry` o libera para `['admin','gestor']`), mas clicar nele com o Admin em contexto global — isto é, sem Impersonate Org ativo — não abre a tela. `UserController@index` chama `resolveOrgId()`, que lança `UnresolvedOrgContextException` porque o Admin não tem `org_id` próprio nem `active_org_id` em sessão. Na prática, a tela de usuários só é alcançável quando o Admin está impersonando alguma Organização, o que contradiz o link exibido e deixa o Admin sem qualquer caminho para a gestão de usuários no escopo global.

> Escopo deste bug: **tornar a tela alcançável pelo Admin**. A tela exclusiva de administração global (listagem cross-org, filtros e ações de ativar/desativar/ver perfil completo) é funcionalidade nova e está especificada separadamente em `spec/to_refine/specs/SPEC-002-admin-global-user-management-screen.md`.

## 2. Step-by-Step Reproduction Guide
### Pre-conditions:
1. Usuário com role `admin` e `users.org_id = NULL` (Admin de sistema, sem Organização própria).
2. Sessão **sem** `active_org_id` (nenhum "Entrar como" executado, ou "Sair do contexto" já acionado).
3. Pelo menos uma Organização e alguns usuários `aluno`/`gestor` cadastrados.

### Reproduction Steps:
1. Fazer login como `admin`.
2. Confirmar que não há impersonate ativo (o banner de contexto em `/organizations` não aparece).
3. No menu lateral, clicar em "Alunos & Usuários" — desktop: `dusk="sidebar-users-link"` ([sidebar.blade.php:28-30](file:///home/rafael/projects/cursos/plataforma_ead/resources/views/components/layout/sidebar.blade.php#L28-L30)); mobile (Bootstrap Offcanvas `#mobile-sidebar`): `dusk="sidebar-users-link-mobile"` ([sidebar.blade.php:90-92](file:///home/rafael/projects/cursos/plataforma_ead/resources/views/components/layout/sidebar.blade.php#L90-L92)). Ambos apontam para `users.index`.
4. Observar a resposta.

### Expected Behavior (Happy Path):
- A tela de usuários abre normalmente para o Admin em contexto global.
- O Admin enxerga usuários de todas as Organizações (o Admin é global por definição — mesma semântica que `OrgScope` e `DashboardController::resolveViewingOrgId()` já aplicam: admin sem `active_org_id` ⇒ sem filtro).
- Com Impersonate Org ativo, a mesma tela permanece filtrada à Organização impersonada (comportamento atual preservado).

### Actual Behavior (Bug) — reverificado em 2026-08-13:
- `UnresolvedOrgContextException` é lançada em `resolveOrgId()` (`app/Http/Controllers/Concerns/ResolvesOrgContext.php:22`); a tela **nunca renderiza**.
- O handler global em `bootstrap/app.php:66-73` intercepta a exception: requisição web recebe `back()->withInput()->with('error', 'Selecione uma Organização ativa antes de continuar.')` (302 de volta à tela anterior, com o alerta renderizado por `resources/views/components/layout/alerts.blade.php:8-12`); requisição `expectsJson()` recebe 422. **Não** é uma página 500 — o sintoma visível é um "clique que não faz nada além de mostrar um alerta".
- O link do menu, ainda assim, é exibido — `NavigationService::resolve()` só aplica gates de role/permission/`routeResolver` (`app/Services/Navigation/NavigationService.php:83-108`) e o item `users` não tem `routeResolver`, então `route('users.index')` sempre resolve (`NavigationService.php:136-150`). Violando o contrato do SPEC-17 (RN38/RN40) de que "um link que o usuário não consegue alcançar nunca está presente".

## 3. Codebase & Architectural Mapping
(todas as referências reconferidas em 2026-08-13 contra o código pós-Bootstrap 5.3)

- **Route Name / URL:** `users.index` (`GET /users`) — `routes/web.php:75` (`Route::resource('users', ...)->except(['show'])`), dentro do grupo `middleware(['auth', 'role:admin|gestor'])` (`routes/web.php:71`). Confirmado em `vendor/bin/sail artisan route:list --except-vendor`: `GET|HEAD users → users.index › UserController@index`.
- **Controller / Action:** `App\Http\Controllers\UserController@index` — `app/Http/Controllers/UserController.php:31-47` (chamada estrita na linha 35, filtro `->where('org_id', $orgId)` na linha 38).
- **Trait / Resolução de contexto:** `App\Http\Controllers\Concerns\ResolvesOrgContext::resolveOrgId()` — `app/Http/Controllers/Concerns/ResolvesOrgContext.php:16-27` (throw na linha 22). `UserController` não sobrescreve `resolveOrgId()`; só personaliza a mensagem via `orgContextAction()` (`UserController.php:134`).
- **Exception:** `App\Exceptions\UnresolvedOrgContextException` — renderizada globalmente em `bootstrap/app.php:66-73` (422 para JSON, `back()` + flash `error` para web).
- **Policy / Auth Gate:** `App\Policies\UserPolicy@viewAny` — `app/Policies/UserPolicy.php:19-22` (passa para o Admin; a Policy **não** é a barreira aqui).
- **Blade View / Component:** `resources/views/users/index.blade.php` — migrada para Bootstrap 5.3, com os seletores preservados (`dusk="user-row-{id}"` na linha 14, `dusk="user-status-{id}"`, `dusk="new-user"`, `dusk="import-users"`).
- **Navegação (pós-migração):** o item vem de `App\Services\Navigation\NavigationRegistry::items()`, `key: 'users'` — `app/Services/Navigation/NavigationRegistry.php:59-67` (`roles: ['admin','gestor']`, sem `routeResolver`) → filtrado por `NavigationService::resolve()` (`app/Services/Navigation/NavigationService.php:83-150`) → injetado nas views por `App\Http\View\Composers\NavigationComposer` (`app/Http/View/Composers/NavigationComposer.php:22-24`), registrado para `components.layout.sidebar` e `components.layout.topbar` em `app/Providers/AppServiceProvider.php:40`. **Verificado:** para um Admin sem `active_org_id` o item continua sendo entregue (nenhum gate depende de contexto de organização), tanto no `<aside>` desktop (`sidebar.blade.php:17-41`) quanto no Offcanvas mobile (`sidebar.blade.php:79-103`).
- **Precedente correto no codebase:** `App\Http\Controllers\DashboardController::resolveViewingOrgId()` — `app/Http/Controllers/DashboardController.php:42-53` (retorna `?int`; `null` = global).

## 4. Root Cause Technical Analysis
`UserController@index` usa a resolução **estrita** de contexto:

```php
// app/Http/Controllers/UserController.php:35
$orgId = $this->resolveOrgId($request);

$users = User::query()->where('org_id', $orgId)-> ... ;
```

E `ResolvesOrgContext::resolveOrgId()` retorna `int` (nunca `null`) e lança quando não há contexto:

```php
// app/Http/Controllers/Concerns/ResolvesOrgContext.php:19-25
$orgId = $user->org_id ?? session('active_org_id');

if (! $orgId) {
    throw new UnresolvedOrgContextException(...);
}
```

Para um Admin de sistema, `org_id` é `NULL` e, sem impersonate, `session('active_org_id')` também é `null` ⇒ exceção garantida.

Essa resolução estrita é adequada para as ações de **escrita** (`store`, `import`), onde um `org_id` concreto é obrigatório para criar o registro. Ela é indevida em `index`, uma ação de **leitura**, cuja semântica global-para-Admin já está estabelecida em `DashboardController::resolveViewingOrgId()`:

```php
// app/Http/Controllers/DashboardController.php:46-52
if ($user->hasRole(RolesEnum::ADMIN->value)) {
    $activeOrgId = session('active_org_id');
    return $activeOrgId ? (int) $activeOrgId : null;   // null = global, sem filtro
}
return $user->org_id ? (int) $user->org_id : null;
```

A correção é alinhar `UserController@index` a esse precedente: resolver um `?int $orgId` e aplicar `->where('org_id', $orgId)` **apenas quando não for `null`**. `create`/`store`/`import` continuam usando `resolveOrgId()` estrito — um Admin sem contexto não deve poder criar usuário órfão de Organização.

Ponto de atenção correlato (mesmo bug, outra face): `NavigationRegistry` exibe o item `users` para `admin` sem qualquer `routeResolver` que verifique alcançabilidade — confirmado em `NavigationRegistry.php:59-67` e `NavigationService.php:136-150`, onde a ausência de `routeResolver` faz o item cair no caminho `Route::has()` → `route()`, que sempre resolve. Após a migração Bootstrap isso vale para as duas renderizações do mesmo array (`sidebar` desktop e Offcanvas mobile). Após a correção do controller, o link passa a ser legítimo em ambos os contextos e nenhuma mudança no registry é necessária. Se, por decisão de produto, a tela **não** puder ser global, então o item precisa de um `routeResolver` que retorne `null` sem impersonate — mas essa alternativa contradiz a demanda original.

## 5. Test Specification Plan (TDD Blueprint)
- **Feature test (PHPUnit):** `tests/Feature/UserCrudTest.php` (arquivo existente — acrescentar casos)
  - `admin sem impersonate acessa users.index com sucesso (200)` e enxerga usuários de mais de uma Organização.
  - `admin com active_org_id em sessão vê apenas usuários daquela Organização` (não-regressão).
  - `gestor vê apenas usuários da própria org_id` (não-regressão).
  - `admin sem impersonate ainda recebe UnresolvedOrgContextException ao tentar users.store` (a resolução estrita da escrita permanece).
  - `aluno recebe 403 em users.index` (não-regressão do `role:admin|gestor`).
  - `admin sem impersonate é redirecionado de volta com flash 'error'` — teste de **regressão do sintoma atual** (`assertRedirect()` + `assertSessionHas('error')`), que deve passar a falhar quando o fix entrar.
- **Browser test (Dusk):** `tests/Browser/UserManagementTest.php`
  - `dusk="sidebar-users-link"` (desktop, `sidebar.blade.php:29`): admin recém-logado, sem impersonate, clica no item do menu e chega em `/users` com a tabela renderizada (`waitFor('[dusk^="user-row-"]')`), sem alerta de "Selecione uma Organização ativa".
  - após "Entrar como" numa Organização, a mesma tela lista apenas as linhas daquela Organização.
  - **Pós-Bootstrap 5.3:** em viewport mobile o link vive no Offcanvas `#mobile-sidebar` e o seletor é `dusk="sidebar-users-link-mobile"` (`sidebar.blade.php:91`); é preciso abrir o Offcanvas pelo gatilho da topbar (`data-bs-toggle="offcanvas"`) antes do `click`.

## 6. Acceptance Criteria for Fix Verification
- [ ] Admin sem Impersonate Org ativo acessa `/users` e recebe HTTP 200.
- [ ] Nesse contexto, a listagem inclui usuários de todas as Organizações.
- [ ] Com Impersonate Org ativo, a listagem permanece restrita à Organização impersonada.
- [ ] Gestor continua restrito à própria `org_id`.
- [ ] `users.store` / `users.import` continuam exigindo contexto resolvido (nenhum usuário pode ser criado sem `org_id`).
- [ ] Nenhum `org_id` é aceito a partir do request em nenhuma das ações.
- [ ] `vendor/bin/sail artisan test --compact --filter=UserCrudTest` passa.
- [ ] `vendor/bin/sail bin pint --dirty --format agent` limpo.

## Resolution Status
- **Status:** CLOSED — o item de menu passou a ser condicional ao contexto de Organização (`NavigationRegistry::resolveUsersRoute()`). A tela global de usuários para o Admin segue sendo trabalho da SPEC-002.
- **Impacto da migração Bootstrap 5.3:** nenhum sobre a causa raiz (o commit não tocou controllers/rotas/middlewares/policies). Duas mudanças apenas na superfície do report: (1) o menu passou a ser alimentado por `NavigationComposer` (`app/Providers/AppServiceProvider.php:40`), que continua entregando o item `users` ao Admin sem contexto; (2) o menu mobile virou Bootstrap Offcanvas, adicionando o seletor `dusk="sidebar-users-link-mobile"` ao lado do `dusk="sidebar-users-link"` desktop.
- **Reproduction Tests:** —
- **Fixed In Files:** —
