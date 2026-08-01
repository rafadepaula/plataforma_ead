<?php

namespace App\Models;

use Database\Factories\SystemSettingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Directly org-scoped, with a composite primary key `(setting_key, org_id)`.
 *
 * `org_id` uses a `0` sentinel for "global" settings (see the migration's
 * docblock and the `tenancy-maintenance` skill for the full rationale) —
 * a literal nullable `org_id` cannot participate in a MySQL/MariaDB
 * composite `PRIMARY KEY`. `OrgScope` is intentionally NOT applied here:
 * the composite key already scopes lookups explicitly via `forOrg()`, and
 * the trait's `whereRaw('1 = 0')` fallback for org-less non-admin users
 * would incorrectly hide legitimately global (`org_id = 0`) settings.
 *
 * Eloquent does not natively support composite primary keys, so
 * `find()`/`save()`-by-single-key convenience methods are not reliable
 * here — always look up/persist through `forKey()`/`forOrg()`.
 */
class SystemSetting extends Model
{
    /** @use HasFactory<SystemSettingFactory> */
    use HasFactory;

    /**
     * Sentinel `org_id` used for globally-scoped settings.
     */
    public const GLOBAL_ORG_ID = 0;

    public $incrementing = false;

    protected $primaryKey = 'setting_key';

    protected $keyType = 'string';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'org_id' => self::GLOBAL_ORG_ID,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'setting_key',
        'org_id',
        'setting_value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'org_id' => 'integer',
        ];
    }

    /**
     * Scope to a specific setting key.
     *
     * @param  Builder<SystemSetting>  $query
     * @return Builder<SystemSetting>
     */
    public function scopeForKey(Builder $query, string $settingKey): Builder
    {
        return $query->where('setting_key', $settingKey);
    }

    /**
     * Scope to a specific Organization (or the global sentinel when
     * `$orgId` is `null`).
     *
     * @param  Builder<SystemSetting>  $query
     * @return Builder<SystemSetting>
     */
    public function scopeForOrg(Builder $query, ?int $orgId): Builder
    {
        return $query->where('org_id', $orgId ?? self::GLOBAL_ORG_ID);
    }

    /**
     * Match both halves of the composite primary key when saving.
     */
    protected function setKeysForSaveQuery($query)
    {
        return $query
            ->where('setting_key', $this->getAttribute('setting_key'))
            ->where('org_id', $this->getAttribute('org_id') ?? self::GLOBAL_ORG_ID);
    }
}
