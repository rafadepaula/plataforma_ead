<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E coverage for the Organization CRUD screens.
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): criar →
 * editar → abrir modal de remoção e cancelar → confirmar soft delete é uma
 * jornada única. A checagem do preview de logo  é outra jornada, e
 * a negativa de autorização do Gestor segue isolada.
 */
class OrganizationCrudTest extends DuskTestCase
{
    /**
     * Caminhos gravados no disco `public` real por este teste, apagados no
     * `tearDown()`.
     *
     * @var list<string>
     */
    private array $publicDiskFixtures = [];

    public function test_admin_organization_crud_lifecycle(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->browse(function (Browser $browser) use ($admin): void {
            // 1. Criação
            $browser->loginAs($admin)
                ->visit(route('organizations.create'))
                ->waitFor('@organization-form')
                ->type('name', 'Instituto Dusk')
                ->press('Criar Organização')
                ->waitForLocation('/organizations')
                ->assertSee('Instituto Dusk')
                ->assertSee('Organização criada com sucesso.');

            $this->assertDatabaseHas('organizations', ['name' => 'Instituto Dusk']);

            $organization = Organization::where('name', 'Instituto Dusk')->firstOrFail();

            // 2. Edição a partir da listagem
            $browser->visit(route('organizations.index'))
                ->waitFor('@edit-organization-'.$organization->id)
                ->click('@edit-organization-'.$organization->id)
                ->waitFor('@organization-form')
                ->clear('name')
                ->type('name', 'Instituto Dusk Editado')
                ->press('Salvar Alterações')
                ->waitForLocation('/organizations')
                ->assertSee('Instituto Dusk Editado');

            $this->assertDatabaseHas('organizations', [
                'id' => $organization->id,
                'name' => 'Instituto Dusk Editado',
            ]);

            // 3.  modal de remoção nasce fechado; cancelar preserva.
            $browser->visit(route('organizations.index'))
                ->waitFor('@delete-organization-'.$organization->id)
                ->assertMissing('.modal.show')
                ->assertMissing('.modal-backdrop')
                ->click('@delete-organization-'.$organization->id)
                // Espera a transição `.fade` terminar: clicar num diálogo ainda
                // em movimento é a fonte nº 1 de flake aqui.
                ->waitForModalShown('delete-organization-'.$organization->id)
                // O modal identifica a Organização pelo nome.
                ->assertSeeIn('#delete-organization-'.$organization->id, 'Instituto Dusk Editado')
                ->click('@confirm-modal-delete-organization-'.$organization->id.'-cancel')
                // O backdrop só some no fim da transição de fechamento.
                ->waitForModalClosed('delete-organization-'.$organization->id)
                ->assertMissing('.modal.show')
                ->assertVisible('@organization-row-'.$organization->id)
                ->assertDontSee('Organização removida com sucesso.');

            $this->assertNotSoftDeleted($organization);

            // 4. Confirmar remoção: soft delete e desaparecimento do DOM
            $browser->click('@delete-organization-'.$organization->id)
                ->waitForModalShown('delete-organization-'.$organization->id)
                ->click('@confirm-modal-delete-organization-'.$organization->id.'-confirm')
                ->waitForLocation('/organizations')
                ->assertSee('Organização removida com sucesso.')
                ->assertDontSee('Instituto Dusk Editado')
                ->assertMissing('@organization-row-'.$organization->id);
        });

        $this->assertSoftDeleted(Organization::withTrashed()->where('name', 'Instituto Dusk Editado')->firstOrFail());
    }

    /**
     *  o logo já salvo aparece como imagem carregada (não como
     * caminho de arquivo) na tela de edição; sem logo salvo, nenhum `<img>`
     * é renderizado (nem na tela de criação).
     */
    public function test_organization_logo_preview_presence_and_absence(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        // Nome único por execução: `DatabaseTruncation` não limpa o disco
        // `public`, então o fixture precisa não colidir com sobras.
        $logoPath = 'organizations/logos/dusk-logo-'.uniqid().'.png';
        Storage::disk('public')->put($logoPath, $this->onePixelPng());
        $this->publicDiskFixtures[] = $logoPath;

        $withLogo = Organization::factory()->create([
            'name' => 'Org Com Logo',
            'logo_path' => $logoPath,
        ]);
        $withoutLogo = Organization::factory()->create([
            'name' => 'Org Sem Logo',
            'logo_path' => null,
        ]);

        $this->browse(function (Browser $browser) use ($admin, $withLogo, $withoutLogo, $logoPath): void {
            // 1. Com logo salvo: `<img>` presente, carregado e sem caminho cru.
            $browser->loginAs($admin)
                ->visit(route('organizations.edit', $withLogo))
                ->waitFor('@organization-form')
                ->assertVisible('@organization-logo-preview')
                ->assertDontSee($logoPath)
                ->assertDontSee('Logo atual:')
                ->assertAttribute('@organization-logo-preview', 'alt', 'Logo da Organização Org Com Logo')
                // A imagem realmente carregou: sem o symlink `public/storage`
                // isto seria 0 (404), que é exatamente o modo de falha real.
                ->waitUntil('document.querySelector(\'[dusk="organization-logo-preview"]\').naturalWidth > 0');

            // 2. Sem logo salvo: nenhum `<img>`.
            $browser->visit(route('organizations.edit', $withoutLogo))
                ->waitFor('@organization-form')
                ->assertMissing('@organization-logo-preview')
                ->assertDontSee('Logo atual:');

            // 3. Tela de criação: nunca há preview.
            $browser->visit(route('organizations.create'))
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
