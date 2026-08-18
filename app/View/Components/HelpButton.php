<?php

namespace App\View\Components;

use App\Enums\Permissions\RolesEnum;
use App\Models\HelpArticle;
use App\Services\HelpArticleResolverService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

/**
 * `<x-help-button key="...">`, present on every
 * authenticated screen (topbar) plus every public screen (Landing Page,
 * `/convite/*`, `/validar-certificado/*`). Resolution mirrors `OrgScope`'s
 * own admin-vs-org-user branching (see `tenancy-conventions`) but reads
 * `session('active_org_id')`/`Auth::user()` directly instead of relying on
 * the scope, because the resolved `org_id` must be compared explicitly
 * inside `HelpArticleResolverService` rather than applied as a query
 * constraint — and because a guest (no `Auth::user()` at all) must resolve
 * to `org_id = null` rather than throwing.
 */
class HelpButton extends Component
{
    public ?HelpArticle $article;

    public function __construct(public string $key)
    {
        $this->article = app(HelpArticleResolverService::class)->resolve($this->key, $this->resolveOrgId());
    }

    public function render(): View
    {
        return view('components.help-button');
    }

    private function resolveOrgId(): ?int
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        if ($user->hasRole(RolesEnum::ADMIN->value)) {
            return session('active_org_id');
        }

        return $user->org_id;
    }
}
