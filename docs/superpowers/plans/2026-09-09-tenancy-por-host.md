# Tenancy por Host — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Substituir o paradigma multitenant por-sessão por tenancy resolvida pelo host (`HTTP_HOST` → `organizations.host`), com identidade de pessoa (`users`) separada de conta por org (`credentials`), landing por org (blade por org) e estado 0 (host não mapeado) com login admin-only.

**Architecture:** Mesmo banco MySQL, isolamento lógico. Middleware global `ResolveOrgFromHost` resolve a org por host e binda um `OrgContext` singleton por request (nunca sessão, exceto `active_org_id` do admin). `OrgScope`/`ResolvesOrgContext` leem a nova cadeia: admin+impersonação → org impersonada; admin sem → global (criar = exception); demais → org do host. Auth via `OrgCredentialUserProvider` custom que valida senha na linha `credentials` do par `(user, org-do-host)`.

**Tech Stack:** Laravel 13 (PHP 8.5), PHPUnit 12, Sail (Docker), spatie/laravel-permission, MySQL 8.4, Vite, Bootstrap 5.3.

**Spec:** Este documento — seção "Spec" abaixo + árvore de decisão aprovada com o usuário em 2026-09-09.

## Spec (decisões aprovadas)

1. **Host → org:** coluna `organizations.host` (nullable, unique, `^[a-z0-9.-]+$`, lowercase, porta removida, match exato). `organizations.landing_view` (nullable, admin-editável) = nome do blade da landing. Coluna `slug` removida (não é usada em rota nenhuma). Só admin cadastra host.
2. **Identidade:** `users` = pessoa (name, email unique global, cpf unique global, email_verified_at). Perde `password`, `org_id`, `status`, `remember_token`. Nova tabela `credentials`: `user_id`, `org_id` (null = conta admin global), `password`, `status` (`active`/`inactive`), `remember_token`; unique `(user_id, org_id)`.
3. **Role global no user** (spatie, `teams=false` intocado) — fase 1. Role por org = fase 2.
4. **Resolução de contexto:** admin com `session('active_org_id')` → org impersonada (pode diferir do host); admin sem impersonação → global (criar org-scoped → `UnresolvedOrgContextException`, como hoje); demais papéis → org do host, sempre.
5. **Login:** email → `users`; `credentials(user, org-do-host)` → `Hash::check`. Falha = `auth.failed` genérico (gestor no host errado indistinguível de senha errada). Rate limit `lower(email)|org|ip`.
6. **Estado 0** (host não mapeado — IP direto, domínio errado, org sem host): visitante → `/login` que só aceita admin; autenticado não-admin → logout + redirect login. Admin autenticado navega normalmente (org CRUD vive lá).
7. **Org inativa:** landing visível; login/esqueci-senha/convite falham; sessão já aberta (não-admin) → middleware desloga. Admin navega.
8. **Landing:** `landing_view` → `tenants.{landing_view}.landing`. Sem blade (null ou view inexistente) → redirect ao login da org.
9. **Shell logado:** nome + `organizations.logo_path` da org do host (não-admin). Admin global/estado 0: `system_name` (setting global).
10. **Fluxos públicos host-scoped:** `/validar-certificado/{hash}` → org do certificado ≠ org do host = não encontrado; `/convite/{token}` só vale no host da org; formulário adaptativo do convite muda de base para **existence de credential na org do host** (credential existe → só senha; user existe sem credential aqui → cria credential com a senha; user novo → form completo); CSV import reusa user global + cria credential da org; reset de senha altera a credential da org do host (link aponta pro host da org).
11. **Simulação:** `/etc/hosts` aponta `localhost.ligacerto` e `localhost.informatica` → 127.0.0.1 (porta 8080 do Sail). Estado 0 = `127.0.0.1:8080`. `compose.yaml` intocado. `SESSION_DOMAIN` permanece nulo.
12. **Clean slate:** re-seed total (admin + 2 orgs demo com hosts + blades demo). Sem migração de dados.
13. **Testes:** `actingAsOrgUser($org)` injeta `HTTP_HOST` via `withServerVariables`; Dusk ganha `visitHost()`; CI ganha entradas de hosts.
14. **Fase 2 (não-objetivos):** blades de login por org, cores/tema por org, SMTP por org ligado de fato, role por org, landing data-driven.

