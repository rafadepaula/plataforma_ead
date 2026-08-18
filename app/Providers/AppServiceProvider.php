<?php

namespace App\Providers;

use App\Http\View\Composers\NavigationComposer;
use App\Services\Navigation\ImpersonationContext;
use App\Services\Navigation\NavigationRegistry;
use App\Services\Navigation\NavigationService;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //  singleton so the impersonated Organization is resolved
        // (and memoized) once per request, even though the sidebar and the
        // topbar each trigger the `NavigationComposer`.
        $this->app->singleton(ImpersonationContext::class);

        $this->app->singleton(NavigationRegistry::class);

        $this->app->singleton(NavigationService::class, function ($app): NavigationService {
            return new NavigationService(
                $app->make(NavigationRegistry::class),
                $app->make(Request::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // `Paginator::useBootstrapFive()` was removed in Laravel 11+; the Bootstrap 5
        // pagination views still ship with the framework, so point the paginator at them.
        Paginator::defaultView('pagination::bootstrap-5');
        Paginator::defaultSimpleView('pagination::simple-bootstrap-5');

        View::composer(['components.layout.sidebar', 'components.layout.topbar'], NavigationComposer::class);
    }
}
