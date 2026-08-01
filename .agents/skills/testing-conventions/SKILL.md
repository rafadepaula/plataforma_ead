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
   - Para testes Dusk E2E: utilizar `Illuminate\Foundation\Testing\DatabaseMigrations` (pois o processo HTTP do Dusk e o teste rodam em conexões separadas).

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
