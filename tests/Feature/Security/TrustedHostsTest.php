<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\TrustConfiguredHosts;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\TestCase;

class TrustedHostsTest extends TestCase
{
    protected function tearDown(): void
    {
        SymfonyRequest::setTrustedHosts([]);
        parent::tearDown();
    }

    public function test_request_to_the_configured_host_is_served(): void
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST);

        $this->get("http://{$host}/")->assertOk();
    }

    public function test_request_with_a_forged_host_header_is_rejected(): void
    {
        $this->get('http://attacker.example.org/')->assertStatus(400);
    }

    public function test_subdomains_of_the_app_host_are_not_trusted(): void
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST);

        $this->get("http://evil.{$host}/")->assertStatus(400);
    }

    public function test_aliases_are_trusted_only_while_configured(): void
    {
        config(['marketplace.app_aliases' => ['old.example.com']]);

        $this->get('http://old.example.com/')->assertOk();
    }

    public function test_trusted_host_patterns_are_exact_and_escaped(): void
    {
        config([
            'app.url' => 'https://market.example.com',
            'marketplace.app_aliases' => ['https://old-market.example.com', 'legacy.example.com'],
        ]);

        $patterns = $this->app->make(TrustConfiguredHosts::class)->hosts();

        $this->assertSame([
            '^market\.example\.com$',
            '^old\-market\.example\.com$',
            '^legacy\.example\.com$',
        ], $patterns);
    }
}
