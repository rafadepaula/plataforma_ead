<?php

namespace App\View\Components;

use App\Models\HelpArticle;
use App\Services\HelpArticleResolverService;
use App\Services\OrgContext;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-help-button key="...">`, present on every
 * authenticated screen (topbar) plus every public screen (Landing Page,
 * `/convite/*`, `/validar-certificado/*`). Resolution mirrors `OrgScope`'s
 * own admin-vs-org-user branching (see `tenancy-conventions`) but reads
 * `session('active_org_id')` (Admin) / `OrgContext::current()` (everyone
 * else) directly instead of relying on the scope, because the resolved
 * `org_id` must be compared explicitly
 * inside `HelpArticleResolverService` rather than applied as a query
 * constraint — and because a guest (no `Auth::user()` at all) must resolve
 * to `org_id = null` rather than throwing.
 */
class HelpButton extends Component
{
    public ?HelpArticle $article;

    public function __construct(public string $key)
    {
        $resolver = app(HelpArticleResolverService::class);
        $this->article = $resolver->resolve($this->key, $resolver->resolveActiveOrgId());
    }

    public function render(): View
    {
        return view('components.help-button');
    }
}
