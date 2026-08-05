---
name: testing-conventions
description: Padrões de Código, Code Snippets e Guardrails para Suítes de Testes PHPUnit, Laravel Dusk e Cobertura de Código.
---

# Testing Conventions (`testing-conventions`)

## Overview

Este guia estabelece os padrões de código, convenções de escrita e guardrails para a criação e manutenção de testes automatizados no repositório Plataforma EAD.

---

## Convenções e Regras de Escrita

1. **Nomenclatura de Métodos de Teste**:
   - Utilizar snake_case prefixado com `test_` ou o atributo `#[Test]`.
   - Nomes descritivos expressando a intenção do negócio (ex: `test_user_can_view_course_details()`).

2. **Asserções Robustas (Proibição de Asserções Fracas)**:
   - Proibido o uso de asserções genéricas ou superficiais como `assertSourceHas('Laravel')`.
   - Preferir asserções de estrutura HTML (`assertPresent('main')`, `assertSeeIn('h1', ...)`), títulos exatos (`assertTitle(...)`) e estado autenticado do usuário (`assertAuthenticatedAs($user)`).

3. **Isolamento de Banco de Dados**:
   - Para testes Feature/Unit: utilizar `Illuminate\Foundation\Testing\RefreshDatabase`.
   - Para testes Dusk E2E: utilizar `Illuminate\Foundation\Testing\DatabaseMigrations` (ou `DatabaseTruncation`) pois o processo HTTP do Dusk e o teste rodam em conexões separadas.

4. **Banco de Dados Dedicado do Dusk (`testing`) — RN13/RF30 (SPEC-14)**:
   - `tests/Browser/*.php` **jamais** deve rodar contra `plataforma_ead`. O isolamento é garantido pelo par `.env.dusk.local` / `.env.dusk.example`, versionado na raiz do repositório com o mesmo shape do `.env.example`:
     ```ini
     APP_ENV=dusk
     DUSK_DRIVER_URL=http://selenium:4444/wd/hub
     DB_CONNECTION=mysql
     DB_HOST=mysql
     DB_PORT=3306
     DB_DATABASE=testing
     ```
   - `.env.dusk.example` é o template seguro para compartilhar (sem segredos reais); `.env.dusk.local` é o arquivo efetivamente consumido pelo `vendor/bin/sail dusk` (troca nativa de `.env` feita pelo `DuskCommand`, resolvendo `.env.dusk.{app.environment()}`).
   - Toda classe em `tests/Browser/*` deve usar `DatabaseMigrations` (ou `DatabaseTruncation`) para que as migrações/limpezas ocorram exclusivamente na base `testing` — nunca assuma que a trait `RefreshDatabase` é segura em testes Dusk, pois ela roda na conexão do processo de teste, não na do servidor HTTP.
   - No CI, o equivalente é `.env.dusk.ci` (mesmo shape, apontando para o serviço `mysql`/`selenium` do GitHub Actions), trocado explicitamente antes do passo `php artisan dusk` — nunca reutilize o `.env.ci` (sqlite) para o passo Dusk.

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
use Illuminate\Foundation\Testing\DatabaseMigrations;

class ExampleSmokeTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_homepage_renders_correctly(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/')
                ->assertPathIs('/')
                ->assertTitle(config('app.name'))
                ->assertPresent('main')
                ->assertSeeIn('h1', "Let's get started");
        });
    }
}
```
