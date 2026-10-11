<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds security headers and a nonce-based Content-Security-Policy to every response.
 *
 * Inline scripts and styles must carry the request nonce: `nonce="{{ Vite::cspNonce() }}"`.
 * Extra sources (e.g. a media host) come from config('marketplace.csp'), never from code.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // HSTS only over HTTPS in production. No includeSubDomains: this app must not
        // affect other subdomains of the parent domain.
        if (app()->isProduction() && $request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        $header = config('marketplace.csp.report_only')
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';
        $headers->set($header, $this->contentSecurityPolicy($nonce));

        return $response;
    }

    protected function contentSecurityPolicy(string $nonce): string
    {
        $extra = fn (string $key): array => (array) config("marketplace.csp.{$key}", []);
        $dev = $this->viteDevServerSources();

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", ...$extra('script'), ...$dev],
            'style-src' => ["'self'", "'nonce-{$nonce}'", ...$extra('style'), ...$dev],
            'img-src' => ["'self'", 'data:', 'blob:', ...$extra('img')],
            'font-src' => ["'self'", ...$extra('font'), ...$dev],
            'connect-src' => ["'self'", ...$extra('connect'), ...$dev, ...$this->websocketSources($dev)],
            'frame-src' => [...$extra('frame')] ?: ["'none'"],
            'frame-ancestors' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'object-src' => ["'none'"],
        ];

        $policy = collect($directives)
            ->map(fn (array $sources, string $name): string => $name.' '.implode(' ', array_unique($sources)))
            ->implode('; ');

        return app()->isProduction() ? $policy.'; upgrade-insecure-requests' : $policy;
    }

    /**
     * Allow the Vite dev server only while it is running locally (public/hot exists).
     *
     * @return array<int, string>
     */
    protected function viteDevServerSources(): array
    {
        if (! app()->environment('local') || ! Vite::isRunningHot()) {
            return [];
        }

        $url = rtrim((string) file_get_contents(public_path('hot')));

        return $url !== '' ? [$url] : [];
    }

    /**
     * @param  array<int, string>  $httpSources
     * @return array<int, string>
     */
    protected function websocketSources(array $httpSources): array
    {
        return array_map(fn (string $url): string => preg_replace('#^http#', 'ws', $url), $httpSources);
    }
}
