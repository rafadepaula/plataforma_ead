<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

/**
 * reads/writes org-override `system_settings` rows
 * (SMTP/logo/signature, etc.), mirroring `HelpArticleResolverService`'s
 * org-specific-first, global-fallback resolution: an org-specific row for
 * `$orgId` wins, otherwise the global (`SystemSetting::GLOBAL_ORG_ID`
 * sentinel) row for the same `$key` is served, otherwise `$default`.
 *
 * Reads are cached per `($key, $orgId)` pair via `Cache::remember()`.
 * `set()`/`forget()` always bust both the requested org's cache entry and
 * the global one. Additionally, the cache key embeds a per-`$key`
 * "generation" counter: setting/forgetting a *global* row (`$orgId ===
 * null`) bumps that counter, which changes the cache key for every org,
 * transparently invalidating any previously cached org-scoped fallback —
 * without needing to enumerate which orgs had cached it — since a
 * global-row change can change what those cached fallbacks resolve to.
 */
class SettingService
{
    private const CACHE_TTL_SECONDS = 3600;

    public function get(string $key, ?int $orgId, mixed $default = null): mixed
    {
        return Cache::remember(
            $this->cacheKey($key, $orgId),
            self::CACHE_TTL_SECONDS,
            function () use ($key, $orgId, $default): mixed {
                if ($orgId !== null) {
                    $orgSpecific = SystemSetting::query()->forKey($key)->forOrg($orgId)->first();

                    if ($orgSpecific !== null) {
                        return $orgSpecific->setting_value;
                    }
                }

                $global = SystemSetting::query()->forKey($key)->forOrg(null)->first();

                return $global?->setting_value ?? $default;
            }
        );
    }

    public function set(string $key, ?int $orgId, mixed $value): SystemSetting
    {
        $setting = SystemSetting::query()->forKey($key)->forOrg($orgId)->first()
            ?? new SystemSetting([
                'setting_key' => $key,
                'org_id' => $orgId ?? SystemSetting::GLOBAL_ORG_ID,
            ]);

        $setting->setting_value = $value;
        $setting->save();

        $this->bustCache($key, $orgId);

        return $setting;
    }

    public function forget(string $key, ?int $orgId): void
    {
        SystemSetting::query()->forKey($key)->forOrg($orgId)->delete();

        $this->bustCache($key, $orgId);
    }

    private function bustCache(string $key, ?int $orgId): void
    {
        if ($orgId === null) {
            // A global-row change can change what every org's previously
            // cached fallback resolves to. Bumping the generation changes
            // every org's cache key for this `$key`, transparently
            // invalidating all of them without enumerating which orgs had
            // cached a fallback.
            $this->bumpGeneration($key);
        }

        Cache::forget($this->cacheKey($key, $orgId));
        Cache::forget($this->cacheKey($key, null));
    }

    private function cacheKey(string $key, ?int $orgId): string
    {
        return "system_setting.{$key}.v{$this->generation($key)}.".($orgId ?? 'global');
    }

    private function generation(string $key): int
    {
        return (int) Cache::get($this->generationKey($key), 1);
    }

    private function bumpGeneration(string $key): void
    {
        Cache::forever($this->generationKey($key), $this->generation($key) + 1);
    }

    private function generationKey(string $key): string
    {
        return "system_setting.{$key}.generation";
    }
}
