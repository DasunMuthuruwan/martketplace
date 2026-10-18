<?php

namespace App\Services\Geo\GeoNames;

use App\Models\Country;
use App\Models\Currency;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use libphonenumber\PhoneNumberUtil;
use NumberFormatter;
use ResourceBundle;

/**
 * Imports currencies and countries from GeoNames countryInfo.txt, enriched with ICU (currency
 * names, numeric codes, minor units), libphonenumber (calling codes) and PHP's tz database.
 *
 * New rows are created inactive. Existing rows are never touched unless $sync is true, and even
 * then only reference fields change (name, ISO3, GeoNames ID): activation, labels, currency,
 * locale, timezone and requirements stay as the Owner configured them.
 */
class CountryImporter
{
    /** @var array{excluded: list<string>, timezone_overrides: array<string, string>, location_overrides?: array<string, array<string, int|string>>, currency_symbol_overrides: array<string, string>} */
    private array $adjustments;

    public function __construct(private readonly CountryInfoReader $reader)
    {
        $this->adjustments = require database_path('data/countries.php');
    }

    public function importCurrencies(string $countryInfoPath): ImportResult
    {
        $result = new ImportResult;
        $names = ResourceBundle::create('en', 'ICUDATA-curr');
        $numeric = ResourceBundle::create('currencyNumericCodes', 'ICUDATA', false);

        $codes = collect($this->rows($countryInfoPath))->pluck('currency')->filter()->unique()->sort();

        foreach ($codes as $code) {
            $currency = Currency::firstOrCreate(['code' => $code], [
                'numeric_code' => $this->numericCode($numeric, $code),
                'name' => ['en' => $names['Currencies'][$code][1] ?? $code],
                'minor_units' => $this->minorUnits($code),
                'symbol_override' => $this->adjustments['currency_symbol_overrides'][$code] ?? null,
                'is_active' => true,
            ]);

            $currency->wasRecentlyCreated ? $result->created++ : $result->unchanged++;
        }

        return $result;
    }

    public function importCountries(string $countryInfoPath, bool $sync = false): ImportResult
    {
        $result = new ImportResult;
        $phone = PhoneNumberUtil::getInstance();

        foreach ($this->rows($countryInfoPath) as $row) {
            if ($row['currency'] !== null && ! Currency::whereKey($row['currency'])->exists()) {
                $result->skipped++;
                $result->note("{$row['iso2']}: currency {$row['currency']} not imported yet (run the currency import first)");

                continue;
            }

            $country = Country::where('iso2', $row['iso2'])->first();

            if ($country === null) {
                $callingCode = $phone->getCountryCodeForRegion($row['iso2']);

                $country = Country::create([
                    'iso2' => $row['iso2'],
                    'iso3' => $row['iso3'],
                    'name' => ['en' => $row['name']],
                    'slug' => UniqueSlug::make($row['name'], fn (string $slug) => Country::where('slug', $slug)->exists()),
                    'default_currency' => $row['currency'],
                    'number_locale' => 'en_'.$row['iso2'],
                    'phone_country_code' => $callingCode > 0 ? (string) $callingCode : null,
                    'timezone' => $this->adjustments['timezone_overrides'][$row['iso2']] ?? $this->timezoneFor($row['iso2'], $row['capital']),
                    'is_active' => false,
                    'geonames_id' => $row['geonames_id'],
                ] + $this->locationSettings($row['iso2']));
                $result->created++;
            } elseif ($sync && $this->syncReferenceFields($country, $row)) {
                $result->updated++;
            } else {
                $result->unchanged++;
            }

            if ($row['currency'] !== null && $country->wasRecentlyCreated) {
                DB::table('country_currencies')->insertOrIgnore([
                    'country_id' => $country->id,
                    'currency_code' => $row['currency'],
                    'is_default' => true,
                ]);
            }
        }

        return $result;
    }

    /**
     * @param  array{iso2: string, iso3: string, name: string, capital: string, currency: string|null, geonames_id: int|null}  $row
     */
    private function syncReferenceFields(Country $country, array $row): bool
    {
        $country->iso3 = $row['iso3'];
        $country->geonames_id = $row['geonames_id'];
        if ($country->getTranslation('name', 'en', false) !== $row['name']) {
            $country->setTranslation('name', 'en', $row['name']);
        }

        if (! $country->isDirty()) {
            return false;
        }

        $country->save();

        return true;
    }

    /**
     * @return list<array{iso2: string, iso3: string, name: string, capital: string, currency: string|null, geonames_id: int|null}>
     */
    private function rows(string $path): array
    {
        return array_values(array_filter(
            $this->reader->read($path),
            fn (array $row) => ! in_array($row['iso2'], $this->adjustments['excluded'], true),
        ));
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

    private function minorUnits(string $code): int
    {
        $formatter = new NumberFormatter("en@currency={$code}", NumberFormatter::CURRENCY);

        return (int) $formatter->getAttribute(NumberFormatter::FRACTION_DIGITS);
    }

    private function numericCode(?ResourceBundle $bundle, string $code): ?string
    {
        $value = $bundle['codeMap'][$code] ?? null;

        return $value === null ? null : str_pad((string) $value, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Decided location levels and labels (G-3), as country attributes; labels are translatable (English).
     *
     * @return array<string, mixed>
     */
    private function locationSettings(string $iso2): array
    {
        $settings = [];
        foreach ($this->adjustments['location_overrides'][$iso2] ?? [] as $key => $value) {
            $settings[$key] = str_ends_with($key, '_label') ? ['en' => $value] : $value;
        }

        return $settings;
    }
}
