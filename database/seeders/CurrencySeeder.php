<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Services\Geo\GeoNames\CountryInfoReader;
use Illuminate\Database\Seeder;
use NumberFormatter;
use ResourceBundle;

/**
 * Seeds every currency used by a country in the GeoNames snapshot.
 * Names, numeric codes and minor units come from ICU (PHP intl).
 * Existing rows are never modified, so admin changes survive a re-run.
 */
class CurrencySeeder extends Seeder
{
    public function run(CountryInfoReader $reader): void
    {
        $adjustments = require database_path('data/countries.php');
        $names = ResourceBundle::create('en', 'ICUDATA-curr');
        $numeric = ResourceBundle::create('currencyNumericCodes', 'ICUDATA', false);

        $codes = collect($reader->read(database_path('data/geonames/countryInfo.txt')))
            ->reject(fn (array $row) => in_array($row['iso2'], $adjustments['excluded'], true))
            ->pluck('currency')
            ->filter()
            ->unique()
            ->sort();

        foreach ($codes as $code) {
            Currency::firstOrCreate(['code' => $code], [
                'numeric_code' => $this->numericCode($numeric, $code),
                'name' => ['en' => $names['Currencies'][$code][1] ?? $code],
                'minor_units' => $this->minorUnits($code),
                'symbol_override' => $adjustments['currency_symbol_overrides'][$code] ?? null,
                'is_active' => true,
            ]);
        }
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
}
