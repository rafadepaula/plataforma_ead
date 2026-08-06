# BUG-001: `GET /login` enquanto autenticado redireciona para a Landing Page pública (`/`) em vez da home do role

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
- **Route Name / URL:** `login` (`GET /login`)
- **Route Registration:** [routes/auth.php:10-13](file:///home/rafael/projects/cursos/plataforma_ead/routes/auth.php#L10-L13) — envolvido em `Route::middleware('guest')`.
- **Middleware Alias Ausente:** [bootstrap/app.php:30-40](file:///home/rafael/projects/cursos/plataforma_ead/bootstrap/app.php#L30-L40) — registra apenas `role`, `permission`, `role_or_permission`, `student.enrolled`. **Não** registra alias `guest` e **não** aponta para nenhum `App\Http\Middleware\RedirectIfAuthenticated` customizado.
- **Middleware Conflitado:** `app/Http/Middleware/RedirectIfAuthenticated.php` **não existe** no projeto (confirmado via `find`). Logo o Laravel resolve `'guest'` para o alias default do framework (`Illuminate\Auth\Middleware\RedirectIfAuthenticated`), cujo `redirectTo()` padrão retorna `Route::has('home') ? route('home') : '/home'` (fallback final `/`). Como não há rota `home` nem `dashboard` nomeada (a rota existente chama-se `admin.dashboard`, não `dashboard`), e `LandingPageController` está registrada em `/`, o fallback cai em `/`.
- **Controller / Action:** `App\Http\Controllers\Auth\AuthenticatedSessionController::create` ([AuthenticatedSessionController.php:23-28](file:///home/rafael/projects/cursos/plataforma_ead/app/Http/Controllers/Auth/AuthenticatedSessionController.php#L23-L28)) — apenas renderiza `view('auth.login')`. Nunca é alcançado por usuário autenticado porque o middleware `guest` intercepta antes.
- **Lógica de Redirect Correta (já existe, mas privada):** `AuthenticatedSessionController::redirectPathFor()` ([AuthenticatedSessionController.php:54-61](file:///home/rafael/projects/cursos/plataforma_ead/app/Http/Controllers/Auth/AuthenticatedSessionController.php#L54-L61)) — retorna `route('admin.dashboard')` para admin/gestor e `route('student.courses.index')` para aluno, com fallback defensivo `/` se as rotas não existirem. **É privada e só é usada por `store()`, nunca pelo middleware `guest`.**
- **Logout (impacto secundário, não parte deste bug):** `AuthenticatedSessionController::destroy()` ([AuthenticatedSessionController.php:43](file:///home/rafael/projects/cursos/plataforma_ead/app/Http/Controllers/Auth/AuthenticatedSessionController.php#L43)) retorna `redirect('/')` de propósito (logout deve voltar para a vitrine). **Não alterar** neste fix.
- **Roles Enum:** [app/Enums/Permissions/RolesEnum.php](file:///home/rafael/projects/cursos/plataforma_ead/app/Enums/Permissions/RolesEnum.php) — valores `admin`, `gestor`, `aluno`.
- **Blade View:** `resources/views/auth/login.blade.php` (não está em causa; é só o destino do `create()` quando não autenticado).

## 4. Root Cause Technical Analysis
- **Failure Branch:** `routes/auth.php:10` registra `Route::get('login', ...)->middleware('guest')`. Em `bootstrap/app.php` o alias `'guest'` **não é sobrescrito**, então o Laravel cai no `Illuminate\Auth\Middleware\RedirectIfAuthenticated` default. Esse middleware default, ao detectar `Auth::check() === true`, retorna `redirect()->guest($this->redirectTo($request))`, e seu `redirectTo()` default avalia:
  ```php
  return Route::has('home') ? route('home') : config('app.url', '/home');
  // sem rota 'home' e sem fallback explícito => cai em '/'
  ```
  O projeto **não tem** rota nomeada `home` nem `dashboard` (tem `admin.dashboard` + `student.courses.index`), e o `/` é a Landing Page pública. Resultado: 302 para `/`.
- **Por que `store()` funciona corretamente:** `store()` chama explicitamente `redirect()->intended($this->redirectPathFor($user))`, que (a) honra `url.intended` na sessão se houver, e (b) usa fallback role-aware. O `GET /login` (interceptado pelo middleware) **não** passa por essa lógica.
- **Teste que cristaliza o bug:** [tests/Feature/Auth/LoginTest.php:19-25](file:///home/rafael/projects/cursos/plataforma_ead/tests/Feature/Auth/LoginTest.php#L19-L25):
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
- [ ] Existe `app/Http/Middleware/RedirectIfAuthenticated.php` (criado via `vendor/bin/sail artisan make:middleware`).
- [ ] `bootstrap/app.php` registra alias `'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class` no array `$middleware->alias([...])`.
- [ ] `AuthenticatedSessionController::redirectPathFor()` foi extraído para um local reutilizável (service/trait/method público) e **ambos** `store()` e o novo middleware chamam a mesma fonte de verdade (sem drift).
- [ ] `GET /login` autenticado **sem** `url.intended` redireciona para a home do role (admin/gestor → `/admin/dashboard`, aluno → `/meus-cursos`).
- [ ] `GET /login` autenticado **com** `url.intended` na sessão redireciona para a URL intended.
- [ ] Logout (`POST /logout`) **continua** retornando `redirect('/')` (não regressar).
- [ ] `vendor/bin/sail artisan test --compact --filter=LoginTest` passa (Feature + Dusk), incluindo o teste atualizado em `tests/Feature/Auth/LoginTest.php:19`.
- [ ] `vendor/bin/sail artisan test --compact tests/Feature/Auth/LoginTest.php` e `tests/Browser/Auth/LoginTest.php` verdes.
- [ ] Rodar `vendor/bin/sail artisan test --compact` completo após fix para confirmar ausência de regressões (middleware `guest` é usado em poucas rotas — `login`/`forgot-password`/`reset-password` — confirmar que essas continuam acessíveis a guests).
- [ ] Executar `vendor/bin/sail bin pint --dirty --format agent` para conformidade de estilo.

## Resolution Status
- **Status:** RESOLVED
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
