<?php

namespace Tests\Feature\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** S-02: `marketplace:check-production` passes a safe configuration and fails each unsafe setting. */
class ProductionCheckTest extends TestCase
{
    private function safe(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'app.url' => 'https://market.example.org',
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'session.domain' => null,
            'session.driver' => 'database',
            'cache.default' => 'database',
            'mail.default' => 'smtp',
            'mail.from.address' => 'hello@market.example.org',
            'marketplace.csp.report_only' => false,
            'logging.default' => 'daily',
            'logging.channels.daily.level' => 'warning',
            'marketplace.turnstile.site_key' => 'site',
            'marketplace.turnstile.secret_key' => 'secret',
            'queue.default' => 'database',
            'marketplace.mail.sending_enabled' => true,
            'marketplace.trusted_proxies' => [],
        ]);
    }

    public function test_a_safe_configuration_passes_without_printing_secrets(): void
    {
        $this->safe();

        $this->artisan('marketplace:check-production')
            ->expectsOutputToContain('Production configuration checks passed.')
            ->doesntExpectOutputToContain('secret')
            ->doesntExpectOutputToContain(base64_encode(str_repeat('k', 32)))
            ->assertSuccessful();
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function unsafe(): array
    {
        return [
            'local environment' => [['app.env' => 'local'], 'APP_ENV is production'],
            'debug on' => [['app.debug' => true], 'APP_DEBUG is off'],
            'no key' => [['app.key' => ''], 'APP_KEY is set'],
            'plain http' => [['app.url' => 'http://market.example.org'], 'APP_URL uses https'],
            'localhost' => [['app.url' => 'https://localhost'], 'APP_URL is a public host'],
            'cookie not secure' => [['session.secure' => false], 'Session cookie is Secure'],
            'cookie readable by scripts' => [['session.http_only' => false], 'Session cookie is HttpOnly'],
            'cookie sent cross-site' => [['session.same_site' => 'none'], 'SameSite'],
            'cookie shared with the parent domain' => [['session.domain' => '.example.org'], 'host-only'],
            'sessions forgotten' => [['session.driver' => 'array'], 'Sessions are stored'],
            'cache forgotten' => [['cache.default' => 'array'], 'Cache is stored'],
            'mail to a log' => [['mail.default' => 'log'], 'Mail is really sent'],
            'CSP not enforced' => [['marketplace.csp.report_only' => true], 'Content Security Policy is enforced'],
        ];
    }

    /** @param  array<string, mixed>  $change */
    #[DataProvider('unsafe')]
    public function test_each_unsafe_setting_fails_the_check(array $change, string $check): void
    {
        $this->safe();
        config($change);

        $this->artisan('marketplace:check-production')
            ->expectsOutputToContain($check)
            ->expectsOutputToContain('1 check(s) failed')
            ->assertFailed();
    }

    public function test_warnings_do_not_fail_the_check(): void
    {
        $this->safe();
        config(['marketplace.turnstile.site_key' => null, 'logging.channels.daily.level' => 'debug', 'marketplace.trusted_proxies' => ['*']]);

        $this->artisan('marketplace:check-production')
            ->expectsOutputToContain('WARN')
            ->assertSuccessful();
    }
}
