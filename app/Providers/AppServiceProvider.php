<?php

namespace App\Providers;

use App\Support\RootUrl;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Passkeys;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Passkeys stay disabled: they are bound to the domain, which will change (ADR-015).
        Passkeys::ignoreRoutes();

        // No auth endpoints until authentication is configured with our own views (T2.1).
        Fortify::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RootUrl::configure();
    }
}
