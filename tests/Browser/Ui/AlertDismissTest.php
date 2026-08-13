<?php

namespace Tests\Browser\Ui;

use App\Enums\Permissions\RolesEnum;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * BUG-004 — regressão do botão de fechar de `<x-ui.alert dismissable>`.
 *
 * O defeito original era um `@click` do Alpine (nunca instalado), inerte no
 * navegador. Hoje o contrato é `data-bs-dismiss="alert"` + o import do bundle
 * do Bootstrap em `resources/js/app.js`, que registra o listener delegado.
 * Estes testes travam esse comportamento ponta a ponta: qualquer volta ao
 * handler artesanal, ou a quebra do import do bundle, derruba a suíte.
 *
 * A asserção final é sobre a REMOÇÃO DO NÓ (`Alert._destroyElement()` roda ao
 * fim da transição do `.fade`), não sobre visibilidade — por isso o
 * `waitUntil` sobre `document.querySelectorAll('.alert').length`.
 */
class AlertDismissTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_admin_can_dismiss_a_flash_alert_and_the_node_leaves_the_dom(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        $organization = Organization::factory()->create(['name' => 'Organização Removível']);

        $this->browse(function (Browser $browser) use ($admin, $organization): void {
            $browser->loginAs($admin)
                ->visit(route('organizations.index'))
                ->waitFor('@delete-organization-'.$organization->id)
                ->click('@delete-organization-'.$organization->id)
                ->waitForLocation('/organizations')
                // Pré-condição: o alerta precisa estar de fato na tela antes do
                // clique, senão o teste passaria vazio.
                ->waitFor('.alert')
                ->assertSee('Organização removida com sucesso.')
                ->assertVisible('@alert-dismiss')
                ->assertScript('document.querySelectorAll(".alert").length', 1)
                ->click('@alert-dismiss')
                // O Bootstrap só remove o nó ao fim da transição do `.fade`.
                ->waitUntil('document.querySelectorAll(".alert").length === 0')
                ->assertScript('document.querySelectorAll(".alert").length', 0)
                ->assertDontSee('Organização removida com sucesso.')
                // A tela continua utilizável depois do dismiss.
                ->assertPresent('@new-organization');
        });
    }

    public function test_dismissing_one_alert_leaves_the_other_alerts_untouched(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        $organization = Organization::factory()->create(['name' => 'Organização Alvo']);

        $this->browse(function (Browser $browser) use ($admin, $organization): void {
            // Assumir o contexto da Organização deixa dois alertas na mesma
            // tela: o flash de sucesso (layout) e o aviso de contexto (view).
            $browser->loginAs($admin)
                ->visit(route('organizations.index'))
                ->waitFor('@impersonate-'.$organization->id)
                ->click('@impersonate-'.$organization->id)
                ->waitForLocation('/organizations')
                ->waitFor('.alert')
                ->assertSee('Contexto alterado para a Organização "Organização Alvo".')
                ->assertSee('Você está no contexto da Organização')
                ->assertScript('document.querySelectorAll(".alert").length', 2)
                // O primeiro `@alert-dismiss` do DOM é o do flash de sucesso.
                ->click('@alert-dismiss')
                ->waitUntil('document.querySelectorAll(".alert").length === 1')
                ->assertDontSee('Contexto alterado para a Organização')
                ->assertSee('Você está no contexto da Organização')
                ->assertPresent('@exit-impersonation');
        });
    }
}
