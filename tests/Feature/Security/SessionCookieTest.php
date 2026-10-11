<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SessionCookieTest extends TestCase
{
    public function test_session_cookie_is_host_only_http_only_and_same_site_lax(): void
    {
        // Session driver is "array" in tests; a web route still issues the cookie.
        Route::middleware('web')->get('/_test/session', fn () => 'ok');

        $response = $this->get('/_test/session');

        $cookie = collect($response->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === 'dtm_session');

        $this->assertNotNull($cookie, 'Session cookie must be named dtm_session');
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertNull($cookie->getDomain(), 'Host-only cookie: never shared with other sites');
    }

    public function test_session_config_defaults(): void
    {
        $this->assertSame('dtm_session', config('session.cookie'));
        $this->assertNull(config('session.domain'));
        $this->assertTrue((bool) config('session.http_only'));
    }
}
