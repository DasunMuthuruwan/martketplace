<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate-limits Fortify endpoints that Fortify itself doesn't throttle (registration and password
 * reset). Login, two-factor and verification use Fortify's own `limiters` config.
 * Limiters are defined in FortifyServiceProvider.
 */
class ThrottleAuthEndpoints
{
    /** POST route name => named rate limiter */
    public const LIMITERS = [
        'register.store' => 'register',
        'password.email' => 'password-reset',
        'password.update' => 'password-reset',
    ];

    public function __construct(private readonly ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        $limiter = self::LIMITERS[$request->route()?->getName() ?? ''] ?? null;

        if ($limiter === null || ! $request->isMethod('POST')) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, $limiter);
    }
}
