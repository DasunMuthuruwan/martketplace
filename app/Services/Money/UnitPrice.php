<?php

namespace App\Services\Money;

use App\Enums\ListingType;
use App\Enums\PriceType;
use App\Models\Currency;
use App\Models\MeasurementUnit;
use App\Support\Decimal;

/**
 * Calculated price per unit of area (ADR-018, generalised by ADR-021), e.g. "250,000 per <unit>".
 * Never entered or stored. Returns null whenever it would be meaningless, so callers simply
 * hide it: no or zero price, no or non-positive area, wanted listings, free / on-request prices,
 * a missing target unit (country without a "price per" unit) or mismatched dimensions.
 */
class UnitPrice
{
    public function __construct(private readonly AreaConverter $converter) {}

    /**
     * @param  string|int|float|null  $price  total price in $currency
     * @param  string|int|float|null  $area  area as entered by the seller, in $areaUnit
     * @return string|null amount in $currency, rounded to its minor units
     */
    public function per(
        string|int|float|null $price,
        Currency $currency,
        ListingType $listingType,
        PriceType $priceType,
        string|int|float|null $area,
        ?MeasurementUnit $areaUnit,
        ?MeasurementUnit $targetUnit,
    ): ?string {
        $price = $price === null ? null : Decimal::parse($price);
        $area = $area === null ? null : Decimal::parse($area);

        if ($price === null || ! Decimal::isPositive($price)
            || $area === null || ! Decimal::isPositive($area)
            || $areaUnit === null || $targetUnit === null || $areaUnit->dimension !== $targetUnit->dimension
            || $listingType === ListingType::Wanted
            || ! in_array($priceType, [PriceType::Fixed, PriceType::Negotiable], true)) {
            return null;
        }

        // Exact when the seller used the target unit; otherwise converted without intermediate rounding.
        $areaInTarget = $areaUnit->code === $targetUnit->code ? $area : $this->converter->exact($area, $areaUnit, $targetUnit);
        if (! Decimal::isPositive($areaInTarget)) {
            return null; // a tiny area that underflows the working precision
        }

        return Decimal::round(Decimal::div($price, $areaInTarget), $currency->minor_units);
    }
}
