<?php

namespace App\Services\Insights;

use App\Models\Country;
use App\Models\Currency;
use App\Services\Search\ListingSearch;
use App\Services\Search\SearchCriteria;
use App\Support\Decimal;

/**
 * Typical asking prices for a search (G4, ADR-027): the middle half (25th–75th percentile) and the
 * median of current ads for sale in the country's currency. Shown only from `price_guide.min_ads`
 * ads; with many ads the extreme 5 % at each end are left out, so a typo price cannot skew it. The
 * values are real asking prices from the ads (no averaging), never converted.
 */
class PriceGuide
{
    private const SAMPLE = 500;

    public function __construct(private readonly ListingSearch $search) {}

    /** @return array{count: int, low: string, median: string, high: string, currency: Currency}|null */
    public function for(SearchCriteria $criteria, Country $country): ?array
    {
        $currency = $country->defaultCurrency;
        if ($currency === null) {
            return null;
        }
        $prices = $this->search->priceSample($criteria, $currency->code, self::SAMPLE);
        $count = count($prices);
        if ($count < (int) config('marketplace.price_guide.min_ads')) {
            return null;
        }
        usort($prices, fn (string $a, string $b) => Decimal::compare($a, $b));
        if ($count >= 20) {
            $cut = (int) floor($count * 0.05);
            $prices = array_slice($prices, $cut, $count - 2 * $cut);
        }
        $pick = fn (float $share) => $prices[(int) floor($share * (count($prices) - 1))];

        return ['count' => $count, 'low' => $pick(0.25), 'median' => $pick(0.5), 'high' => $pick(0.75), 'currency' => $currency];
    }
}
