<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Tests\TestCase;

/**
 * S8.5: CSRF. Laravel skips the check while unit tests run, so this test switches it back on and
 * sends every state-changing route (POST, PUT, PATCH, DELETE: forms, Fortify, Livewire) a request
 * without a token: each must be refused with 419 before anything happens. No route is exempt, and a
 * sibling subdomain (same-site, e.g. another site on the parent domain) is not trusted.
 */
class CsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    /** @return list<Route> */
    private function changingRoutes(): array
    {
        $routes = [];
        foreach (app('router')->getRoutes() as $route) {
            /** @var Route $route */
            if (array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== []) {
                $routes[] = $route;
            }
        }

        return $routes;
    }

    private function uri(Route $route): string
    {
        return '/'.ltrim((string) preg_replace('/\{[^}]+\}/', '1', $route->uri()), '/');
    }

    private function method(Route $route): string
    {
        return array_values(array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']))[0];
    }

    public function test_every_state_changing_route_is_in_the_web_group(): void
    {
        $outside = array_map(fn (Route $r) => $this->uri($r), array_filter($this->changingRoutes(), fn (Route $r) => ! in_array('web', $r->gatherMiddleware(), true)));

        $this->assertSame([], array_values($outside), 'routes without the web group skip CSRF');
        $this->assertGreaterThan(30, count($this->changingRoutes()), 'the route list was read');
    }

    public function test_every_state_changing_route_refuses_a_request_without_a_token(): void
    {
        $user = User::factory()->create();
        $checked = 0;
        foreach ([null, $user] as $actor) {
            foreach ($this->changingRoutes() as $route) {
                $request = $actor ? $this->actingAs($actor) : $this;
                $response = $request->call($this->method($route), $this->uri($route), ['name' => 'x', 'email' => 'x@example.com']);

                $this->assertSame(419, $response->getStatusCode(), $this->method($route).' '.$this->uri($route).($actor ? ' (signed in)' : ' (guest)'));
                $checked++;
            }
        }
        $this->assertSame(2 * count($this->changingRoutes()), $checked);
    }

    public function test_cross_site_and_sibling_subdomain_requests_are_refused(): void
    {
        $user = User::factory()->create();

        foreach (['cross-site', 'same-site', 'none'] as $site) {
            $this->actingAs($user)->post('/logout', [], ['Sec-Fetch-Site' => $site])->assertStatus(419);
        }
        $this->actingAs($user)->post('/logout', ['_token' => 'forged'])->assertStatus(419);
        $this->assertAuthenticatedAs($user);
    }

    public function test_same_origin_requests_and_the_page_token_are_accepted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout', [], ['Sec-Fetch-Site' => 'same-origin'])->assertRedirect();
        $this->assertGuest();

        $this->actingAs($user)->withSession(['_token' => 'page-token'])->post('/logout', ['_token' => 'page-token'])->assertRedirect();
        $this->assertGuest();
    }
}
