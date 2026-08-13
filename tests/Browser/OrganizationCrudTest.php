<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-04 §2 / RF23 — E2E coverage for the Organization CRUD screens.
 */
class OrganizationCrudTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Caminhos gravados no disco `public` real por este teste, apagados no
     * `tearDown()`.
     *
     * @var list<string>
     */
    private array $publicDiskFixtures = [];

    public function test_admin_can_create_an_organization_via_the_ui(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->browse(function (Browser $browser) use ($admin): void {
            $browser->loginAs($admin)
                ->visit(route('organizations.create'))
                ->waitFor('@organization-form')
                ->type('name', 'Instituto Dusk')
                ->press('Criar Organização')
                ->waitForLocation('/organizations')
                ->assertSee('Instituto Dusk')
                ->assertSee('Organização criada com sucesso.');
        });
    }

    public function test_admin_can_edit_an_organization_via_the_ui(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        $organization = Organization::factory()->create(['name' => 'Nome Original']);

        $this->browse(function (Browser $browser) use ($admin, $organization): void {
            $browser->loginAs($admin)
                ->visit(route('organizations.index'))
                ->waitFor('@edit-organization-'.$organization->id)
                ->click('@edit-organization-'.$organization->id)
                ->waitFor('@organization-form')
                ->clear('name')
                ->type('name', 'Nome Editado')
                ->press('Salvar Alterações')
                ->waitForLocation('/organizations')
                ->assertSee('Nome Editado');
        });
    }

    public function test_admin_can_soft_delete_an_organization_via_the_ui(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        $organization = Organization::factory()->create(['name' => 'Organização Removível']);

        $this->browse(function (Browser $browser) use ($admin, $organization): void {
            $browser->loginAs($admin)
                ->visit(route('organizations.index'))
                ->waitFor('@delete-organization-'.$organization->id)
                // UX-003 — nenhum modal nasce aberto (ver BUG-003).
                ->assertMissing('.modal.show')
                ->assertMissing('.modal-backdrop')
                ->click('@delete-organization-'.$organization->id)
                // Espera a transição `.fade` terminar: clicar num diálogo ainda
                // em movimento é a fonte nº 1 de flake aqui.
                ->waitForModalShown('delete-organization-'.$organization->id)
                // O modal identifica a Organização pelo nome.
                ->assertSeeIn('#delete-organization-'.$organization->id, 'Organização Removível')
                ->click('@confirm-modal-delete-organization-'.$organization->id.'-confirm')
                ->waitForLocation('/organizations')
                ->assertSee('Organização removida com sucesso.')
                ->assertDontSee('Organização Removível');
        });

        $this->assertSoftDeleted($organization);
    }

    public function test_admin_can_cancel_the_organization_deletion(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        $organization = Organization::factory()->create(['name' => 'Organização Preservada']);

        $this->browse(function (Browser $browser) use ($admin, $organization): void {
            $browser->loginAs($admin)
                ->visit(route('organizations.index'))
                ->waitFor('@delete-organization-'.$organization->id)
                ->assertMissing('.modal.show')
                ->assertMissing('.modal-backdrop')
                ->click('@delete-organization-'.$organization->id)
                ->waitForModalShown('delete-organization-'.$organization->id)
                ->click('@confirm-modal-delete-organization-'.$organization->id.'-cancel')
                // O backdrop só some no fim da transição de fechamento.
                ->waitForModalClosed('delete-organization-'.$organization->id)
                ->assertMissing('.modal.show')
                ->assertVisible('@organization-row-'.$organization->id)
                ->assertSee('Organização Preservada')
                ->assertDontSee('Organização removida com sucesso.');
        });

        $this->assertNotSoftDeleted($organization);
    }

    /**
     * UX-004 — o logo já salvo aparece como imagem carregada (não como
     * caminho de arquivo) na tela de edição.
     */
    public function test_edit_screen_shows_the_current_logo_as_a_loaded_image(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $logoPath = 'organizations/logos/dusk-logo.png';
        Storage::disk('public')->put($logoPath, $this->onePixelPng());
        $this->publicDiskFixtures[] = $logoPath;

        $organization = Organization::factory()->create([
            'name' => 'Org Com Logo',
            'logo_path' => $logoPath,
        ]);

        $this->browse(function (Browser $browser) use ($admin, $organization, $logoPath): void {
            $browser->loginAs($admin)
                ->visit(route('organizations.edit', $organization))
                ->waitFor('@organization-form')
                ->assertVisible('@organization-logo-preview')
                // O caminho cru do arquivo não é mais exibido ao usuário.
                ->assertDontSee($logoPath)
                ->assertDontSee('Logo atual:')
                ->assertAttribute('@organization-logo-preview', 'alt', 'Logo da Organização Org Com Logo')
                // A imagem realmente carregou: sem o symlink `public/storage`
                // isto seria 0 (404), que é exatamente o modo de falha real.
                ->waitUntil('document.querySelector(\'[dusk="organization-logo-preview"]\').naturalWidth > 0');
        });
    }

    /**
     * UX-004 — sem logo salvo, nenhum `<img>` é renderizado (nem na criação).
     */
    public function test_logo_preview_is_absent_without_a_logo_and_on_the_create_screen(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        $organization = Organization::factory()->create(['logo_path' => null]);

        $this->browse(function (Browser $browser) use ($admin, $organization): void {
            $browser->loginAs($admin)
                ->visit(route('organizations.edit', $organization))
                ->waitFor('@organization-form')
                ->assertMissing('@organization-logo-preview')
                ->assertDontSee('Logo atual:')
                ->visit(route('organizations.create'))
                ->waitFor('@organization-form')
                ->assertMissing('@organization-logo-preview');
        });
    }

    public function test_gestor_cannot_reach_the_organizations_index(): void
    {
        $gestor = User::factory()->create();
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $this->browse(function (Browser $browser) use ($gestor): void {
            $browser->loginAs($gestor)
                ->visit(route('organizations.index'))
                ->assertSee('403');
        });
    }

    /**
     * Dusk roda contra o disco `public` real (o browser busca a imagem por
     * HTTP), então os fixtures são removidos ao final de cada teste.
     */
    protected function tearDown(): void
    {
        foreach ($this->publicDiskFixtures as $path) {
            Storage::disk('public')->delete($path);
        }

        $this->publicDiskFixtures = [];

        parent::tearDown();
    }

    /**
     * PNG 1x1 válido, para o browser conseguir decodificar a imagem.
     */
    private function onePixelPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }
}
