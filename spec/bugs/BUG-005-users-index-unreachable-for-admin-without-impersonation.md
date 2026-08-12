# BUG-005: Admin não consegue acessar `/users` sem Impersonate Org ativo (`UnresolvedOrgContextException`)

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
3. No menu lateral, clicar em "Alunos & Usuários" (`dusk="sidebar-users-link"`, aponta para `users.index`).
4. Observar a resposta.

### Expected Behavior (Happy Path):
- A tela de usuários abre normalmente para o Admin em contexto global.
- O Admin enxerga usuários de todas as Organizações (o Admin é global por definição — mesma semântica que `OrgScope` e `DashboardController::resolveViewingOrgId()` já aplicam: admin sem `active_org_id` ⇒ sem filtro).
- Com Impersonate Org ativo, a mesma tela permanece filtrada à Organização impersonada (comportamento atual preservado).

### Actual Behavior (Bug):
- `UnresolvedOrgContextException` é lançada em `resolveOrgId()`; a tela nunca renderiza.
- O link do menu, ainda assim, é exibido — violando o contrato do SPEC-17 (RN38/RN40) de que "um link que o usuário não consegue alcançar nunca está presente".

## 3. Codebase & Architectural Mapping
- **Route Name / URL:** `users.index` (`GET /users`) — `routes/web.php:75`, dentro do grupo `middleware(['auth', 'role:admin|gestor'])` (`routes/web.php:71`)
- **Controller / Action:** `App\Http\Controllers\UserController@index` — `app/Http/Controllers/UserController.php:31-47`
- **Trait / Resolução de contexto:** `App\Http\Controllers\Concerns\ResolvesOrgContext::resolveOrgId()` — `app/Http/Controllers/Concerns/ResolvesOrgContext.php:16-28`
- **Exception:** `App\Exceptions\UnresolvedOrgContextException`
- **Policy / Auth Gate:** `App\Policies\UserPolicy@viewAny` — `app/Policies/UserPolicy.php:19`
- **Blade View / Component:** `resources/views/users/index.blade.php`
- **Navegação:** `App\Services\Navigation\NavigationRegistry::items()` item `key: 'users'` — `app/Services/Navigation/NavigationRegistry.php:59-67`
- **Precedente correto no codebase:** `App\Http\Controllers\DashboardController::resolveViewingOrgId()` — `app/Http/Controllers/DashboardController.php:42-53`

## 4. Root Cause Technical Analysis
`UserController@index` usa a resolução **estrita** de contexto:

```php
// app/Http/Controllers/UserController.php:35
$orgId = $this->resolveOrgId($request);

$users = User::query()->where('org_id', $orgId)-> ... ;
```

E `ResolvesOrgContext::resolveOrgId()` retorna `int` (nunca `null`) e lança quando não há contexto:

```php
// app/Http/Controllers/Concerns/ResolvesOrgContext.php:18-26
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

Ponto de atenção correlato (mesmo bug, outra face): `NavigationRegistry` exibe o item `users` para `admin` sem qualquer `routeResolver` que verifique alcançabilidade. Após a correção do controller, o link passa a ser legítimo em ambos os contextos e nenhuma mudança no registry é necessária. Se, por decisão de produto, a tela **não** puder ser global, então o item precisa de um `routeResolver` que retorne `null` sem impersonate — mas essa alternativa contradiz a demanda original.

## 5. Test Specification Plan (TDD Blueprint)
- **Feature test (PHPUnit):** `tests/Feature/UserCrudTest.php` (arquivo existente — acrescentar casos)
  - `admin sem impersonate acessa users.index com sucesso (200)` e enxerga usuários de mais de uma Organização.
  - `admin com active_org_id em sessão vê apenas usuários daquela Organização` (não-regressão).
  - `gestor vê apenas usuários da própria org_id` (não-regressão).
  - `admin sem impersonate ainda recebe UnresolvedOrgContextException ao tentar users.store` (a resolução estrita da escrita permanece).
  - `aluno recebe 403 em users.index` (não-regressão do `role:admin|gestor`).
- **Browser test (Dusk):** `tests/Browser/UserManagementTest.php`
  - `dusk="sidebar-users-link"`: admin recém-logado, sem impersonate, clica no item do menu e chega em `/users` com a tabela renderizada (`waitFor('[dusk^="user-row-"]')`), sem página de erro.
  - após "Entrar como" numa Organização, a mesma tela lista apenas as linhas daquela Organização.

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
- **Status:** OPEN
- **Reproduction Tests:** —
- **Fixed In Files:** —
