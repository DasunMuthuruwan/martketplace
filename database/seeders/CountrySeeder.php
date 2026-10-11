<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Services\Geo\GeoNames\CountryInfoReader;
use DateTimeZone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use libphonenumber\PhoneNumberUtil;

/**
 * Seeds all countries from the GeoNames snapshot, inactive. Run after CurrencySeeder.
 * Existing rows are never modified, so the Owner's activation and label changes survive a re-run.
 */
class CountrySeeder extends Seeder
{
    public function run(CountryInfoReader $reader): void
    {
        $adjustments = require database_path('data/countries.php');
        $phone = PhoneNumberUtil::getInstance();

        foreach ($reader->read(database_path('data/geonames/countryInfo.txt')) as $row) {
            if (in_array($row['iso2'], $adjustments['excluded'], true)) {
                continue;
            }

            $callingCode = $phone->getCountryCodeForRegion($row['iso2']);

            $country = Country::firstOrCreate(['iso2' => $row['iso2']], [
                'iso3' => $row['iso3'],
                'name' => ['en' => $row['name']],
                'slug' => Str::slug($row['name']),
                'default_currency' => $row['currency'],
                'number_locale' => 'en_'.$row['iso2'],
                'phone_country_code' => $callingCode > 0 ? (string) $callingCode : null,
                'timezone' => $adjustments['timezone_overrides'][$row['iso2']] ?? $this->timezoneFor($row['iso2'], $row['capital']),
                'is_active' => false,
                'geonames_id' => $row['geonames_id'],
            ]);

            if ($row['currency'] !== null) {
                DB::table('country_currencies')->insertOrIgnore([
                    'country_id' => $country->id,
                    'currency_code' => $row['currency'],
                    'is_default' => true,
                ]);
            }
        }
    }

    /**
     * The zone named after the capital, else the first zone of the country.
     */
    private function timezoneFor(string $iso2, string $capital): ?string
    {
        $zones = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $iso2);
        $needle = str_replace(' ', '_', Str::ascii($capital));

        foreach ($zones as $zone) {
            if ($needle !== '' && str_ends_with($zone, '/'.$needle)) {
                return $zone;
            }
        }

        return $zones[0] ?? null;
    }
}
