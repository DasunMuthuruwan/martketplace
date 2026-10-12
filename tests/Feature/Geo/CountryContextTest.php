<?php

namespace Tests\Feature\Geo;

use App\Models\Country;
use App\Services\Geo\CountryContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Visitor country resolution (T1.6, G-2, ADR-023, global scenario 10). */
class CountryContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Country::factory()->create(['iso2' => 'LK', 'iso3' => 'LKA']);
        Country::factory()->create(['iso2' => 'GB', 'iso3' => 'GBR']);
        Country::factory()->create(['iso2' => 'IN', 'iso3' => 'IND']);
        Country::factory()->inactive()->create(['iso2' => 'DE', 'iso3' => 'DEU']);
        config(['marketplace.default_country' => 'LK']);

        // Probe routes: what the app thinks the current country is.
        Route::middleware('web')->group(function () {
            Route::get('/_probe/country', fn (Request $request, CountryContext $context) => $context->current($request)?->iso2 ?? 'none');
            Route::get('/{cc}/_probe', fn (Request $request, CountryContext $context) => $context->current($request)?->iso2.'|'.view()->shared('currentCountry')?->iso2)
                ->middleware('country');
        });
    }

    private function current(array $cookies = [], array $headers = []): string
    {
        return $this->withCookies($cookies)->withHeaders($headers)->get('/_probe/country')->assertOk()->getContent();
    }

    // ---- Resolution order -----------------------------------------------------------------

    public function test_the_site_default_applies_when_nothing_else_is_known(): void
    {
        $this->assertSame('LK', $this->current());

        config(['marketplace.default_country' => 'DE']); // inactive
        $this->assertSame('none', $this->current(), 'Country chooser');

        config(['marketplace.default_country' => null]);
        $this->assertSame('none', $this->current());
    }

    public function test_a_remembered_choice_wins_over_the_default(): void
    {
        $this->assertSame('GB', $this->current(['dtm_country' => 'gb']));
        $this->assertSame('LK', $this->current(['dtm_country' => 'de']), 'Inactive country ignored');
        $this->assertSame('LK', $this->current(['dtm_country' => '../../etc']), 'Malformed value ignored');
    }

    public function test_cloudflare_country_is_used_only_when_enabled_and_from_a_trusted_proxy(): void
    {
        $header = ['CF-IPCountry' => 'IN'];

        $this->assertSame('LK', $this->current([], $header), 'Disabled by default');

        config(['marketplace.geo.use_cf_ip_country' => true]);
        $this->assertSame('LK', $this->current([], $header), 'Not from a trusted proxy: header could be forged');

        config(['marketplace.trusted_proxies' => ['127.0.0.1']]);
        $this->assertSame('IN', $this->current([], $header));
        $this->assertSame('LK', $this->current([], ['CF-IPCountry' => 'XX']), 'Unknown country');
        $this->assertSame('GB', $this->current(['dtm_country' => 'gb'], $header), 'Remembered choice still wins');
    }

    // ---- Country-prefixed routes ------------------------------------------------------------

    public function test_the_url_country_wins_and_is_shared_with_views(): void
    {
        $this->withCookies(['dtm_country' => 'gb'])->get('/in/_probe')->assertOk()->assertSeeText('IN|IN');
    }

    public function test_inactive_or_unknown_countries_are_not_found(): void
    {
        $this->get('/de/_probe')->assertNotFound();
        $this->get('/zz/_probe')->assertNotFound();
        $this->get('/lka/_probe')->assertNotFound();
    }

    public function test_upper_case_codes_redirect_to_the_canonical_lower_case_url(): void
    {
        $this->get('/LK/_probe?page=2')->assertStatus(301)->assertRedirect(url('/lk/_probe?page=2'));
        $this->get('/Lk/_probe')->assertStatus(301)->assertRedirect(url('/lk/_probe'));
    }

    // ---- Choosing a country -----------------------------------------------------------------

    public function test_choosing_a_country_remembers_it(): void
    {
        $response = $this->post(route('country.choose'), ['country' => 'GB']);

        $response->assertRedirect(url('/gb'));
        $cookie = $response->getCookie('dtm_country'); // decrypted (EncryptCookies)
        $this->assertNotNull($cookie);
        $this->assertSame('gb', $cookie->getValue());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertNull($cookie->getDomain(), 'Host-only, like the session cookie');
    }

    public function test_only_active_countries_can_be_chosen(): void
    {
        $this->post(route('country.choose'), ['country' => 'DE'])->assertSessionHasErrors('country');
        $this->post(route('country.choose'), ['country' => ['GB']])->assertSessionHasErrors('country');
    }
}
