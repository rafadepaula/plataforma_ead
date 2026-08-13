<?php

namespace Tests\Feature;

use App\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
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

    /**
     * UX-004 — the edit screen renders the stored logo as an actual `<img>`
     * resolved from the `public` disk, never the raw column value.
     */
    public function test_edit_form_renders_the_current_logo_as_an_image(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $organization = Organization::factory()->create([
            'name' => 'Instituto Preview',
            'logo_path' => 'organizations/logos/current-logo.png',
        ]);
        Storage::disk('public')->put($organization->logo_path, 'fake-contents');

        $response = $this->get(route('organizations.edit', $organization))->assertOk();

        $response->assertSee('dusk="organization-logo-preview"', false);
        $response->assertSee('src="'.Storage::disk('public')->url($organization->logo_path).'"', false);
        $response->assertSee('alt="Logo da Organização Instituto Preview"', false);
        $response->assertSee('org-logo', false);
    }

    /**
     * UX-004 — the raw `logo_path` string must no longer leak into the UI.
     */
    public function test_edit_form_no_longer_prints_the_raw_logo_path(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $organization = Organization::factory()->create([
            'logo_path' => 'organizations/logos/current-logo.png',
        ]);

        $response = $this->get(route('organizations.edit', $organization))->assertOk();

        $response->assertDontSee('Logo atual:');
    }

    /**
     * UX-004 — no logo means no `<img>` at all, so there is never an empty
     * `src` re-requesting the page's own HTML.
     */
    public function test_edit_form_renders_no_logo_preview_when_the_organization_has_none(): void
    {
        $this->actingAsAdmin();
        $organization = Organization::factory()->create(['logo_path' => null]);

        $response = $this->get(route('organizations.edit', $organization))->assertOk();

        $response->assertDontSee('dusk="organization-logo-preview"', false);
        $response->assertDontSee('src=""', false);
    }

    public function test_create_form_renders_no_logo_preview(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('organizations.create'))->assertOk();

        $response->assertDontSee('dusk="organization-logo-preview"', false);
        $response->assertDontSee('src=""', false);
    }

    /**
     * UX-004 — the logo field moved to `<x-ui.input type="file">`; the
     * `id`/`name` contract and the accepted MIME filter must survive.
     */
    public function test_logo_field_keeps_its_input_contract_after_migrating_to_the_ui_component(): void
    {
        $this->actingAsAdmin();
        $organization = Organization::factory()->create();

        $response = $this->get(route('organizations.edit', $organization))->assertOk();

        $response->assertSee('type="file"', false);
        $response->assertSee('id="logo"', false);
        $response->assertSee('name="logo"', false);
        $response->assertSee('accept="image/*"', false);
    }

    /**
     * UX-004 failure path — a non-image upload is rejected and nothing is
     * written to the `public` disk nor to `logo_path`.
     */
    public function test_a_non_image_logo_upload_is_rejected_and_never_persisted(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $organization = Organization::factory()->create(['logo_path' => null]);

        $this->from(route('organizations.edit', $organization))
            ->put(route('organizations.update', $organization), [
                'name' => $organization->name,
                'slug' => $organization->slug,
                'status' => 'active',
                'logo' => UploadedFile::fake()->create('not-an-image.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect(route('organizations.edit', $organization))
            ->assertSessionHasErrors('logo');

        $this->assertNull($organization->refresh()->logo_path);
        $this->assertEmpty(Storage::disk('public')->allFiles('organizations/logos'));
    }

    /**
     * UX-004 failure path (rendering) — with a `logo` error in the bag, the
     * field is now rendered by `<x-ui.input>`, so the message comes out in
     * `.invalid-feedback` under `dusk="error-logo"` and the control itself
     * gets `.is-invalid`.
     *
     * The bag is shared with the view directly because `SESSION_DRIVER=array`
     * (phpunit.xml) discards session data seeded by the test before the
     * request is handled, so a flashed error bag never reaches the view.
     */
    public function test_logo_validation_error_is_rendered_by_the_ui_input_component(): void
    {
        $bag = new ViewErrorBag;
        $bag->put('default', new MessageBag(['logo' => ['O campo logo deve ser uma imagem.']]));
        View::share('errors', $bag);

        $html = Blade::render('<x-ui.input type="file" name="logo" label="Logo" accept="image/*" />');

        $this->assertStringContainsString('dusk="error-logo"', $html);
        $this->assertStringContainsString('invalid-feedback', $html);
        $this->assertStringContainsString('is-invalid', $html);
        $this->assertStringContainsString('O campo logo deve ser uma imagem.', $html);
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
