<?php

namespace Tests\Feature\Geo;

use App\Enums\DisplayDecimals;
use App\Models\Country;
use App\Models\Currency;
use App\Services\Geo\GeoNames\CountryInfoReader;
use Database\Seeders\DatabaseSeeder;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CurrencyAndCountrySeedingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_all_countries_are_seeded_inactive_without_obsolete_codes(): void
    {
        $this->assertSame(250, Country::count());
        $this->assertSame(0, Country::active()->count(), 'Countries start inactive; the Owner activates them');
        $this->assertDatabaseMissing('countries', ['iso2' => 'CS']);
        $this->assertDatabaseMissing('countries', ['iso2' => 'AN']);
    }

    public function test_currencies_come_from_icu_with_iso_minor_units_and_numeric_codes(): void
    {
        $this->assertGreaterThanOrEqual(150, Currency::count());

        $expected = ['LKR' => [2, '144'], 'USD' => [2, '840'], 'GBP' => [2, '826'], 'INR' => [2, '356'], 'JPY' => [0, '392'], 'KWD' => [3, '414']];
        foreach ($expected as $code => [$minorUnits, $numeric]) {
            $currency = Currency::findOrFail($code);
            $this->assertSame($minorUnits, $currency->minor_units, "{$code} minor units");
            $this->assertSame($numeric, $currency->numeric_code, "{$code} numeric code");
            $this->assertSame(DisplayDecimals::Auto, $currency->display_decimals);
        }

        $this->assertSame('Sri Lankan Rupee', Currency::findOrFail('LKR')->name);
        $this->assertSame('Rs', Currency::findOrFail('LKR')->symbol_override);
        $this->assertNull(Currency::findOrFail('USD')->symbol_override);
    }

    public function test_sri_lanka_reference_data(): void
    {
        $lk = Country::where('iso2', 'LK')->firstOrFail();

        $this->assertSame('Sri Lanka', $lk->name);
        $this->assertSame('LKA', $lk->iso3);
        $this->assertSame('sri-lanka', $lk->slug);
        $this->assertSame('LKR', $lk->default_currency);
        $this->assertSame('LKR', $lk->defaultCurrency->code);
        $this->assertSame('94', $lk->phone_country_code);
        $this->assertSame('Asia/Colombo', $lk->timezone);
        $this->assertSame('en_LK', $lk->number_locale);
        $this->assertSame(1227603, $lk->geonames_id);

        $this->assertSame(['LKR'], $lk->currencies->pluck('code')->all());
        $this->assertTrue((bool) $lk->currencies->first()->pivot->is_default);
    }

    public function test_multi_zone_countries_get_a_sensible_default_timezone(): void
    {
        $zones = Country::whereIn('iso2', ['US', 'GB', 'IN', 'AU'])->pluck('timezone', 'iso2');

        $this->assertSame('America/New_York', $zones['US']);
        $this->assertSame('Europe/London', $zones['GB']);
        $this->assertSame('Asia/Kolkata', $zones['IN']);
        $this->assertSame('Australia/Sydney', $zones['AU']);
    }

    public function test_every_timezone_is_a_valid_identifier(): void
    {
        $valid = DateTimeZone::listIdentifiers();

        $invalid = Country::whereNotNull('timezone')->pluck('timezone', 'iso2')
            ->reject(fn (string $zone) => in_array($zone, $valid, true));

        $this->assertSame([], $invalid->all());
    }

    public function test_country_without_a_currency_has_no_default_and_no_allowed_currency(): void
    {
        $aq = Country::where('iso2', 'AQ')->firstOrFail();

        $this->assertNull($aq->default_currency);
        $this->assertNull($aq->phone_country_code);
        $this->assertCount(0, $aq->currencies);
    }

    public function test_every_country_with_a_currency_has_exactly_one_default_allowed_currency(): void
    {
        $withCurrency = Country::whereNotNull('default_currency')->count();
        $defaults = DB::table('country_currencies')->where('is_default', true)->count();

        $this->assertSame($withCurrency, $defaults);
        $this->assertSame(0, DB::table('country_currencies')
            ->join('countries', 'countries.id', '=', 'country_currencies.country_id')
            ->whereColumn('countries.default_currency', '!=', 'country_currencies.currency_code')
            ->count());
    }

    public function test_reseeding_is_idempotent_and_keeps_admin_changes(): void
    {
        Country::where('iso2', 'LK')->update(['is_active' => true, 'timezone' => 'Asia/Kolkata']);
        Currency::whereKey('LKR')->update(['symbol_override' => 'LKR']);
        $counts = [Country::count(), Currency::count(), DB::table('country_currencies')->count()];

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($counts, [Country::count(), Currency::count(), DB::table('country_currencies')->count()]);
        $lk = Country::where('iso2', 'LK')->firstOrFail();
        $this->assertTrue($lk->is_active);
        $this->assertSame('Asia/Kolkata', $lk->timezone);
        $this->assertSame('LKR', Currency::findOrFail('LKR')->symbol_override);
    }

    public function test_location_labels_fall_back_to_generic_terms_until_configured(): void
    {
        $lk = Country::where('iso2', 'LK')->firstOrFail();

        $this->assertSame(__('geo.labels.region'), $lk->label('region'));

        $lk->setTranslation('region_label', 'en', 'Province')->save();

        $this->assertSame('Province', $lk->fresh()->label('region'));
        $this->assertSame(__('geo.labels.city'), $lk->fresh()->label('city'));
    }

    public function test_unknown_label_level_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Country::firstOrFail()->label('street');
    }

    public function test_reader_skips_comments_and_parses_columns(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ci');
        file_put_contents($file, "# comment\n"
            ."LK\tLKA\t144\tCE\tSri Lanka\tColombo\t65610\t21803000\tAS\t.lk\tLKR\tRupee\t94\t#####\t^(\\d{5})$\tsi,ta,en\t1227603\tIN\t\n"
            ."AQ\tATA\t010\tAY\tAntarctica\t\t14000000\t0\tAN\t.aq\t\t\t\t\t\t\t6697173\t\t\n"
            ."bad line\n");

        $rows = (new CountryInfoReader)->read($file);
        unlink($file);

        $this->assertSame([
            ['iso2' => 'LK', 'iso3' => 'LKA', 'name' => 'Sri Lanka', 'capital' => 'Colombo', 'currency' => 'LKR', 'geonames_id' => 1227603],
            ['iso2' => 'AQ', 'iso3' => 'ATA', 'name' => 'Antarctica', 'capital' => '', 'currency' => null, 'geonames_id' => 6697173],
        ], $rows);
    }
}
