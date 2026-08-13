# BUG-001: `GET /login` enquanto autenticado redireciona para a Landing Page pública (`/`) em vez da home do role

> **Revalidado em 2026-08-13 (pós-migração Bootstrap 5.3, commit 3088d99):** JÁ CORRIGIDO — o alias `guest` aponta para `App\Http\Middleware\RedirectIfAuthenticated` ([bootstrap/app.php:47](file:///home/rafael/projects/cursos/plataforma_ead/bootstrap/app.php#L47)), que redireciona via `redirect()->intended($home)` com `UserHomeResolver` ([RedirectIfAuthenticated.php:40-44](file:///home/rafael/projects/cursos/plataforma_ead/app/Http/Middleware/RedirectIfAuthenticated.php#L40-L44)). A migração Bootstrap não tocou em rotas/middlewares e não reintroduziu o defeito: `vendor/bin/sail artisan test --compact tests/Feature/Auth/LoginTest.php` → 12 passed / 35 assertions em 2026-08-13.

## 1. Executive Summary & Impact
- **ID:** BUG-001
- **Severity:** High
- **Affected Role(s):** Admin | Gestor | Aluno (qualquer usuário autenticado)
- **Tenant Context:** Agnóstico (admin-global, gestor com `org_id`, aluno com/sem `org_id`) — repro em todos os tenants.
- **Summary:** Ao acessar `GET /login` estando já autenticado, o middleware `guest` redireciona o usuário para `/` (a Landing Page pública do SPEC-11, servida por `LandingPageController::show`). O comportamento esperado é redirecionar para a página principal do role (admin/gestor → `route('admin.dashboard')`, aluno → `route('student.courses.index')`), respeitando a URL `intended` caso exista na sessão (e.g. usuário capturado por um middleware `role:` e mandado pro login). O impacto é que qualquer usuário que digite manualmente `/login` ou clique num link/redirect antigo para `/login` "se perde" da área autenticada, voltando para a vitrine pública.

## 2. Step-by-Step Reproduction Guide
### Pre-conditions:
1. Banco de dados rodando (Sail up) com migrações aplicadas.
2. Pelo menos um usuário ativo para cada role:
   - Admin (`role=admin`, `org_id=null`)
   - Gestor (`role=gestor`, com `org_id` válido)
   - Aluno (`role=aluno`, com/sem `org_id` — `aluno` não exige `org_id` até a matrícula)
3. O usuário está **autenticado** (sessão ativa / cookie de sessão válido).

### Reproduction Steps (qualquer role, mas documentado aqui para Aluno):
1. Fazer login como `aluno` via `POST /login` (ou estar já logado de sessão anterior).
2. Abrir uma nova aba ou digitar manualmente a URL: `http://localhost/login` (ou clicar em qualquer link stale para `/login`).
3. Observar o redirecionamento 302.

### Reprodução equivalente via tinker/HTTP:
```bash
# Aluno
Acting as aluno → GET /login → 302 Location: /        ← BUG
# Gestor
Acting as gestor → GET /login → 302 Location: /       ← BUG
# Admin
Acting as admin → GET /login → 302 Location: /        ← BUG
```

### Expected Behavior (Happy Path):
- Sem `url.intended` na sessão:
  - Admin/Gestor → `302 Location: route('admin.dashboard')` (`/admin/dashboard`)
  - Aluno → `302 Location: route('student.courses.index')` (`/meus-cursos`)
- **Com** `url.intended` na sessão (ex.: um `redirect()->guest('login')` vindo de um middleware `role:admin|gestor`):
  - Redirecionar para a URL intended salva, **não** para a home do role.
- Esta lógica já existe em `AuthenticatedSessionController::store()` via `redirect()->intended($this->redirectPathFor($user))` — o bug é apenas o `GET /login` não usar a mesma.

### Actual Behavior (Bug):
- `GET /login` autenticado **sempre** retorna `302 Location: /`, servindo a Landing Page pública. Confirme para todos os roles:
  - Admin → `/`
  - Gestor → `/`
  - Aluno → `/`

## 3. Codebase & Architectural Mapping

**Estado ATUAL verificado (2026-08-13, pós-Bootstrap 5.3):**
- **Route Name / URL:** `login` (`GET /login`) — confirmado em `vendor/bin/sail artisan route:list --except-vendor`: `GET|HEAD login → login › Auth\AuthenticatedSessionController@create`.
- **Route Registration:** [routes/auth.php:10-12](file:///home/rafael/projects/cursos/plataforma_ead/routes/auth.php#L10-L12) — ainda dentro de `Route::middleware('guest')->group(...)` ([routes/auth.php:9](file:///home/rafael/projects/cursos/plataforma_ead/routes/auth.php#L9)). Inalterado pela migração.
- **Middleware Alias (corrigido):** [bootstrap/app.php:47](file:///home/rafael/projects/cursos/plataforma_ead/bootstrap/app.php#L47) — `'guest' => RedirectIfAuthenticated::class`, com o comentário `// BUG-001 — custom role-aware guest redirect`.
- **Middleware Customizado (existe):** [app/Http/Middleware/RedirectIfAuthenticated.php:25-47](file:///home/rafael/projects/cursos/plataforma_ead/app/Http/Middleware/RedirectIfAuthenticated.php#L25-L47) — resolve o usuário pelos guards (fallback `web`, linha 36) e retorna `redirect()->intended($home)` (linha 43), honrando `url.intended`.
- **Fonte de verdade do destino:** [app/Services/UserHomeResolver.php:20-27](file:///home/rafael/projects/cursos/plataforma_ead/app/Services/UserHomeResolver.php#L20-L27) — admin/gestor → `route('admin.dashboard')`, demais → `route('student.courses.index')`, com fallback `/` guardado por `Route::has()`.
- **Controller / Action:** `AuthenticatedSessionController::create` ([AuthenticatedSessionController.php:21-24](file:///home/rafael/projects/cursos/plataforma_ead/app/Http/Controllers/Auth/AuthenticatedSessionController.php#L21-L24)) — renderiza `view('auth.login')` apenas para guests.
- **Mesma fonte de verdade no `store()`:** [AuthenticatedSessionController.php:35-37](file:///home/rafael/projects/cursos/plataforma_ead/app/Http/Controllers/Auth/AuthenticatedSessionController.php#L35-L37) — `app(UserHomeResolver::class)->resolve(...)` + `redirect()->intended($home)`. Sem drift entre login POST e o guard do GET.
- **Logout (preservado):** [AuthenticatedSessionController.php:54](file:///home/rafael/projects/cursos/plataforma_ead/app/Http/Controllers/Auth/AuthenticatedSessionController.php#L54) — segue `redirect('/')` (mais `session()->forget('active_org_id')` na linha 49).
- **Blade View:** `resources/views/auth/login.blade.php` — reescrita para Bootstrap 5.3 na migração, mas irrelevante para este bug (nunca é alcançada por usuário autenticado).

**Estado no momento do report original (histórico, não mais verdadeiro):**
- `bootstrap/app.php` registrava apenas `role`, `permission`, `role_or_permission`, `student.enrolled`; sem alias `guest`.
- `app/Http/Middleware/RedirectIfAuthenticated.php` não existia, então o Laravel resolvia `'guest'` para `Illuminate\Auth\Middleware\RedirectIfAuthenticated`, cujo `redirectTo()` cai em `/` na ausência de rota `home`/`dashboard`.
- A lógica role-aware existia apenas em `AuthenticatedSessionController::redirectPathFor()`, privada e usada só por `store()`.
- **Roles Enum:** [app/Enums/Permissions/RolesEnum.php](file:///home/rafael/projects/cursos/plataforma_ead/app/Enums/Permissions/RolesEnum.php) — valores `admin`, `gestor`, `aluno`.
- **Blade View:** `resources/views/auth/login.blade.php` (não está em causa; é só o destino do `create()` quando não autenticado).

## 4. Root Cause Technical Analysis

> **Revalidação 2026-08-13:** a causa raiz abaixo é **histórica** — foi eliminada pelo par
> [bootstrap/app.php:47](file:///home/rafael/projects/cursos/plataforma_ead/bootstrap/app.php#L47) +
> [RedirectIfAuthenticated.php:40-44](file:///home/rafael/projects/cursos/plataforma_ead/app/Http/Middleware/RedirectIfAuthenticated.php#L40-L44).
> A migração Bootstrap 5.3 (commit 3088d99) tocou apenas views/SCSS/JS e não reabriu o caminho de
> falha: o alias `guest` segue registrado e o teste que cristalizava o bug já afirma o comportamento
> correto ([tests/Feature/Auth/LoginTest.php:26](file:///home/rafael/projects/cursos/plataforma_ead/tests/Feature/Auth/LoginTest.php#L26) — `assertRedirect(route('admin.dashboard'))`).

- **Failure Branch (histórico):** `routes/auth.php:10` registra `Route::get('login', ...)->middleware('guest')`. Em `bootstrap/app.php` o alias `'guest'` **não é sobrescrito**, então o Laravel cai no `Illuminate\Auth\Middleware\RedirectIfAuthenticated` default. Esse middleware default, ao detectar `Auth::check() === true`, retorna `redirect()->guest($this->redirectTo($request))`, e seu `redirectTo()` default avalia:
  ```php
  return Route::has('home') ? route('home') : config('app.url', '/home');
  // sem rota 'home' e sem fallback explícito => cai em '/'
  ```
  O projeto **não tem** rota nomeada `home` nem `dashboard` (tem `admin.dashboard` + `student.courses.index`), e o `/` é a Landing Page pública. Resultado: 302 para `/`.
- **Por que `store()` funciona corretamente:** `store()` chama explicitamente `redirect()->intended($this->redirectPathFor($user))`, que (a) honra `url.intended` na sessão se houver, e (b) usa fallback role-aware. O `GET /login` (interceptado pelo middleware) **não** passa por essa lógica.
- **Teste que cristalizava o bug (já atualizado no fix):** hoje [tests/Feature/Auth/LoginTest.php:21-27](file:///home/rafael/projects/cursos/plataforma_ead/tests/Feature/Auth/LoginTest.php#L21-L27) afirma `assertRedirect(route('admin.dashboard'))`. A versão original, que afirmava o comportamento incorreto, era:
  ```php
  public function test_authenticated_admin_is_redirected_away_from_login_screen(): void
  {
      $admin = User::factory()->create(['org_id' => null]);
      $admin->assignRole(RolesEnum::ADMIN->value);

      $this->actingAs($admin)->get('/login')->assertRedirect('/');  // ← afirma o bug
  }
  ```
  Esse teste **afirma o comportamento atual incorreto** (`assertRedirect('/')`). Qualquer correção que mude o destino do `GET /login` autenticado fará esse teste falhar — ele **precisa ser atualizado** no mesmo fix.
- **Stack Trace / Log Evidence:** Não há erro/exception — é um redirect silencioso. `laravel-boost:last-error` não aplica. Para confirmar empiricamente, basta `curl -i http://localhost/login` com cookie de sessão ativo: `HTTP/1.1 302 Found` + `Location: /`.

## 5. Test Specification Plan (TDD Blueprint)

### Fix Strategy (resumo, não obrigatório neste report):
Criar `app/Http/Middleware/RedirectIfAuthenticated.php` (via `vendor/bin/sail artisan make:middleware RedirectIfAuthenticated --no-interaction`) que estenda ou replique o comportamento do `Illuminate\Auth\Middleware\RedirectIfAuthenticated` mas com `redirectTo()` retornando o **role-aware home** via uma lógica equivalente a `AuthenticatedSessionController::redirectPathFor()` (idealmente extraindo `redirectPathFor()` para um método público reutilizável, ou para um `ValueObject`/helper compartilhado, para evitar duplicação). Em `bootstrap/app.php`, registrar o alias `'guest'` apontando para essa classe customizada, e usar `redirect()->intended($roleHome)` para honrar `url.intended` quando presente.

> **Nota arquitetural:** preferível extrair `redirectPathFor(User $user): string` de `AuthenticatedSessionController` para um local reutilizável (ex.: `App\Services\Auth\RoleHomeResolver` ou trait), pois agora dois callers precisam da mesma lógica (`store()` + o novo `RedirectIfAuthenticated`). Evita drift entre os dois caminhos.

### Unit / Feature Test (PHPUnit) — `tests/Feature/Auth/LoginTest.php`
Atualizar o teste existente e adicionar cobertura por role + cenário `intended`:

- **Substituir** `test_authenticated_admin_is_redirected_away_from_login_screen` (linha 19) para afirmar o comportamento **correto**:
  ```php
  public function test_authenticated_admin_is_redirected_to_admin_dashboard_from_login(): void
  {
      $admin = User::factory()->create(['org_id' => null]);
      $admin->assignRole(RolesEnum::ADMIN->value);

      $this->actingAs($admin)
          ->get('/login')
          ->assertRedirect(route('admin.dashboard'));
  }
  ```

- **Adicionar** `test_authenticated_gestor_is_redirected_to_admin_dashboard_from_login`:
  - Setup: `User::factory()->gestor()->create()` (já atribui role + `org_id`).
  - Assert: `assertRedirect(route('admin.dashboard'))`.

- **Adicionar** `test_authenticated_aluno_is_redirected_to_student_courses_from_login`:
  - Setup: `User::factory()->aluno()->create()`.
  - Assert: `assertRedirect(route('student.courses.index'))`.

- **Adicionar** `test_authenticated_user_with_intended_url_is_redirected_to_intended_from_login`:
  - Setup: `$user = User::factory()->aluno()->create()`.
  - Simular intended: chamar `$this->actingAs($user)->withSession(['url.intended' => 'http://localhost/some-protected-route'])->get('/login')`.
  - Assert: `assertRedirect('http://localhost/some-protected-route')` — prova que o middleware honra `intended` quando existe.

### Browser Test (Laravel Dusk) — `tests/Browser/Auth/LoginTest.php`
Adicionar um teste E2E que cobre o fluxo do usuário real (acessa `/login` manualmente enquanto logado):

- **Adicionar** `test_authenticated_aluno_visiting_login_is_redirected_to_home`:
  ```php
  public function test_authenticated_aluno_visiting_login_is_redirected_to_home(): void
  {
      $user = User::factory()->create();
      $user->assignRole(RolesEnum::ALUNO->value);

      $this->browse(function (Browser $browser) use ($user): void {
          $browser->loginAs($user)
              ->visit('/login')
              ->waitForLocation('/meus-cursos')
              ->assertPathIs('/meus-cursos');
      });
  }
  ```
  - **Selectors:** não há interação com formulário; usa `loginAs()` + `visit('/login')` + `waitForLocation`. Sem seletores `dusk=` novos necessários.

## 6. Acceptance Criteria for Fix Verification
(reverificados em 2026-08-13 contra o código atual)

- [x] Existe `app/Http/Middleware/RedirectIfAuthenticated.php` — confirmado em `app/Http/Middleware/RedirectIfAuthenticated.php:16`.
- [x] `bootstrap/app.php` registra alias `'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class` — `bootstrap/app.php:47`.
- [x] A lógica de destino foi extraída para `App\Services\UserHomeResolver` (`app/Services/UserHomeResolver.php:20`) e **ambos** `AuthenticatedSessionController::store()` (`:35`) e o middleware (`:41`) a consomem — sem drift.
- [x] `GET /login` autenticado **sem** `url.intended` redireciona para a home do role — coberto por `tests/Feature/Auth/LoginTest.php:21,29,36` (admin/gestor → `/admin/dashboard`, aluno → `/meus-cursos`).
- [x] `GET /login` autenticado **com** `url.intended` na sessão redireciona para a URL intended — `tests/Feature/Auth/LoginTest.php:43-52`.
- [x] Logout (`POST /logout`) **continua** retornando `redirect('/')` — `AuthenticatedSessionController.php:54`, coberto por `tests/Feature/Auth/LoginTest.php:149-155`.
- [x] `vendor/bin/sail artisan test --compact tests/Feature/Auth/LoginTest.php` verde — **12 passed / 35 assertions** (execução de 2026-08-13, pós-migração Bootstrap).
- [ ] **Lacuna remanescente (não bloqueante):** não existe teste Dusk cobrindo "usuário autenticado visita `/login`". `tests/Browser/Auth/LoginTest.php` cobre login/logout/reset e o redirect **pós-login** (`:160`, `:180`), mas não o guard do `GET /login`. Não executado nesta revalidação (Dusk fora de escopo).
- [x] Rotas `guest` (`login`/`forgot-password`/`reset-password`) continuam acessíveis a guests — `test_login_screen_can_be_rendered` (`tests/Feature/Auth/LoginTest.php:16`) verde.

## Resolution Status
- **Status:** RESOLVED (revalidado em 2026-08-13, pós-migração Bootstrap 5.3 / commit 3088d99 — sem regressão)
- **Como provar:** `vendor/bin/sail artisan test --compact tests/Feature/Auth/LoginTest.php` → 12 passed, 35 assertions.
- **Reproduction Tests:** `tests/Feature/Auth/LoginTest.php`
  - `test_authenticated_admin_is_redirected_away_from_login_screen`
  - `test_authenticated_gestor_is_redirected_away_from_login_screen`
  - `test_authenticated_aluno_is_redirected_away_from_login_screen`
  - `test_authenticated_user_with_intended_url_is_redirected_to_intended_from_login`
- **Fixed In Files:**
  - `app/Services/UserHomeResolver.php` (created)
  - `app/Http/Middleware/RedirectIfAuthenticated.php` (created)
  - `bootstrap/app.php` (modified — registered `guest` alias)
  - `app/Http/Controllers/Auth/AuthenticatedSessionController.php` (modified — uses UserHomeResolver)
- **Impacto da migração Bootstrap 5.3:** nenhum. O commit 3088d99 alterou apenas views/SCSS/módulos JS; `routes/auth.php`, `bootstrap/app.php`, o middleware e o `UserHomeResolver` permanecem intactos. A view `auth/login.blade.php` foi reescrita em Bootstrap, mas só é renderizada para guests.
