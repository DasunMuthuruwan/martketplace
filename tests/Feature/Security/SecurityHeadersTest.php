<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    // `/` reads the countries table (home redirect or country chooser).
    use RefreshDatabase;

    public function test_baseline_security_headers_are_sent(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Permissions-Policy');
    }

    public function test_content_security_policy_uses_a_nonce_and_blocks_framing(): void
    {
        $response = $this->get('/');

        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9]+'/", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringNotContainsString("'unsafe-inline'", $csp);
        $this->assertStringNotContainsString('upgrade-insecure-requests', $csp, 'Only in production');

        preg_match("/'nonce-([A-Za-z0-9]+)'/", $csp, $nonce);
        $this->assertStringContainsString('nonce="'.$nonce[1].'"', $response->getContent(), 'Inline styles carry the request nonce');
    }

    public function test_nonce_changes_on_every_request(): void
    {
        $first = $this->get('/')->headers->get('Content-Security-Policy');
        $second = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertNotSame($first, $second);
    }

    public function test_extra_csp_sources_come_from_config(): void
    {
        config(['marketplace.csp.img' => ['https://media.example.com']]);

        $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("img-src 'self' data: blob: https://media.example.com", $csp);
    }

    public function test_report_only_mode_switches_the_header_name(): void
    {
        config(['marketplace.csp.report_only' => true]);

        $response = $this->get('/');

        $response->assertHeader('Content-Security-Policy-Report-Only');
        $response->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_hsts_is_not_sent_outside_production(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_headers_are_also_sent_on_error_pages(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy');
    }
}
