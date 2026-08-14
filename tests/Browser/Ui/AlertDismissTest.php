<?php

namespace Tests\Browser\Ui;

use App\Enums\Permissions\RolesEnum;
use App\Models\Organization;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * BUG-004 — regressão do botão de fechar de `<x-ui.alert dismissable>`.
 *
 * O defeito original era um `@click` do Alpine (nunca instalado), inerte no
 * navegador. Hoje o contrato é `data-bs-dismiss="alert"` + o import do bundle
 * do Bootstrap em `resources/js/app.js`, que registra o listener delegado.
 * Este teste trava o comportamento ponta a ponta: qualquer volta ao handler
 * artesanal, ou a quebra do import do bundle, derruba a suíte.
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): o mesmo
 * Admin dispensa um alerta único e, em seguida, um alerta entre dois — numa
 * única sessão de navegador.
 *
 * A asserção final é sobre a REMOÇÃO DO NÓ (`Alert._destroyElement()` roda ao
 * fim da transição do `.fade`), não sobre visibilidade — por isso o
 * `waitUntil` sobre `document.querySelectorAll('.alert').length`.
 */
class AlertDismissTest extends DuskTestCase
{
    public function test_alert_dismiss_lifecycle(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        $removable = Organization::factory()->create(['name' => 'Organização Removível']);
        $target = Organization::factory()->create(['name' => 'Organização Alvo']);

        $this->browse(function (Browser $browser) use ($admin, $removable, $target): void {
            // 1. UX-003 — "Remover" abre o modal de confirmação; o flash de
            //    sucesso só existe depois de confirmar.
            $browser->loginAs($admin)
                ->visit(route('organizations.index'))
                ->waitFor('@delete-organization-'.$removable->id)
                ->click('@delete-organization-'.$removable->id)
                ->waitForModalShown('delete-organization-'.$removable->id)
                ->click('@confirm-modal-delete-organization-'.$removable->id.'-confirm')
                ->waitForLocation('/organizations')
                // Pré-condição: o alerta precisa estar de fato na tela antes do
                // clique, senão o teste passaria vazio.
                ->waitFor('.alert')
                ->assertSee('Organização removida com sucesso.')
                ->assertVisible('@alert-dismiss')
                ->assertScript('document.querySelectorAll(".alert").length', 1);

            // 2. Dispensar remove o nó do DOM (fim da transição do `.fade`).
            $browser->click('@alert-dismiss')
                ->waitUntil('document.querySelectorAll(".alert").length === 0')
                ->assertScript('document.querySelectorAll(".alert").length', 0)
                ->assertDontSee('Organização removida com sucesso.')
                // A tela continua utilizável depois do dismiss.
                ->assertPresent('@new-organization');

            $this->assertSoftDeleted($removable);

            // 3. Assumir o contexto da Organização deixa DOIS alertas na mesma
            //    tela: o flash de sucesso (layout) e o aviso de contexto (view).
            $browser->waitFor('@impersonate-'.$target->id)
                ->click('@impersonate-'.$target->id)
                ->waitForLocation('/organizations')
                ->waitFor('.alert')
                ->assertSee('Contexto alterado para a Organização "Organização Alvo".')
                ->assertSee('Você está no contexto da Organização')
                ->assertScript('document.querySelectorAll(".alert").length', 2);

            // 4. Dispensar um NÃO afeta o outro. O primeiro `@alert-dismiss`
            //    do DOM é o do flash de sucesso.
            $browser->click('@alert-dismiss')
                ->waitUntil('document.querySelectorAll(".alert").length === 1')
                ->assertDontSee('Contexto alterado para a Organização')
                ->assertSee('Você está no contexto da Organização')
                ->assertPresent('@exit-impersonation');
        });
    }
}
