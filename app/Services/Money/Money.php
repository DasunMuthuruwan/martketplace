<?php

namespace App\Services\Money;

use App\Enums\DisplayDecimals;
use App\Models\Country;
use App\Models\Currency;
use App\Support\Decimal;
use NumberFormatter;

/**
 * Price formatting (global design §3.3): ICU NumberFormatter in the listing country's locale,
 * with the currency's symbol override (a local symbol where ICU shows the ISO code) and the
 * display-decimals rule (auto hides ".00").
 * Amounts are decimal strings; nothing is converted between currencies.
 */
class Money
{
    /** @var array<string, NumberFormatter> */
    private array $formatters = [];

    /**
     * @param  bool  $withCode  append the ISO code when the symbol is ambiguous across countries
     *                          (cross-country pages: "$12,500 USD"); not added when the code is already shown
     */
    public function format(string|int|float|null $amount, Currency $currency, string $locale, bool $withCode = false): ?string
    {
        $value = $amount === null ? null : Decimal::parse($amount);
        if ($value === null) {
            return null;
        }

        $digits = match ($currency->display_decimals) {
            DisplayDecimals::Always => $currency->minor_units,
            DisplayDecimals::Never => 0,
            DisplayDecimals::Auto => Decimal::isWhole(Decimal::round($value, $currency->minor_units)) ? 0 : $currency->minor_units,
        };

        // Round exactly first; ICU then only lays the digits out.
        $rounded = Decimal::round($value, $digits);
        $formatted = $this->formatter($locale, $currency, $digits)->format((float) $rounded);
        if ($formatted === false) {
            return null;
        }

        if ($withCode && ! str_contains($formatted, $currency->code)) {
            $formatted .= "\u{00A0}".$currency->code;
        }

        return $formatted;
    }

    /** Formats in the listing country's locale (G-5). */
    public function forCountry(string|int|float|null $amount, Currency $currency, Country $country, bool $withCode = false): ?string
    {
        return $this->format($amount, $currency, $country->number_locale, $withCode);
    }

    private function formatter(string $locale, Currency $currency, int $digits): NumberFormatter
    {
        $key = $locale.'|'.$currency->code.'|'.$currency->symbol_override.'|'.$digits;

        if (! isset($this->formatters[$key])) {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $currency->code);
            if ($currency->symbol_override !== null && $currency->symbol_override !== '') {
                $formatter->setSymbol(NumberFormatter::CURRENCY_SYMBOL, $currency->symbol_override);
            }
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $digits);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $digits);
            $this->formatters[$key] = $formatter;
        }

        return $this->formatters[$key];
    }
}
