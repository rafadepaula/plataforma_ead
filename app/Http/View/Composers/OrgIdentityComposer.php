<?php

namespace App\Http\View\Composers;

use App\Services\OrgContext;
use App\Services\SettingService;
use Illuminate\Contracts\View\View;

/**
 * The brand every shell renders — topbar, sidebar drawer, guest panel and
 * the `<title>` — resolves once here: the request host's Organization
 * (name + logo) for tenants, and the global `system_name` setting in
 * state zero. Admins impersonating see the host brand plus the
 * impersonation badge (which names the impersonated Organization).
 */
final class OrgIdentityComposer
{
    public function __construct(private readonly SettingService $settings) {}

    public function compose(View $view): void
    {
        $context = OrgContext::current();

        if ($context->isStateZero()) {
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
