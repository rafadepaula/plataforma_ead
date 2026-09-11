<?php

namespace App\Http\View\Composers;

use App\Enums\Permissions\RolesEnum;
use App\Services\OrgContext;
use App\Services\SettingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * The brand every shell renders — topbar, sidebar drawer, guest panel and
 * the `<title>` — resolves once here (spec 9): the request host's
 * Organization (name + logo) for every non-Admin surface, and the global
 * `system_name` setting for the Admin (the platform staff is not "of"
 * any portal — state zero OR a tenant host alike; the impersonation
 * badge still names the Organization being operated).
 */
final class OrgIdentityComposer
{
    public function __construct(private readonly SettingService $settings) {}

    public function compose(View $view): void
    {
        $context = OrgContext::current();
        $isAdmin = Auth::user()?->hasRole(RolesEnum::ADMIN->value) ?? false;

        if ($context->isStateZero() || $isAdmin) {
            $name = $this->settings->get('system_name', null, config('app.name', 'Plataforma EAD'));
            $logoPath = null;
        } else {
            $name = $context->organization->name;
            $logoPath = $context->organization->logo_path;
        }

        $view->with('orgBrand', [
            'name' => $name ?? config('app.name', 'Plataforma EAD'),
            'logoPath' => $logoPath,
        ]);
    }
}