## Global Constraints

- Sail sempre: `vendor/bin/sail artisan …`, `vendor/bin/sail composer …`, `vendor/bin/sail npm …`.
- PHPUnit (nunca Pest). Testes novos: `vendor/bin/sail artisan make:test --phpunit`.
- Pint após alterar PHP: `vendor/bin/sail bin pint --dirty --format=agent` (no fim de cada task).
- Codestyle de método (CLAUDE.md): params múltiplos = multiline com named args; uma linha curta = inline.
- Curly braces sempre; constructor promotion; return types explícitos; PHPDoc com array shapes.
- `dusk=` selectors preservados verbatim em blades tocados.
- Sem dependência nova. Sem pasta nova de primeiro nível em `app/` (usar `app/Services`, `app/Http/Middleware`, `app/Models`, `app/Auth` já aceitável como subpasta de provider? — usar `app/Services` para tudo não-HTTP).
- Nunca aplicar `OrgScope` em `User` nem em `Credential` (credential tem org_id nullable admin; queries explícitas).
- Mensagens de falha de auth SEMPRE genéricas (`trans('auth.failed')`) — nunca revelar existência de conta em outra org.

---

### Task 1: Schema — `users` trim + `credentials` + `organizations.host/landing_view`

**Files:**
- Modify: `database/migrations/0001_01_01_000000_create_users_table.php`
- Modify: `database/migrations/2026_08_01_000002_add_org_id_to_users_table.php`
- Modify: `database/migrations/2026_08_01_000001_create_organizations_table.php`
- Create: `database/migrations/2026_08_01_000003_create_credentials_table.php`

Clean slate: editar as migrations existentes in-place (banco dev é re-seedável, decisão Q6=B). `users` final: `id, name, email (unique), email_verified_at, cpf (nullable unique), timestamps`. `organizations` final: `id, name, host (nullable unique), landing_view (nullable), cnpj (nullable unique), logo_path, status, softDeletes, timestamps`. `credentials`: `id, user_id FK cascade, org_id FK restrict nullable, password, status enum active/inactive default active, rememberToken, timestamps, unique(user_id, org_id)`.

- [ ] Editar `0001_01_01_000000_create_users_table.php`: remover `password` e `rememberToken()` do `Schema::create('users')`.
- [ ] Editar `2026_08_01_000002_add_org_id_to_users_table.php`: vira migration que só adiciona `cpf` (sem org_id/status; docblock atualizado).
- [ ] Editar `2026_08_01_000001_create_organizations_table.php`: trocar `slug` por `host` (nullable unique) + `landing_view` (nullable).
- [ ] Criar `2026_08_01_000003_create_credentials_table.php` com a tabela descrita (unique composto).
- [ ] `vendor/bin/sail artisan migrate:fresh` — deve passar.
- [ ] Commit `feat(schema): credentials table + org host/landing_view, users vira pessoa`

### Task 2: Models + factories

**Files:**
- Modify: `app/Models/User.php`
- Modify: `app/Models/Organization.php`
- Create: `app/Models/Credential.php`
- Modify: `database/factories/UserFactory.php`
- Modify: `database/factories/OrganizationFactory.php`
- Create: `database/factories/CredentialFactory.php`

**Interfaces (produz):**
- `User::credentials(): HasMany<Credential>` ; `User::credentialFor(?Organization $org): ?Credential` (org null = admin)
- `Credential` fillable `['user_id','org_id','password','status','remember_token']`, cast `password => hashed`, `status => string`; scopes: `scopeForOrg(Builder, ?int $orgId)` → `where('org_id', $orgId)` (null admin incluído via `whereNull`).
- `Organization::memberships(): HasMany<Credential>` ; fillable `['name','host','landing_view','cnpj','logo_path','status']`.
- `UserFactory` states mantidos (`aluno()`, `gestor()`, `professor()`, `inactive()` → inactive aplica na credential), novo `forOrg(Organization $org)` que cria a credential da org após criar o user.
- `OrganizationFactory::definition()` ganha `host` único (`Str::slug(name).'.'.fake()->unique()->domainName()`) e `landing_view => null`.

