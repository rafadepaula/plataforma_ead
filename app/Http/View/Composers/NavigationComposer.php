<?php

namespace App\Http\View\Composers;

use App\Services\Navigation\ImpersonationContext;
use App\Services\Navigation\NavigationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * the single source of navigation data for the layout
 * shell. Bound (in `AppServiceProvider`) to the sidebar and topbar Blade
 * components, it hands each render a `$navigationSections` list already
 * filtered for the acting user.
 *
 * The composer is intentionally thin: every business rule (role
 * filtering, route resolution, badge counting) lives in
 * {@see NavigationService}; this class only forwards the result and a
 * couple of shell-only helpers (the brand URL, login/logout URLs) that
 * the topbar needs regardless of role.
 */
final class NavigationComposer
{
    public function __construct(
        private readonly NavigationService $navigation,
        private readonly ImpersonationContext $impersonation,
    ) {}

    /**
     * Bind navigation data to the view.
     */
    public function compose(View $view): void
    {
        $view->with([
            'navigationSections' => $this->navigation->build(Auth::user()),
            'brandUrl' => $this->brandUrl(),
            'loginUrl' => Route::has('login') ? route('login') : '#',
            'logoutUrl' => Route::has('logout') ? route('logout') : '#',
            //  the topbar badge must never run its own query
            // inside the Blade: it is rendered on every authenticated
            // request, so the resolution lives here (memoized by
            // `ImpersonationContext`) and the view only reads a model.
            'activeOrganization' => $this->impersonation->activeOrganization(Auth::user()),
        ]);
    }

    /**
     * Role-aware brand link: Admin/Gestor land on the dashboard, Alunos
     * (and guests) on "Meus Cursos" / the login screen. Falls back to
     * `/` so the topbar brand is never a dead `#`.
     */
    private function brandUrl(): string
    {
        $user = Auth::user();

        if ($user?->hasRole('admin') || $user?->hasRole('gestor')) {
            return Route::has('admin.dashboard') ? route('admin.dashboard') : '/';
        }

        if ($user?->hasRole('aluno')) {
            return Route::has('student.courses.index') ? route('student.courses.index') : '/';
        }

        return Route::has('login') ? route('login') : '/';
    }
}
