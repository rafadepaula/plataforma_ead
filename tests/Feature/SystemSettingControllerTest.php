<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Organization;
use App\Models\SystemSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `GET`/`PUT /admin/settings` (`settings.edit`/`settings.update`) —
 * Admin-exclusive (`role:admin`). An Admin with no active Impersonate
 * Org session reads/writes the global row, an impersonating Admin writes
 * that org's override row, and every other role (Gestor and Aluno) is
 * forbidden by middleware. A blank `smtp_password` never overwrites the
 * currently stored one (see `dashboard-conventions`).
 */
class SystemSettingControllerTest extends TestCase
{
    public function test_gestor_cannot_access_the_settings_screen(): void
    {
        //  Configurações é uma superfície de administração do
        // sistema: `role:admin` no middleware bloqueia o Gestor antes de
        // qualquer leitura ou gravação — nem override de org, nem logo.
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $this->get(route('settings.edit'))->assertForbidden();
        $this->put(route('settings.update'), ['smtp_host' => 'smtp.hacker.com'])->assertForbidden();

        $this->assertDatabaseMissing('system_settings', [
            'setting_key' => 'smtp_host',
            'org_id' => $org->id,
        ]);
    }

    public function test_admin_impersonating_an_org_persists_an_org_scoped_override(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsAdmin($org);

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
        $this->actingAsAdmin($org);

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

    public function test_admin_impersonating_an_org_can_upload_a_logo(): void
    {
        Storage::fake('public');

        $org = Organization::factory()->create();
        $this->actingAsAdmin($org);

        $response = $this->put(route('settings.update'), [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $response->assertRedirect(route('settings.edit'));

        $logoPath = SystemSetting::query()->forKey('logo_path')->forOrg($org->id)->first()?->setting_value;

        $this->assertNotNull($logoPath);
        Storage::disk('public')->assertExists($logoPath);
    }

    public function test_non_admin_roles_are_forbidden_from_the_settings_routes(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::ALUNO->value);

        $this->get(route('settings.edit'))->assertForbidden();
        $this->put(route('settings.update'), ['smtp_host' => 'x'])->assertForbidden();
    }
}