`User::organization()` (belongsTo org_id) removida; `org_id`/`password`/`status` saem de fillable/casts; `sendPasswordResetNotification` fica. `gestor()`/`professor()` states criam org + credential.

- [ ] Escrever `Credential` + ajustar `User`/`Organization` + factories.
- [ ] Teste rápido: `vendor/bin/sail artisan tinker --execute 'App\Models\User::factory()->gestor()->create();'` — sem erro de coluna.
- [ ] Commit `feat(models): User vira pessoa, Credential vira conta por org`

### Task 3: OrgContext + middleware de resolução por host

**Files:**
- Create: `app/Services/OrgContext.php`
- Create: `app/Http/Middleware/ResolveOrgFromHost.php`
- Create: `app/Http/Middleware/EnsureTenantAccess.php`
- Modify: `bootstrap/app.php`

**Interfaces (produz):**
- `app(OrgContext::class)` singleton por request: `readonly ?Organization $organization`, `readonly bool $orgIsActive`; métodos `isStateZero(): bool`, `orgId(): ?int`.
- `OrgContext::stateZero()` estático para builds fora de request (console).
- Ordem no grupo `web`: `ResolveOrgFromHost` PRIMEIRO (prepend), `EnsureTenantAccess` logo depois.

`ResolveOrgFromHost`: `$host = Str::lower($request->getHost())` (getHost já vem sem porta); `Organization::where('host', $host)->first()` (SoftDeletes filtra deletadas); binda `app()->instance(OrgContext::class, new OrgContext($org, $org?->status === 'active'))`.

`EnsureTenantAccess` (regras da spec itens 6–7):

```php
public function handle(Request $request, Closure $next): Response
{
    $context = app(OrgContext::class);
    $user = $request->user();
    $isAdmin = $user?->hasRole(RolesEnum::ADMIN->value) ?? false;

    if ($context->isStateZero()) {
        if ($user && ! $isAdmin) {
            Auth::guard('web')->logout();

            return redirect()->route('login')->with('error', trans('auth.failed'));
        }

        if (! $user && ! $this->isAuthRoute($request)) {
            return redirect()->route('login');
        }

        return $next($request);
    }

    if (! $context->orgIsActive) {
        if ($user && ! $isAdmin) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect('/')->with('error', trans('auth.failed'));
        }

        if (! $user && ! $this->isPublicOnInactiveOrg($request)) {
            return redirect('/');
        }

        return $next($request);
    }

    return $next($request);
}
```

`isAuthRoute`: path começa com `login`, `forgot-password`, `reset-password` OU é `logout`. `isPublicOnInactiveOrg`: path `/` OU `validar-certificado*` OU rotas de auth OU `logout` (POST de login/convite em org inativa falha mais adiante, no provider/controller — não aqui, para manter a falha genérica).

Registro em `bootstrap/app.php`:

```php
$middleware->prependToGroup('web', ResolveOrgFromHost::class);
$middleware->appendToGroup('web', EnsureTenantAccess::class);
```

- [ ] Criar os 3 arquivos + registro.
- [ ] Teste: `tests/Feature/Tenancy/HostResolutionTest.php` (criar com `make:test --phpunit`): host mapeado resolve org; host desconhecido = state0; porta é ignorada; `GET /` em state0 redireciona login; não-admin logado em state0 é deslogado.
- [ ] Rodar o teste novo; ver falhar antes, passar depois.
- [ ] Commit `feat(tenancy): resolve org por HTTP_HOST com OrgContext por request`

### Task 4: OrgScope + ResolvesOrgContext — nova cadeia

**Files:**
- Modify: `app/Models/Traits/OrgScope.php`
- Modify: `app/Http/Controllers/Concerns/ResolvesOrgContext.php`

Cadeia (spec item 4), nos dois pontos:

