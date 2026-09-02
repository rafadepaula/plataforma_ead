---
name: testing-conventions
description: Padrões de código, snippets, guardrails para suítes de teste PHPUnit, Laravel Dusk, cobertura de código.
---

# Testing Conventions (`testing-conventions`)

## Overview

Guia define padrões de código, convenções de escrita, guardrails para criar e manter testes automatizados no repositório Plataforma EAD.

---

## Convenções e Regras de Escrita

1. **Nomenclatura de Métodos de Teste**:
   - Usar snake_case prefixado com `test_` ou atributo `#[Test]`.
   - Nome descritivo expressando intenção de negócio (ex: `test_user_can_view_course_details()`).

2. **Asserções Robustas (Proibição de Asserções Fracas)**:
   - Proibido asserção genérica ou superficial tipo `assertSourceHas('Laravel')`.
   - Preferir asserção de estrutura HTML (`assertPresent('main')`, `assertSeeIn('h1', ...)`), título exato (`assertTitle(...)`), estado autenticado (`assertAuthenticatedAs($user)`).

3. **Isolamento de Banco de Dados**:
   - Teste Feature/Unit: usar `Illuminate\Foundation\Testing\RefreshDatabase`.
   - Teste Dusk E2E: **`Illuminate\Foundation\Testing\DatabaseTruncation` é padrão obrigatório**, declarado **uma única vez** na classe base `Tests\DuskTestCase` (com `$exceptTables = ['migrations', 'roles', 'permissions', 'role_has_permissions']` — os papéis do Spatie são semeados pela MIGRAÇÃO `create_permission_tables`, não por seeder, então truncá-los quebraria toda a suíte a partir do 2º teste com "There is no role named `admin` for guard `web`"). Classes em `tests/Browser/*` **não** declaram `DatabaseMigrations` nem repetem a trait. Migrações rodam uma vez por suíte; entre métodos só `TRUNCATE`.
   - `RefreshDatabase` segue **proibido** em Dusk: processo HTTP do Dusk e processo de teste rodam em conexões separadas, e transação do `RefreshDatabase` nunca é vista pelo servidor.
   - `DatabaseMigrations` em `tests/Browser/*` só aceitável com justificativa escrita no próprio arquivo (ex.: teste que altera schema em runtime). Sem justificativa, é regressão de desempenho.

---

## Agrupamento por Cadeia de Ciclo de Vida (Lifecycle Chaining) — Regra E2E

**Regra**: em `tests/Browser/*`, critério de agrupamento é **cadeia de ciclo de vida / jornada do usuário**, **não** módulo. Um método de teste Dusk cobre cadeia inteira (criar, editar, transicionar estado, excluir, verificar consequência), com asserção intermediária em cada etapa.

**O que muda em relação ao padrão anterior (um método por cenário atômico, um arquivo por módulo):**

| Antes (atômico / módulo exclusivo) | Agora (cadeia de ciclo de vida) |
| :--- | :--- |
| 1 método por ação (`test_gestor_can_create_user`, `test_gestor_can_edit_user`, ...) | 1 método por cadeia (`test_gestor_user_management_full_lifecycle`) |
| Cada método re-migra o banco, reabre navegador, refaz login, renavega | 1 truncate, 1 login, 1 navegação, N etapas encadeadas |
| Arquivo de teste pertence a um único módulo | Arquivo pertence à **jornada**; pode cruzar módulos (ex.: cadeia de usuário toca auth-orgs + matrícula + notificações) |
| Cenários de validação cada um com sessão própria | Rejeições de validação agrupadas na **mesma sessão de formulário**, sem recarregar do zero |

**Motivo**: cada método Dusk custa migrações/truncate + boot de sessão WebDriver + login + navegação. Fragmentar ciclo de vida multiplica esse custo fixo sem ganhar cobertura. Encadear preserva cobertura e ainda testa transição real de estado que teste atômico nunca exercita.

### Regras obrigatórias da cadeia

1. **Asserção intermediária por etapa**: cada etapa precisa de asserção de UI (`assertSee`/`assertSeeIn`/`waitForText`) **e**, quando houver escrita, asserção de banco (`assertDatabaseHas`/`assertDatabaseMissing`). Cadeia sem checkpoint intermediário é anti-padrão (ver `validate-test-quality`).
2. **Comentários numerados de etapa** (`// 1. Criação`, `// 2. Edição`, ...) para falha ser localizável na linha.
3. **Nomenclatura**: `test_{ator}_{entidade|jornada}_lifecycle` para cadeias, `test_{entidade}_validation_rejections` para grupos de rejeição na mesma sessão, `test_{regra}_blocked`/`_isolation` para negativas.
4. **Não encadear o que é independente**: isolamento multitenant, negativa de autorização (403) e cenário que exige outro ator/tenant seguem em métodos próprios. Encadear mascara origem da falha e não compartilha estado útil.
5. **Cadeia não depende de ordem entre métodos**: encadeamento é *dentro* de um método. Nenhum método assume estado deixado por outro (`TRUNCATE` entre testes apagaria).
6. **Testes Unit/Feature seguem atômicos**: um comportamento por método. Lifecycle chaining é regra exclusiva de E2E de navegador, onde custo fixo por método é ordens de grandeza maior.

