<?php

namespace Tests\Feature\Database;

use App\Models\Organization;
use App\Models\SystemSetting;
use Illuminate\Database\QueryException;
use Tests\TestCase;

/**
 * `system_settings` composite primary key
 * `(setting_key, org_id)` edge case: a literal nullable `org_id` cannot
 * participate in a MySQL/MariaDB composite PK, so global settings use a
 * `0` sentinel (`SystemSetting::GLOBAL_ORG_ID`) instead of `NULL` (see the
 * migration's docblock and the `tenancy-maintenance` skill).
 */
class SystemSettingTest extends TestCase
{
    public function test_a_global_setting_defaults_to_the_sentinel_org_id(): void
    {
        $setting = SystemSetting::create([
            'setting_key' => 'platform_name',
            'setting_value' => 'Plataforma EAD',
        ]);

        $this->assertSame(SystemSetting::GLOBAL_ORG_ID, $setting->org_id);
    }

    public function test_the_same_key_can_exist_once_globally_and_once_per_organization(): void
    {
        $organization = Organization::factory()->create();

        SystemSetting::create([
            'setting_key' => 'theme_color',
            'org_id' => SystemSetting::GLOBAL_ORG_ID,
            'setting_value' => 'blue',
        ]);

        SystemSetting::create([
            'setting_key' => 'theme_color',
            'org_id' => $organization->id,
            'setting_value' => 'red',
        ]);

        $this->assertSame(
            'blue',
            SystemSetting::query()->forKey('theme_color')->forOrg(null)->first()->setting_value
        );
        $this->assertSame(
            'red',
            SystemSetting::query()->forKey('theme_color')->forOrg($organization->id)->first()->setting_value
        );
    }

    public function test_the_same_key_cannot_be_duplicated_for_the_same_org(): void
    {
        SystemSetting::create([
            'setting_key' => 'theme_color',
            'org_id' => SystemSetting::GLOBAL_ORG_ID,
            'setting_value' => 'blue',
        ]);

        $this->expectException(QueryException::class);

        SystemSetting::create([
            'setting_key' => 'theme_color',
            'org_id' => SystemSetting::GLOBAL_ORG_ID,
            'setting_value' => 'green',
        ]);
    }
}