```php
// leitura (global scope)
if ($user->hasRole(RolesEnum::ADMIN->value)) {
    $activeOrgId = session('active_org_id');
    if ($activeOrgId) {
        $builder->where($builder->getModel()->getTable().'.org_id', $activeOrgId);
    }

    return;
}

$contextOrgId = app(OrgContext::class)->orgId();

$builder->where($builder->getModel()->getTable().'.org_id', $contextOrgId ?? 0);
```

```php
// escrita (creating) + resolveOrgId()
$resolvedOrgId = $user->hasRole(RolesEnum::ADMIN->value)
    ? session('active_org_id')
    : app(OrgContext::class)->orgId();

if (! $resolvedOrgId) {
    throw new UnresolvedOrgContextException('…sem organização resolvida (impersonação admin ou host).');
}
```

`where org_id = 0` substitui `whereRaw('1 = 0')` (org 0 nunca existe; índice usa o valor). Not-admin sem host (state0) → filtrado pra vazio, e o gating do Task 3 já deslogou — caminho defensivo.

- [ ] Editar os dois arquivos.
- [ ] Atualizar `tests/Feature/OrgScopeUnresolvedContextTest.php` (admin sem impersonação criando → exception continua; gestor cria na org do host).
- [ ] Commit `refactor(tenancy): contexto de org vem do host, impersonação só pro admin`

### Task 5: OrgCredentialUserProvider + auth config

**Files:**
- Create: `app/Services/OrgCredentialUserProvider.php`
- Modify: `config/auth.php` (provider `users.driver` → `org-credential`)
- Modify: `app/Providers/AppServiceProvider.php`

```php
final class OrgCredentialUserProvider extends EloquentUserProvider
{
    // Busca SEMPRE só por email — status/senha vivem em `credentials`
    // e são checados em validateCredentials() contra a org do host.
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        if (! isset($credentials['email']) || ! is_string($credentials['email'])) {
            return null;
        }

        return $this->newModelQuery()->where('email', $credentials['email'])->first();
    }

    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        if (! isset($credentials['password']) || ! is_string($credentials['password'])) {
            return false;
        }

        $credential = Credential::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('org_id', app(OrgContext::class)->orgId())
            ->first();

        if (! $credential || $credential->status !== 'active') {
            return false;
        }

        if (Hash::check($credentials['password'], $credential->password)) {
            $this->rehashCredentialIfRequired($credential, $credentials['password']);

            return true;
        }

        return false;
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?Authenticatable
    {
        $user = $this->newModelQuery()->find($identifier);

        if (! $user) {
            return null;
        }

        return Credential::query()
            ->where('user_id', $identifier)
            ->where('org_id', app(OrgContext::class)->orgId())
            ->where('remember_token', $token)
            ->exists() ? $user : null;
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token): void
    {
        Credential::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('org_id', app(OrgContext::class)->orgId())
            ->update(['remember_token' => $token]);
    }

    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false): void
    {
        $credential = Credential::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('org_id', app(OrgContext::class)->orgId())
            ->first();

        if ($credential && ($force || Hash::needsRehash($credential->password))) {
            $credential->forceFill(['password' => Hash::make($credentials['password'])])->save();
        }
    }
}
```

Registro (`AppServiceProvider::register`):

```php
Auth::provider('org-credential', function (Application $app, array $config): OrgCredentialUserProvider {
    return new OrgCredentialUserProvider($app['hash'], $config['model']);
});
```

- [ ] Criar provider + registro + config.
- [ ] Teste `tests/Feature/Auth/HostScopedLoginTest.php`: aluno da org A loga no host A ✓; mesma senha no host B falha `auth.failed`; gestor da org B tentando host A falha genérico; credential inactive falha; state0 só credential org null (admin) loga.
- [ ] Commit `feat(auth): senha por org via OrgCredentialUserProvider`

### Task 6: LoginRequest + logout org-aware

**Files:**
- Modify: `app/Http/Requests/Auth/LoginRequest.php`
- Modify: `app/Http/Controllers/Auth/AuthenticatedSessionController.php`

