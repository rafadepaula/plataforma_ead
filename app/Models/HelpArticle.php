<?php

namespace App\Models;

use App\Models\Traits\OrgScope;
use Database\Factories\HelpArticleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Directly org-scoped, but `org_id` is nullable: `null` means a global
 * help article (visible to every Organization).
 */
class HelpArticle extends Model
{
    /** @use HasFactory<HelpArticleFactory> */
    use HasFactory, OrgScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'org_id',
        'title',
        'slug',
        'category',
        'target_page_key',
        'content',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }
}
