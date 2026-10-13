<?php

namespace App\Services\Listings;

use App\Enums\PriceType;
use App\Models\Listing;
use App\Services\Money\Money;

/**
 * Display strings shared by cards, the listing page and search results, so they always agree
 * (LST-14: the per-unit price is the same everywhere). Prices use the listing country's locale.
 */
class ListingPresenter
{
    public function __construct(
        private readonly Money $money,
        private readonly ListingUnitPrice $unitPrice,
    ) {}

    /** @param  bool  $withCode  add the ISO code on pages that mix countries */
    public function price(Listing $listing, bool $withCode = false): string
    {
        $type = $listing->price_type;
        $currency = $listing->currencyModel;

        if ($listing->price === null || $currency === null) {
            return $type === null ? '' : (string) __('public.price_types.'.$type->value);
        }

        $text = (string) $this->money->forCountry($listing->price, $currency, $listing->country, $withCode);
        if ($listing->rent_period !== null) {
            $text .= ' '.__('public.rent_periods.'.$listing->rent_period->value);
        }
        if ($type === PriceType::Negotiable) {
            $text .= ' · '.__('public.price_types.negotiable');
        }

        return $text;
    }

    /** e.g. "Rs 250,000 / P" (LST-14); null when it does not apply. */
    public function unitPrice(Listing $listing): ?string
    {
        $result = $this->unitPrice->for($listing);
        if ($result === null || $listing->currencyModel === null) {
            return null;
        }

        return (string) __('public.per_unit', [
            'amount' => $this->money->forCountry($result['amount'], $listing->currencyModel, $listing->country),
            'unit' => $result['unit']->getTranslation('name', app()->getLocale(), true),
        ]);
    }

    /** "Area, City, Region" from the most specific known level; never a street address. */
    public function location(Listing $listing, bool $withCountry = false): string
    {
        $parts = array_filter([
            $listing->locality_id !== null ? $listing->locality->name : $listing->locality_text,
            $listing->city?->name,
            match (true) {
                $listing->subregion_id !== null => $listing->subregion->name,
                $listing->region_id !== null => $listing->region->name,
                default => null,
            },
            $withCountry ? $listing->country?->name : null,
        ]);

        return implode(', ', array_unique($parts));
    }

    public function coverUrl(Listing $listing, string $variant = 'sm'): ?string
    {
        return ($listing->coverImage ?? $listing->images->first())?->url($variant);
    }
}
