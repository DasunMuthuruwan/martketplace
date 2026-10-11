<?php

namespace App\Providers;

use App\Support\RootUrl;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
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

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RootUrl::configure();

        // 10+ characters; in production also refuse passwords found in known breaches (HIBP k-anonymity).
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->max(255)->uncompromised()
            : Password::min(10)->max(255));
    }
}