`authenticate()`: `Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))` (status tratado no provider). `throttleKey()`: `Str::lower(email).'|'.(app(OrgContext::class)->orgId() ?? 'global').'|'.$this->ip()`. `destroy()`: redirect passa a `redirect('/')` mantido (landing da org, ou login em state0 via gating) — intocado exceto comentário.

- [ ] Ajustar + rodar teste do Task 5 de novo (rate limit por org: 5 tentativas host A não bloqueiam host B — adicionar teste).
- [ ] Commit `feat(auth): throttle de login por org`

### Task 7: Reset de senha host-aware

**Files:**
- Modify: `app/Http/Controllers/Auth/PasswordResetLinkController.php`
- Modify: `app/Http/Controllers/Auth/NewPasswordController.php`
- Modify: `tests/Feature/Auth/PasswordResetTest.php` (ajustar para host/credential)

Regras: envio de link exige `credential(user, org-do-host)` existente (org null p/ admin em state0); org inativa → resposta idêntica a email inexistente (`Password::INVALID_USER` genérico). Callback do reset troca a senha da credential da org do host + `remember_token` novo + event.

```php
// PasswordResetLinkController::store (após validate)
$context = app(OrgContext::class);

if ($context->organization && ! $context->orgIsActive) {
    return back()->withInput($request->only('email'))
        ->withErrors(['email' => trans(Password::INVALID_USER)]);
}

$user = User::where('email', $request->string('email'))->first();

if (! $user || ! Credential::query()->forOrg($context->orgId())->where('user_id', $user->id)->exists()) {
    return back()->withInput($request->only('email'))
        ->withErrors(['email' => trans(Password::INVALID_USER)]);
}
```

```php
// NewPasswordController::store — callback
function (User $user) use ($request): void {
    $credential = Credential::query()
        ->forOrg(app(OrgContext::class)->orgId())
        ->where('user_id', $user->id)
        ->firstOrFail();

    $credential->forceFill([
        'password' => Hash::make($request->string('password')),
        'remember_token' => Str::random(60),
    ])->save();

    event(new PasswordReset($user));
}
```

Link do email: `resetUrl()` do parent resolve `password.reset` com o host do request no envio (mail síncrono) — host da org. Teste: senha trocada vale só na org do host; org B mantém senha antiga.

- [ ] Implementar + testes (reset da org A não vaza pra org B).
- [ ] Commit `feat(auth): reset de senha por credential da org do host`

### Task 8: Landing por org + fallback

**Files:**
- Modify: `app/Http/Controllers/LandingPageController.php`
- Create: `resources/views/tenants/ligacerto/landing.blade.php`
- Create: `resources/views/tenants/informatica/landing.blade.php`
- Modify: `resources/views/landing/show.blade.php` → vira base genérica? Não — arquivo antigo removido na Task 15 (seeders); por ora mantém como fallback inatingível.

```php
public function show(): View|RedirectResponse
{
    $context = app(OrgContext::class);

    if ($context->isStateZero()) {
        return redirect()->route('login');
    }

    $org = $context->organization;
    $view = $org->landing_view !== null && $org->landing_view !== ''
        ? 'tenants.'.$org->landing_view.'.landing'
        : null;

    if ($view === null || ! View::exists($view)) {
        return redirect()->route('login');
    }

    return view($view, ['organization' => $org]);
}
```

Blades demo: estrutura leve (header com `organization.name` + `logo_path`, hero com CTA `route('login')`, footer com `route('certificates.verify')`), 1 por org. `EnsureTenantAccess` já deixa landing passar em org inativa (path `/`).

- [ ] Controller + 2 blades demo.
- [ ] Teste `tests/Feature/Tenancy/LandingTest.php`: host com `landing_view` renderiza blade certo; sem `landing_view` → redirect login; state0 → redirect login; org inativa → landing 200.
- [ ] Commit `feat(landing): landing por org via landing_view, fallback login`

### Task 9: Shell — identidade da org no layout

**Files:**
- Modify: `resources/views/layouts/app.blade.php` (marca/branding do header/sidebar)
- Modify: `app/View/Composers/OrgIdentityComposer.php` (criar) ou `View::share` no `AppServiceProvider` — usar composer registrado via `View::composer('layouts.*', …)`.

