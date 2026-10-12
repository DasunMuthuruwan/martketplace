<?php

namespace App\Services\Listings;

use App\Models\Attribute;
use App\Models\Listing;
use App\Models\MeasurementUnit;
use App\Services\Money\AreaUnits;
use App\Services\Money\UnitPrice;

/**
 * Calculated price per unit of a listing (LST-14, ADR-018/021), in the country's "price per" unit.
 * Read from the card summary (no joins) so cards, the detail page and search show the same value.
 * Null when the category has no unit price, the country has no "price per" unit or UnitPrice's
 * guards hide it.
 */
class ListingUnitPrice
{
    public function __construct(
        private readonly UnitPrice $unitPrice,
        private readonly AreaUnits $areaUnits,
    ) {}

    /** @return array{amount: string, unit: MeasurementUnit}|null */
    public function for(Listing $listing): ?array
    {
        $attributeId = $listing->category?->unit_price_attribute_id;
        if ($attributeId === null || $listing->type === null || $listing->price_type === null) {
            return null;
        }

        $key = Attribute::whereKey($attributeId)->value('key');
        $area = $listing->attributes_summary[$key] ?? null;
        $target = $this->areaUnits->landPriceUnit($listing->country);
        if (! is_array($area) || $target === null) {
            return null;
        }

        $areaUnit = MeasurementUnit::where('code', $area['unit'] ?? '')->first();
        $currency = $listing->currencyModel;
        if ($currency === null) {
            return null;
        }

        $amount = $this->unitPrice->per($listing->price, $currency, $listing->type, $listing->price_type, $area['value'] ?? null, $areaUnit, $target);

        return $amount === null ? null : ['amount' => $amount, 'unit' => $target];
    }
}
