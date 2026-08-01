<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Dusk\DuskServiceProvider as VendorDuskServiceProvider;

/**
 * Explicit local/testing-only registration of Laravel Dusk (SPEC-00 §5).
 *
 * `laravel/dusk` already auto-discovers and self-guards its `_dusk` browser
 * login routes behind `!app()->environment('production')`, but this app
 * provider makes the "never in production" intent explicit at the
 * application level and is the extension point for any Dusk-specific
 * bindings future specs' Browser tests may need (e.g. `Dusk::authenticateUsing`).
 */
class DuskServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('local', 'testing')) {
            $this->app->register(VendorDuskServiceProvider::class);
        }
    }
}