Produz: `$orgIdentity = ['name' => ..., 'logoPath' => ...]` — não-admin: `app(OrgContext::class)->organization` (name + logo_path); admin: `system_name` (SettingService global, fallback `config('app.name')`) + logo global setting `logo_path`. Blindar: state0/console → `config('app.name')`.

- [ ] Composer + ajuste do layout (texto do brand + `<img>` condicional).
- [ ] Dusk check manual: brand muda entre hosts.
- [ ] Commit `feat(shell): header/sidebar usa nome+logo da org do host`

### Task 10: Org CRUD — host + landing_view

**Files:**
- Modify: `app/Http/Requests/StoreOrganizationRequest.php`
- Modify: `app/Http/Requests/UpdateOrganizationRequest.php`
- Modify: `app/Http/Controllers/OrganizationController.php` (remover `resolveSlug`)
- Modify: `resources/views/organizations/create.blade.php`, `resources/views/organizations/edit.blade.php`, `resources/views/organizations/index.blade.php`
- Test: `tests/Feature/OrganizationCrudTest.php` (existentes ajustados)

Regras: `host` → `nullable|string|max:253|regex:/^[a-z0-9.-]+$/|unique:organizations,host` (+`ignore` no update); `landing_view` → `nullable|string|max:100`; sem slug. Controller: remover `resolveSlug()` e `$data['slug']`.

- [ ] Requests + controller + views (campo Host com hint "ex.: plataforma.suaorg.com" e campo Blade da Landing com hint "nome da view em resources/views/tenants/{view}/landing.blade.php").
- [ ] Rodar `OrganizationCrudTest` ajustado.
- [ ] Commit `feat(orgs): host + landing_view no CRUD admin`

### Task 11: Fluxos públicos host-scoped (convite + certificado + CSV)

**Files:**
- Modify: `app/Http/Controllers/InvitationController.php` (ou equivalente do convite — ler na execução)
- Modify: `app/Services/ProcessSmartInvitationAction.php`
- Modify: `app/Http/Controllers/PublicCertificateController.php`
- Modify: `app/Services/UserImportService.php`

**Convite:**
- `InvitationLink` resolve org própria (`$link->org_id`) ≠ `app(OrgContext::class)->orgId()` → `InvitationLinkInvalidException` (not_found) — host errado nunca revela o link.
- `check-email`: base muda de "user existe" para `Credential::forOrg(context)->where('user_id', …)->exists()` — form adaptativo (só senha) quando a credential JÁ existe NESTA org.
- Store (form completo): user novo → cria `User` + `Credential` (senha do form); user existente sem credential nesta org → cria `Credential` com a senha informada; com credential → fluxo só-senha valida a credential. Matrícula `course_user` segue igual.

**Certificado:** em `PublicCertificateController::show`, após resolver `$certificate`: `if ($certificate->course->org_id !== app(OrgContext::class)->orgId()) abort(404);` (lookup da org por certificado: usar `withoutGlobalScopes()` como hoje e comparar ids).

**CSV:** em `UserImportService::importChunk` — user existente: garantir credential da org (`firstOrCreate(['user_id' => …, 'org_id' => $orgId], ['password' => Hash::make(Str::random(32)), 'status' => 'active'])`); user novo: criar `User` (sem password) + `Credential` random + role aluno.

- [ ] Implementar os três + testes: convite no host errado 404; CSV em 2 orgs cria 2 credentials mesma pessoa; certificado do host certo 200, host errado 404.
- [ ] Commit `feat(public): convite/certificado/csv host-scoped com credentials`

### Task 12: CRUDs de usuário com credentials

**Files (ler na execução e ajustar):**
- `app/Http/Controllers/UserController.php` + `GestorStudentController.php` + `GestorProfessorController.php` + `UserAdminController.php` + `PasswordController.php` (perfil)
- `app/Http/Requests/StoreUserRequest.php`, `UpdateUserRequest.php`, `UpdateUserAdminRequest.php`, equivalents
- Views `users/*`, `gestor/students/*`, `gestor/professors/*`, `admin/users/*`

