<?php

namespace App\Http\Controllers;

use App\Services\OrgContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\View as ViewFacade;

/**
 * the public landing page (`GET /`, `landing.show`) — now
 * per-Organization. Each tenant's landing is its own blade, named by
 * `organizations.landing_view` and rendered from
 * `resources/views/tenants/{landing_view}/landing.blade.php`. Fallbacks:
 * - state zero (host unmatched) → straight to the admin login;
 * - an Organization without a usable blade → its own login.
 */
class LandingPageController extends Controller
{
    public function show(): View|RedirectResponse
    {
        $context = OrgContext::current();

        if ($context->isStateZero()) {
            return redirect()->route('login');
        }

        $organization = $context->organization;
        $viewName = filled($organization->landing_view)
            ? 'tenants.'.$organization->landing_view.'.landing'
            : null;

        if ($viewName === null || ! ViewFacade::exists($viewName)) {
            return redirect()->route('login');
        }

        return view($viewName, ['organization' => $organization]);
    }
}
