<?php

namespace App\Models;

use App\Models\Traits\AuditableTrait;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use AuditableTrait, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'host',
        'landing_view',
        'cnpj',
        'logo_path',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    /**
     * The per-organization accounts (`credentials`) held under this
     * Organization — the membership list of the tenant. People are unique
     * (`users`); accounts are per-org.
     *
     * @return HasMany<Credential, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Credential::class, 'org_id');
    }

    /**
     * @return HasMany<Course, $this>
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'org_id');
    }

    /**
     * @return HasMany<InvitationLink, $this>
     */
    public function invitationLinks(): HasMany
    {
        return $this->hasMany(InvitationLink::class, 'org_id');
    }

    /**
     * @return HasMany<ForumTopic, $this>
     */
    public function forumTopics(): HasMany
    {
        return $this->hasMany(ForumTopic::class, 'org_id');
    }
}