Contratos:
- Criar aluno/professor (gestor, org do host): cria `User` (name, email, cpf) + `Credential` da org com a senha do form. Se email global já existe (pessoa em outra org): reusar `User`, criar `Credential` nova para ESTA org (senha do form) — nunca tocar credential de outra org.
- Edição (gestor): troca de senha altera a credential da org do host; dados pessoais alteram o `User`.
- `admin.users`: tela global mostra memberships (`credentials` com org) por usuário; ação "ativar/desativar" passa a operar por membership (credential.status); deletar user continua com guardas existentes (certificados/invites). `org_id` do formulário vira lista de memberships (mínimo: mostrar orgs + status por org).
- `status=inactive` de user (CSV import states, factories) → credential.
- `Auth::logoutOtherDevices` (PasswordController) usa senha do user — reimplementar para credential do host (ou remover o chamada se depender de `users.password`; decidir na execução com teste).

- [ ] Ajustar controllers/requests/views mínimos para o app funcionar completo.
- [ ] Rodar suítes `UserCrudTest`, `MultiTenantStudentImportTest`, `GestorProfessor*`.
- [ ] Commit `feat(users): contas por org (credentials) em todos os CRUDs`

### Task 13: Impersonate Org — precedência + telas admin globais

**Files:**
- Modify: `app/Services/Navigation/ImpersonationContext.php` (só leitura — conferir que admin sem impersonação em host de org não "herda" a org do host nas telas admin.*)
- Modify: views de impersonação se necessário

Regra: telas `admin.*` NUNCA leem a org do host — só `session('active_org_id')` (já garantido pela Task 4 nos traits). Verificar `ImpersonationContext::activeOrganization()` não cai no host. Banner de "você está impersonando X" mantido.

- [ ] Conferir/ajustar + teste: admin logado no host A sem impersonação cria curso → `UnresolvedOrgContextException` (302 com erro); com impersonação da org B via host A → curso criado na org B.
- [ ] Commit `test(admin): impersonação prevalece sobre host`

### Task 14: Infra — hosts, env, CI

**Files:**
- Create: `scripts/setup-hosts.sh`
- Modify: `.env.example` (`APP_URL=http://localhost.ligacerto:8080`)
- Modify: `.github/workflows/ci.yml` (job Dusk: adicionar entradas em `/etc/hosts` antes de subir)
- Modify: `README.md` (seção "Tenancy por host" — como rodar: `./scripts/setup-hosts.sh`, hosts de teste, estado 0 em `127.0.0.1:8080`)

```bash
#!/usr/bin/env bash
# Registra os hosts de desenvolvimento das organizações demo.
set -euo pipefail

entries=(
  "127.0.0.1 localhost.ligacerto"
  "127.0.0.1 localhost.informatica"
)

for entry in "${entries[@]}"; do
  host="${entry#* }"
  if grep -qE "^[[:space:]]*[^#]*[[:space:]]${host}([[:space:]]|$)" /etc/hosts; then
    echo "OK: ${host} já presente em /etc/hosts"
  else
    echo "${entry}" | sudo tee -a /etc/hosts > /dev/null
    echo "Adicionado: ${entry}"
  fi
done
```

CI Dusk (antes do `php artisan serve`): `sudo echo "127.0.0.1 localhost.ligacerto" | sudo tee -a /etc/hosts && sudo echo "127.0.0.1 localhost.informatica" | sudo tee -a /etc/hosts`.

- [ ] Script + env + CI + README.
- [ ] Commit `chore(infra): hosts de tenancy para dev e CI`

### Task 15: Seeders + blades demo

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: seeders de org/usuários/settings (ler na execução)
- Create: `resources/views/tenants/ligacerto/landing.blade.php` (se não criada na Task 8) e `informatica`

Seed final: 1 admin (`admin@plataforma.test` + credential org null + role admin), orgs **LigaCerto** (`host=localhost.ligacerto`, `landing_view=ligacerto`) e **Informática+** (`host=localhost.informatica`, `landing_view=informatica`), 1 gestor + alunos + cursos por org, settings globais (`system_name`, smtp log, etc.). Tudo idempotente (`firstOrCreate`).

