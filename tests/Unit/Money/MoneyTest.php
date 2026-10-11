<?php

namespace Tests\Unit\Money;

use App\Enums\DisplayDecimals;
use App\Models\Country;
use App\Models\Currency;
use App\Services\Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Formats from the global design §3.3 table (verified against ICU 77.1). ICU separates letter
 * symbols from digits with a no-break space (U+00A0), which keeps "Rs 12,500,000" on one line.
 */
class MoneyTest extends TestCase
{
    private const NBSP = "\u{00A0}";

    private function currency(string $code, int $minorUnits = 2, ?string $symbol = null, DisplayDecimals $display = DisplayDecimals::Auto): Currency
    {
        return new Currency(['code' => $code, 'minor_units' => $minorUnits, 'symbol_override' => $symbol, 'display_decimals' => $display]);
    }

    /** @return array<string, array{string|int|float, string, int, string|null, string, string}> */
    public static function documentedFormats(): array
    {
        return [
            'LKR with Rs override, auto decimals' => ['12500000', 'LKR', 2, 'Rs', 'en_LK', 'Rs'.self::NBSP.'12,500,000'],
            'USD' => ['12500000.00', 'USD', 2, null, 'en_US', '$12,500,000'],
            'GBP' => [12500000, 'GBP', 2, null, 'en_GB', '£12,500,000'],
            'INR lakh grouping' => ['12500000', 'INR', 2, null, 'en_IN', '₹1,25,00,000'],
            'JPY no decimals' => ['12500000', 'JPY', 0, null, 'en_JP', '¥12,500,000'],
            'KWD three decimals' => ['12500.5', 'KWD', 3, null, 'en_KW', 'KWD'.self::NBSP.'12,500.500'],
            'LKR with cents shows them' => ['250000.75', 'LKR', 2, 'Rs', 'en_LK', 'Rs'.self::NBSP.'250,000.75'],
            'rounds half up to minor units' => ['99.995', 'USD', 2, null, 'en_US', '$100'],
            'unknown regional locale falls back' => ['1500', 'USD', 2, null, 'en_ZZ', '$1,500'],
        ];
    }

    #[DataProvider('documentedFormats')]
    public function test_documented_formats(string|int|float $amount, string $code, int $minor, ?string $symbol, string $locale, string $expected): void
    {
        $this->assertSame($expected, app(Money::class)->format($amount, $this->currency($code, $minor, $symbol), $locale));
    }

    public function test_display_decimals_rules(): void
    {
        $money = app(Money::class);

        $this->assertSame('$1,500.00', $money->format('1500', $this->currency('USD', display: DisplayDecimals::Always), 'en_US'));
        $this->assertSame('$1,501', $money->format('1500.5', $this->currency('USD', display: DisplayDecimals::Never), 'en_US'));
        $this->assertSame('$1,500.50', $money->format('1500.5', $this->currency('USD'), 'en_US'));
    }

    public function test_iso_code_is_added_on_cross_country_pages_when_not_shown(): void
    {
        $money = app(Money::class);

        $this->assertSame('$12,500'.self::NBSP.'USD', $money->format('12500', $this->currency('USD'), 'en_US', withCode: true));
        // ICU already shows the code where "$" would be ambiguous (en_AU uses $ for AUD).
        $this->assertSame('USD'.self::NBSP.'12,500', $money->format('12500', $this->currency('USD'), 'en_AU', withCode: true));
    }

    public function test_uses_the_listing_countrys_locale(): void
    {
        $india = new Country(['number_locale' => 'en_IN']);

        $this->assertSame('₹1,25,00,000', app(Money::class)->forCountry('12500000', $this->currency('INR'), $india));
    }

    public function test_missing_or_invalid_amounts_give_null(): void
    {
        $money = app(Money::class);

        $this->assertNull($money->format(null, $this->currency('USD'), 'en_US'));
        $this->assertNull($money->format('lots', $this->currency('USD'), 'en_US'));
    }
}
