<?php

namespace App\Http\Middleware;

use App\Services\Geo\CountryContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * For country-prefixed routes (`/{cc}/…`, ADR-023): the code must be an active country (404
 * otherwise, global scenario 10), lower-case in the canonical URL (301 from any other case),
 * and becomes the current country for the request and the views.
 */
class ResolveCountry
{
    public function __construct(private readonly CountryContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $code = (string) $request->route('cc');
        $country = $this->context->find($code);
        if ($country === null) {
            abort(404);
        }

        if ($code !== strtolower($code)) {
            $path = preg_replace('#^/'.preg_quote($code, '#').'(?=/|$)#', '/'.strtolower($code), '/'.ltrim($request->path(), '/'));
            $query = $request->getQueryString();

            return redirect()->to(url((string) $path).($query ? '?'.$query : ''), 301);
        }

        $this->context->set($request, $country);
        View::share('currentCountry', $country);

        return $next($request);
    }
}