### Exemplo canônico

```php
namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

// Sem trait de banco: DatabaseTruncation vem de DuskTestCase.
class UserManagementTest extends DuskTestCase
{
    public function test_gestor_user_management_full_lifecycle(): void
    {
        $gestor = User::factory()->gestor()->create();

        $this->browse(function (Browser $browser) use ($gestor): void {
            // 1. Criação
            $browser->loginAs($gestor)
                ->visit(route('users.create'))
                ->type('name', 'Aluno Dusk')
                ->type('email', 'aluno.dusk@example.com')
                ->press('Criar Usuário')
                ->waitForLocation('/users')
                ->assertSee('Usuário criado com sucesso.');

            $user = User::where('email', 'aluno.dusk@example.com')->firstOrFail();
            $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'active']);

            // 2. Edição (mesma sessão, sem novo login)
            $browser->visit(route('users.edit', $user))
                ->clear('name')->type('name', 'Aluno Editado')
                ->press('Salvar Alterações')
                ->waitForLocation('/users')
                ->assertSee('Aluno Editado');

            // 3. Inativação + consequência: login bloqueado
            $browser->visit(route('users.edit', $user))
                ->select('@user-status-select', 'inactive')
                ->press('Salvar Alterações')
                ->waitForText('Usuário atualizado com sucesso.')
                ->logout()
                ->visit('/login')
                ->type('@login-email', 'aluno.dusk@example.com')
                ->type('@login-password', 'password')
                ->press('@login-submit')
                ->waitForText('These credentials do not match our records.')
                ->assertGuest();
        });

        $this->assertDatabaseHas('users', [
            'email' => 'aluno.dusk@example.com',
            'name' => 'Aluno Editado',
            'status' => 'inactive',
        ]);
    }
}
```

### Orçamento de desempenho (guardrail)

- Alvo: **≤ 1 login de navegador por método** de teste; reaproveite sessão via `$browser->visit(...)` em vez de novo `loginAs()`.
- Proibido `pause()`/`sleep()` como espera. Só waits explícitos (custo direto de wall-clock).
- Arquivo `tests/Browser/*` com > ~6 métodos é sinal de fragmentação: reavalie se há cadeia a unificar.
- Ao criar teste E2E novo, primeiro procure a cadeia existente que já cobre a jornada e estenda-a. Criar arquivo novo por módulo é padrão antigo.

4. **Banco de Dados Dedicado do Dusk (`testing`)**:
   - `tests/Browser/*.php` **jamais** roda contra `plataforma_ead`. Isolamento vem do par `.env.dusk.local` / `.env.dusk.example`, versionado na raiz do repositório com mesmo shape do `.env.example`:
     ```ini
     APP_ENV=dusk
     DUSK_DRIVER_URL=http://selenium:4444/wd/hub
     DB_CONNECTION=mysql
     DB_HOST=mysql
     DB_PORT=3306
     DB_DATABASE=testing
     ```
   - `.env.dusk.example` é template seguro para compartilhar (sem segredo real); `.env.dusk.local` é o arquivo consumido pelo `vendor/bin/sail dusk` (troca nativa de `.env` feita pelo `DuskCommand`, resolvendo `.env.dusk.{app.environment()}`).
   - Isolamento de dados vem de `DatabaseTruncation` declarado em `Tests\DuskTestCase` (herdado por toda classe em `tests/Browser/*`), que migra/limpa exclusivamente a conexão ativa, ou seja, base `testing`. Nunca assuma trait `RefreshDatabase` segura em Dusk: ela roda na conexão do processo de teste, não na do servidor HTTP.
   - No CI, equivalente é `.env.dusk.ci` (mesmo shape, apontando para serviço `mysql`/`selenium` do GitHub Actions), trocado explícito antes do passo `php artisan dusk`. Nunca reutilize `.env.ci` (sqlite) para passo Dusk.

---

## Exemplos de Código

### Teste de Feature (PHPUnit)

```php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }
}
```

### Teste de Navegador (Laravel Dusk)

```php
namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

// Sem trait de banco aqui: DatabaseTruncation está em DuskTestCase.
class ExampleSmokeTest extends DuskTestCase
{
    public function test_landing_page_full_structure_sections_and_ctas(): void
    {
        // Cadeia: uma única visita cobre estrutura + seções + CTAs,
        // em vez de um método (e um boot de navegador) por checagem.
        $this->browse(function (Browser $browser): void {
            $browser->visit('/')
                ->assertPathIs('/')
                ->assertTitle(config('app.name'))
                ->assertPresent('main')
                ->assertSeeIn('h1', "Let's get started")
                ->assertPresent('@cta-login')
                ->assertPresent('@cta-register');
        });
    }
}
```
