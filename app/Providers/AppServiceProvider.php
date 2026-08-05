<?php

namespace App\Providers;

use App\Http\View\Composers\NavigationComposer;
use App\Services\Navigation\NavigationRegistry;
use App\Services\Navigation\NavigationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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
        View::composer(['components.layout.sidebar', 'components.layout.topbar'], NavigationComposer::class);
    }
}