- [ ] Seeders + `migrate:fresh --seed` limpo.
- [ ] Commit `feat(seeders): estado 0 + 2 orgs demo com hosts e landings`

### Task 16: Helpers de teste + fallout + suítes novas

**Files:**
- Modify: `tests/TestCase.php`
- Modify: todos os testes quebrados (91 Feature + 37 Dusk + 39 Unit)
- Create: suítes novas em `tests/Feature/Tenancy/*`

`tests/TestCase.php` final:

```php
protected function actingAsAdmin(?Organization $impersonatedOrg = null): User
{
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->assignRole(RolesEnum::ADMIN->value);
    Credential::factory()->admin()->create(['user_id' => $admin->id]);

    $this->actingAs($admin);
    $this->withServerVariables(['HTTP_HOST' => 'localhost.admin']); // host de teste neutro (não mapeado = estado 0)

    if ($impersonatedOrg) {
        $this->withSession(['active_org_id' => $impersonatedOrg->id]);
    }

    return $admin;
}

protected function actingAsOrgUser(?Organization $organization = null, string $role = 'gestor'): User
{
    $organization ??= Organization::factory()->create();

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole($role);
    Credential::factory()->create(['user_id' => $user->id, 'org_id' => $organization->id]);

    $this->actingAs($user);
    $this->withServerVariables(['HTTP_HOST' => $organization->host]);

    return $user;
}
```

Constante `TEST_ADMIN_HOST = 'localhost.admin'` para estado 0 em testes Feature (hosts não precisam de DNS real em requests internos). Dusk: `visitHost(string $host, string $path)` helper em `tests/Browser/Pages/Page.php` que navega `http://{host}:8080{path}` (hosts reais via `/etc/hosts`).

Estratégia de fallout: rodar `vendor/bin/sail artisan test --compact`, mapear falhas por domínio (auth/orgs/cursos/fórum/quiz/certificados/dashboard/landing), corrigir por área — padrões: user factory sem `org_id`/`password`/`status` → criar credential; `actingAsOrgUser` já cobre host; Dusk logins precisam de host correto.

Suítes novas: `HostResolutionTest`, `HostScopedLoginTest`, `PasswordResetHostTest`, `LandingTest`, `StateZeroTest`, `InactiveOrgTest`, `CertificateHostScopeTest`, `CsvCredentialsTest` (Task 3/5/7/8/11 já criam; aqui consolidar gaps).

- [x] Helpers + fallout completo + suítes novas.
- [x] `vendor/bin/sail artisan test --compact` verde; `vendor/bin/sail bin pint --dirty --format=agent`.
- [x] Commit(s) `test: adapta suíte ao paradigma host-based`

## Self-Review

- Spec → tasks: 1↔T1/T2, 2↔T1/T2, 3↔T2, 4↔T3/T4/T13, 5↔T5/T6, 6↔T3, 7↔T3, 8↔T8, 9↔T9, 10↔T11, 11↔T7/T11, 12↔T14, 13↔T15, 14↔T16. Fechado.
- Tipos consistentes: `OrgContext::orgId(): ?int` usado por provider/scope/traits/request; `Credential::scopeForOrg(Builder, ?int)` em todos.
- Riscos declarados: `logoutOtherDevices` (T12), `AuthenticateSession` middleware compara `password` do user — **precisa** override (hash na credential): tratar em T5 (`Authenticatable::getAuthPasswordName()`? Laravel 13 usa `getAuthPassword()`/`getAuthPasswordName()` — `AuthenticateSession` compara `user->{$passwordName}`; sobrescrever em `User::getAuthPassword(): ?string { return $this->credentialFor(app(OrgContext::class))->password ?? null; }` + `getAuthPasswordName(): string { return 'password'; }`? `getAuthPassword` não existe mais em L13? Verificar na execução e ajustar). Views do admin.users com memberships podem sair mínimas (listagem simples) — UX rica fica pra depois.
