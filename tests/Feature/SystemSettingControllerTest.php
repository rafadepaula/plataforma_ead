<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SystemSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `GET`/`PUT /admin/settings` (`settings.edit`/`settings.update`).
 * A Gestor reads/writes their own Organization's override row, an Admin
 * with no active Impersonate Org session reads/writes the global row, an
 * Aluno is forbidden entirely, and a blank `smtp_password` never
 * overwrites the currently stored one (see `dashboard-conventions`).
 */
class SystemSettingControllerTest extends TestCase
{
    public function test_gestor_can_view_the_settings_edit_screen(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, 'gestor');

        $response = $this->get(route('settings.edit'));

        $response->assertOk();
        $response->assertSee('Configurações');
    }

    public function test_gestor_update_persists_an_org_scoped_override(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, 'gestor');

        $response = $this->put(route('settings.update'), [
            'smtp_host' => 'smtp.minhaorg.com',
            'smtp_port' => '587',
            'signature' => 'Diretoria Pedagógica',
        ]);

        $response->assertRedirect(route('settings.edit'));

        $this->assertSame(
            'smtp.minhaorg.com',
            SystemSetting::query()->forKey('smtp_host')->forOrg($org->id)->first()?->setting_value
        );

        $editResponse = $this->get(route('settings.edit'));
        $editResponse->assertSee('smtp.minhaorg.com');
        $editResponse->assertSee('Diretoria Pedagógica');
    }

    public function test_admin_with_no_impersonated_org_edits_the_global_settings_row(): void
    {
        $this->actingAsAdmin();

        $response = $this->put(route('settings.update'), [
            'smtp_host' => 'smtp.global.com',
        ]);

        $response->assertRedirect(route('settings.edit'));

        $this->assertSame(
            'smtp.global.com',
            SystemSetting::query()->forKey('smtp_host')->forOrg(null)->first()?->setting_value
        );
    }

    public function test_blank_smtp_password_does_not_overwrite_the_stored_one(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, 'gestor');

        $this->put(route('settings.update'), ['smtp_password' => 'super-secret']);

        $this->assertSame(
            'super-secret',
            SystemSetting::query()->forKey('smtp_password')->forOrg($org->id)->first()?->setting_value
        );

        // Read the raw column directly (bypassing the encrypting accessor)
        // to prove `smtp_password` is never persisted in plaintext, not
        // just that the accessor round-trips it correctly.
        $rawValue = DB::table('system_settings')
            ->where('setting_key', 'smtp_password')
            ->where('org_id', $org->id)
            ->value('setting_value');

        $this->assertNotSame('super-secret', $rawValue);

        $this->put(route('settings.update'), ['smtp_password' => '']);

        $this->assertSame(
            'super-secret',
            SystemSetting::query()->forKey('smtp_password')->forOrg($org->id)->first()?->setting_value
        );
    }

    public function test_gestor_can_upload_a_logo(): void
    {
        Storage::fake('public');

        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, 'gestor');

        $response = $this->put(route('settings.update'), [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $response->assertRedirect(route('settings.edit'));

        $logoPath = SystemSetting::query()->forKey('logo_path')->forOrg($org->id)->first()?->setting_value;

        $this->assertNotNull($logoPath);
        Storage::disk('public')->assertExists($logoPath);
    }

    public function test_aluno_cannot_access_the_settings_screen(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, 'aluno');

        $this->get(route('settings.edit'))->assertForbidden();
        $this->put(route('settings.update'), ['smtp_host' => 'x'])->assertForbidden();
    }
}
