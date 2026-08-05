<?php

namespace Tests\Unit\Services;

use App\Models\Organization;
use App\Models\SystemSetting;
use App\Services\SettingService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * SPEC-12 — `SettingService` is the single source of truth for reading
 * and writing `system_settings` rows: org-specific-first, global-fallback
 * resolution (mirroring `HelpArticleResolverService`'s pattern), backed by
 * a `Cache::remember()` layer that `set()`/`forget()` must bust for both
 * the requested org's cache entry and the global one.
 */
class SettingServiceTest extends TestCase
{
    private SettingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SettingService;
    }

    public function test_it_resolves_the_org_specific_value_when_one_exists(): void
    {
        $org = Organization::factory()->create();

        SystemSetting::create([
            'setting_key' => 'smtp_host',
            'org_id' => SystemSetting::GLOBAL_ORG_ID,
            'setting_value' => 'global.smtp.example.com',
        ]);
        SystemSetting::create([
            'setting_key' => 'smtp_host',
            'org_id' => $org->id,
            'setting_value' => 'org.smtp.example.com',
        ]);

        $this->assertSame('org.smtp.example.com', $this->service->get('smtp_host', $org->id));
    }

    public function test_it_falls_back_to_the_global_value_when_no_org_specific_row_exists(): void
    {
        $org = Organization::factory()->create();

        SystemSetting::create([
            'setting_key' => 'smtp_host',
            'org_id' => SystemSetting::GLOBAL_ORG_ID,
            'setting_value' => 'global.smtp.example.com',
        ]);

        $this->assertSame('global.smtp.example.com', $this->service->get('smtp_host', $org->id));
    }

    public function test_it_returns_the_given_default_when_no_row_exists_at_any_level(): void
    {
        $org = Organization::factory()->create();

        $this->assertSame('fallback', $this->service->get('unauthored_key', $org->id, 'fallback'));
        $this->assertNull($this->service->get('unauthored_key_2', $org->id));
    }

    public function test_it_resolves_only_the_global_value_for_a_null_org_context(): void
    {
        SystemSetting::create([
            'setting_key' => 'platform_name',
            'org_id' => SystemSetting::GLOBAL_ORG_ID,
            'setting_value' => 'Plataforma EAD',
        ]);

        $this->assertSame('Plataforma EAD', $this->service->get('platform_name', null));
    }

    public function test_it_does_not_leak_another_organizations_specific_value(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();

        SystemSetting::create([
            'setting_key' => 'smtp_host',
            'org_id' => $otherOrg->id,
            'setting_value' => 'other-org.smtp.example.com',
        ]);

        $this->assertNull($this->service->get('smtp_host', $org->id));
    }

    public function test_get_caches_the_resolved_value(): void
    {
        $org = Organization::factory()->create();

        SystemSetting::create([
            'setting_key' => 'smtp_host',
            'org_id' => $org->id,
            'setting_value' => 'cached-value',
        ]);

        $this->assertSame('cached-value', $this->service->get('smtp_host', $org->id));

        // Mutate the row directly, bypassing the service: the cached
        // value must still be served until the cache is busted.
        SystemSetting::query()->forKey('smtp_host')->forOrg($org->id)->update(['setting_value' => 'mutated-value']);

        $this->assertSame('cached-value', $this->service->get('smtp_host', $org->id));
    }

    public function test_set_persists_and_busts_the_cache_for_the_requested_org(): void
    {
        $org = Organization::factory()->create();

        $this->service->get('smtp_host', $org->id, 'default'); // warm an empty-result cache entry

        $this->service->set('smtp_host', $org->id, 'new-org-value');

        $this->assertSame('new-org-value', $this->service->get('smtp_host', $org->id));
        $this->assertSame(
            'new-org-value',
            SystemSetting::query()->forKey('smtp_host')->forOrg($org->id)->first()->setting_value
        );
    }

    public function test_set_with_a_null_org_writes_the_global_sentinel_row(): void
    {
        $this->service->set('platform_name', null, 'Nova Plataforma');

        $this->assertSame(
            'Nova Plataforma',
            SystemSetting::query()->forKey('platform_name')->forOrg(null)->first()->setting_value
        );
    }

    public function test_set_updates_an_existing_row_instead_of_duplicating_the_composite_key(): void
    {
        $org = Organization::factory()->create();

        $this->service->set('smtp_host', $org->id, 'first-value');
        $this->service->set('smtp_host', $org->id, 'second-value');

        $this->assertSame(
            1,
            SystemSetting::query()->forKey('smtp_host')->forOrg($org->id)->count()
        );
        $this->assertSame('second-value', $this->service->get('smtp_host', $org->id));
    }

    public function test_set_on_an_org_specific_key_also_busts_the_global_cache_entry(): void
    {
        SystemSetting::create([
            'setting_key' => 'smtp_host',
            'org_id' => SystemSetting::GLOBAL_ORG_ID,
            'setting_value' => 'global-value',
        ]);
        $org = Organization::factory()->create();

        // Warm the global cache entry.
        $this->assertSame('global-value', $this->service->get('smtp_host', null));

        $this->service->set('smtp_host', $org->id, 'org-value');

        // Directly mutate the global row and confirm the global cache
        // entry was actually busted by the org-scoped set() call.
        SystemSetting::query()->forKey('smtp_host')->forOrg(null)->update(['setting_value' => 'mutated-global-value']);

        $this->assertSame('mutated-global-value', $this->service->get('smtp_host', null));
    }

    public function test_set_on_a_global_key_busts_the_cached_fallback_for_every_other_org(): void
    {
        $org = Organization::factory()->create();

        SystemSetting::create([
            'setting_key' => 'smtp_host',
            'org_id' => SystemSetting::GLOBAL_ORG_ID,
            'setting_value' => 'old-global-value',
        ]);

        // Warm the org's cache entry: no org-specific row exists, so this
        // caches the *global* fallback value under the org's cache key.
        $this->assertSame('old-global-value', $this->service->get('smtp_host', $org->id));

        $this->service->set('smtp_host', null, 'new-global-value');

        $this->assertSame('new-global-value', $this->service->get('smtp_host', $org->id));
    }

    public function test_forget_removes_the_row_and_busts_the_cache(): void
    {
        $org = Organization::factory()->create();

        $this->service->set('smtp_host', $org->id, 'to-be-removed');
        $this->assertSame('to-be-removed', $this->service->get('smtp_host', $org->id));

        $this->service->forget('smtp_host', $org->id);

        $this->assertNull(SystemSetting::query()->forKey('smtp_host')->forOrg($org->id)->first());
        $this->assertNull($this->service->get('smtp_host', $org->id));
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}
