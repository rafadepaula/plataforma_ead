<?php

namespace App\Models;

use Database\Factories\SystemSettingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

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

    /**
     * `setting_key`s whose `setting_value` holds credential material and
     * must never be persisted in plaintext (see `dashboard-architecture`).
     *
     * @var list<string>
     */
    private const ENCRYPTED_KEYS = ['smtp_password'];

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
     * Transparently encrypts/decrypts `setting_value` for
     * `ENCRYPTED_KEYS` (currently just `smtp_password`) so credential
     * material is never persisted in plaintext, while every other
     * `setting_key` (logo path, SMTP host/port/username, signature, …)
     * passes through untouched.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function settingValue(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value !== null && $this->isEncryptedKey()
                ? Crypt::decryptString($value)
                : $value,
            set: fn (?string $value): ?string => $value !== null && $this->isEncryptedKey()
                ? Crypt::encryptString($value)
                : $value,
        );
    }

    private function isEncryptedKey(): bool
    {
        return in_array($this->getAttribute('setting_key'), self::ENCRYPTED_KEYS, true);
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
