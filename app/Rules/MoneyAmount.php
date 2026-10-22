<?php

namespace App\Rules;

use App\Models\Currency;
use App\Support\Decimal;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A positive amount in a currency: a plain decimal number with no more decimals than the currency
 * has minor units (from the currencies table), at most 15 digits before the point (DECIMAL(18,3)). Used for boost
 * prices; listing prices apply the same checks in SaveListingPrice.
 */
class MoneyAmount implements ValidationRule
{
    private const MAX_INTEGER_DIGITS = 15;

    public function __construct(private readonly Currency $currency) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $amount = Decimal::parse($value);
        if ($amount === null) {
            $fail(__('listings.price_invalid'));

            return;
        }
        if (! Decimal::isPositive($amount)) {
            $fail(__('listings.price_positive'));

            return;
        }
        $minor = (int) $this->currency->minor_units;
        if (Decimal::decimals($amount) > $minor) {
            $fail($minor === 0
                ? __('listings.price_decimals_none', ['currency' => $this->currency->code])
                : __('listings.price_decimals', ['currency' => $this->currency->code, 'decimals' => $minor]));

            return;
        }
        if (strlen(explode('.', $amount)[0]) > self::MAX_INTEGER_DIGITS) {
            $fail(__('listings.price_too_large'));
        }
    }
}
