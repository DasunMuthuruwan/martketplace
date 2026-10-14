<?php

use App\Http\Middleware\EnforceStaffSessionLimits;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureStaffTwoFactor;
use App\Http\Middleware\ResolveCountry;
use App\Http\Middleware\RunBackgroundTasks;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustConfiguredHosts;
use App\Http\Middleware\TrustConfiguredProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Host and proxy trust come from config (APP_URL, APP_ALIASES, TRUSTED_PROXIES).
        $middleware->replace(TrustProxies::class, TrustConfiguredProxies::class);
        $middleware->prepend(TrustConfiguredHosts::class);
        $middleware->append(SecurityHeaders::class);

        // Suspended/banned users are signed out on their next request (AUTH-09).
        $middleware->appendToGroup('web', EnsureAccountActive::class);
        // Staff: 30-minute idle timeout, 8-hour absolute lifetime, no "remember me" (auth doc §6).
        $middleware->appendToGroup('web', EnforceStaffSessionLimits::class);
        // Optional web runner: drains the queue after the response (ADR-006, WEB_RUNNER_ENABLED).
        $middleware->appendToGroup('web', RunBackgroundTasks::class);
        $middleware->alias(['staff.2fa' => EnsureStaffTwoFactor::class, 'country' => ResolveCountry::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
