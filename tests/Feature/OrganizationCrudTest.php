<?php

namespace Tests\Feature;

use App\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SPEC-04 §2 / RF23 — Organization CRUD is reserved to `role:admin`.
 */
class OrganizationCrudTest extends TestCase
{
    public function test_admin_can_view_the_organizations_index(): void
    {
        $this->actingAsAdmin();
        Organization::factory()->count(2)->create();

        $this->get(route('organizations.index'))
            ->assertOk()
            ->assertViewIs('organizations.index');
    }

    public function test_organizations_index_renders_a_confirmation_modal_trigger_for_each_row(): void
    {
        $this->actingAsAdmin();
        $organization = Organization::factory()->create();

        $response = $this->get(route('organizations.index'))->assertOk();

        $response->assertSee('data-bs-toggle="modal"', false);
        $response->assertSee('data-bs-target="#delete-organization-'.$organization->id.'"', false);
        $response->assertSee('id="delete-organization-'.$organization->id.'"', false);
        $response->assertSee('dusk="delete-organization-'.$organization->id.'"', false);
        $response->assertSee('dusk="delete-form-'.$organization->id.'"', false);
        $response->assertSee(route('organizations.destroy', $organization), false);
        $response->assertSee($organization->name);
    }

    public function test_organizations_index_never_renders_an_open_modal(): void
    {
        $this->actingAsAdmin();
        Organization::factory()->create();

        $response = $this->get(route('organizations.index'))->assertOk();

        $response->assertDontSee('modal fade show', false);
        $response->assertDontSee('modal-backdrop', false);
    }

    public function test_gestor_is_forbidden_from_the_organizations_index(): void
    {
        $this->actingAsOrgUser(role: 'gestor');

        $this->get(route('organizations.index'))->assertForbidden();
    }

    public function test_aluno_is_forbidden_from_the_organizations_index(): void
    {
        $this->actingAsOrgUser(role: 'aluno');

        $this->get(route('organizations.index'))->assertForbidden();
    }

    public function test_guest_is_redirected_away_from_the_organizations_index(): void
    {
        $this->get(route('organizations.index'))->assertRedirect();
    }

    public function test_admin_can_view_the_create_organization_form(): void
    {
        $this->actingAsAdmin();

        $this->get(route('organizations.create'))
            ->assertOk()
            ->assertViewIs('organizations.create');
    }

    public function test_admin_can_create_an_organization(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('organizations.store'), [
            'name' => 'Instituto Alfa',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('organizations.index'));
        $this->assertDatabaseHas('organizations', [
            'name' => 'Instituto Alfa',
            'slug' => 'instituto-alfa',
            'status' => 'active',
        ]);
    }

    public function test_slug_is_used_as_provided_when_explicitly_set_and_unique(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('organizations.store'), [
            'name' => 'Instituto Gama',
            'slug' => 'custom-gama-slug',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('organizations.index'));
        $this->assertDatabaseHas('organizations', [
            'name' => 'Instituto Gama',
            'slug' => 'custom-gama-slug',
        ]);
    }

    public function test_slug_is_auto_generated_from_name_when_not_provided(): void
    {
        $this->actingAsAdmin();

        $this->post(route('organizations.store'), [
            'name' => 'Escola Beta',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('organizations', ['slug' => 'escola-beta']);
    }

    public function test_slug_is_disambiguated_when_it_already_exists(): void
    {
        Organization::factory()->create(['slug' => 'escola-beta']);
        $this->actingAsAdmin();

        $this->post(route('organizations.store'), [
            'name' => 'Escola Beta',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('organizations', ['slug' => 'escola-beta-2']);
    }

    public function test_slug_can_be_set_explicitly_and_must_be_unique(): void
    {
        Organization::factory()->create(['slug' => 'taken-slug']);
        $this->actingAsAdmin();

        $response = $this->post(route('organizations.store'), [
            'name' => 'Outra Org',
            'slug' => 'taken-slug',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('slug');
    }

    public function test_cnpj_must_match_the_brazilian_format(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('organizations.store'), [
            'name' => 'Org com CNPJ inválido',
            'status' => 'active',
            'cnpj' => '12345',
        ]);

        $response->assertSessionHasErrors('cnpj');
    }

    public function test_cnpj_must_be_unique(): void
    {
        Organization::factory()->create(['cnpj' => '12.345.678/0001-90']);
        $this->actingAsAdmin();

        $response = $this->post(route('organizations.store'), [
            'name' => 'Org duplicada',
            'status' => 'active',
            'cnpj' => '12.345.678/0001-90',
        ]);

        $response->assertSessionHasErrors('cnpj');
    }

    public function test_admin_can_upload_a_logo_when_creating_an_organization(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $this->post(route('organizations.store'), [
            'name' => 'Org com logo',
            'status' => 'active',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $organization = Organization::sole();
        $this->assertNotNull($organization->logo_path);
        Storage::disk('public')->assertExists($organization->logo_path);
    }

    public function test_gestor_cannot_create_an_organization(): void
    {
        $this->actingAsOrgUser(role: 'gestor');

        $this->post(route('organizations.store'), [
            'name' => 'Org proibida',
            'status' => 'active',
        ])->assertForbidden();
    }

    public function test_admin_can_view_the_edit_form_for_an_organization(): void
    {
        $this->actingAsAdmin();
        $organization = Organization::factory()->create();

        $this->get(route('organizations.edit', $organization))
            ->assertOk()
            ->assertViewIs('organizations.edit')
            ->assertSee($organization->name);
    }

    public function test_admin_can_update_an_organization(): void
    {
        $this->actingAsAdmin();
        $organization = Organization::factory()->create(['name' => 'Nome Antigo']);

        $response = $this->put(route('organizations.update', $organization), [
            'name' => 'Nome Novo',
            'slug' => $organization->slug,
            'status' => 'active',
        ]);

        $response->assertRedirect(route('organizations.index'));
        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => 'Nome Novo',
        ]);
    }

    public function test_admin_replacing_the_logo_on_update_deletes_the_old_one(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $organization = Organization::factory()->create([
            'logo_path' => 'organizations/logos/old-logo.png',
        ]);
        Storage::disk('public')->put($organization->logo_path, 'fake-contents');

        $this->put(route('organizations.update', $organization), [
            'name' => $organization->name,
            'slug' => $organization->slug,
            'status' => 'active',
            'logo' => UploadedFile::fake()->image('new-logo.png'),
        ]);

        $organization->refresh();
        Storage::disk('public')->assertMissing('organizations/logos/old-logo.png');
        Storage::disk('public')->assertExists($organization->logo_path);
        $this->assertNotSame('organizations/logos/old-logo.png', $organization->logo_path);
    }

    public function test_gestor_cannot_update_an_organization(): void
    {
        $organization = Organization::factory()->create();
        $this->actingAsOrgUser($organization, 'gestor');

        $this->put(route('organizations.update', $organization), [
            'name' => 'Tentativa Gestor',
            'slug' => $organization->slug,
            'status' => 'active',
        ])->assertForbidden();
    }

    public function test_admin_can_soft_delete_an_organization(): void
    {
        $this->actingAsAdmin();
        $organization = Organization::factory()->create();

        $this->delete(route('organizations.destroy', $organization))
            ->assertRedirect(route('organizations.index'));

        $this->assertSoftDeleted($organization);
    }

    public function test_gestor_cannot_delete_an_organization(): void
    {
        $organization = Organization::factory()->create();
        $this->actingAsOrgUser($organization, 'gestor');

        $this->delete(route('organizations.destroy', $organization))
            ->assertForbidden();
    }
}
