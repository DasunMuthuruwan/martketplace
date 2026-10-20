<?php

namespace App\Services\Listings;

use App\Enums\PriceType;
use App\Models\Listing;
use App\Services\Attributes\AttributeSchema;
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
        private readonly AttributeSchema $schemas,
    ) {}

    /**
     * Up to three key facts for cards ("2015 · 85000 · Hybrid"), from the category's "on the card"
     * attributes in schema order (components §5.1). Read from the join-free summary: no queries
     * beyond the cached schema.
     *
     * @return list<string>
     */
    public function cardFacts(Listing $listing, int $limit = 3): array
    {
        $summary = $listing->attributes_summary ?? [];
        if ($summary === [] || $listing->category === null || $listing->country === null) {
            return [];
        }

        $facts = [];
        foreach ($this->schemas->for($listing->category, $listing->country->iso2)->cardKeys() as $key) {
            if (array_key_exists($key, $summary) && ($text = $this->attributeValue($summary[$key])) !== '') {
                $facts[] = $text;
            }
            if (count($facts) === $limit) {
                break;
            }
        }

        return $facts;
    }

    /** One value from `attributes_summary` as text. Areas show as entered: the value and its unit. */
    public function attributeValue(mixed $value): string
    {
        return match (true) {
            is_array($value) && isset($value['value'], $value['unit']) => $value['value'].' '.$value['unit'],
            is_array($value) => implode(', ', array_map('strval', $value)),
            is_bool($value) => $value ? (string) __('Yes') : (string) __('No'),
            default => (string) $value,
        };
    }

    /** @param  bool  $withCode  add the ISO code on pages that mix countries */
    public function price(Listing $listing, bool $withCode = false): string
    {
        $parts = $this->priceParts($listing, $withCode);

        return trim($parts['amount'].($parts['suffix'] !== '' ? ' '.$parts['suffix'] : ''));
    }

    /**
     * The figure and its suffix separately, so cards can let "/ month · Negotiable" wrap to the next
     * line while the number never breaks (Q-UX-4). Without a price, the amount is the price type
     * ("Free", "Price on request").
     *
     * @return array{amount: string, suffix: string}
     */
    public function priceParts(Listing $listing, bool $withCode = false): array
    {
        $type = $listing->price_type;
        $currency = $listing->currencyModel;

        if ($listing->price === null || $currency === null) {
            return ['amount' => $type === null ? '' : (string) __('public.price_types.'.$type->value), 'suffix' => ''];
        }

        $suffix = [];
        if ($listing->rent_period !== null) {
            $suffix[] = __('public.rent_periods.'.$listing->rent_period->value);
        }
        if ($type === PriceType::Negotiable) {
            $suffix[] = ($suffix === [] ? '· ' : '').__('public.price_types.negotiable');
        }

        return [
            'amount' => (string) $this->money->forCountry($listing->price, $currency, $listing->country, $withCode),
            'suffix' => implode(' · ', $suffix),
        ];
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
        if ($listing->coverImage !== null) {
            return $listing->coverImage->url($variant);
        }
        // No cover: ads without photos are common (wanted ads, drafts). When the photo count is
        // known, do not query the photos of each card just to find none (F-08).
        if (! $listing->relationLoaded('images') && $listing->getAttribute('images_count') === 0) {
            return null;
        }

        return $listing->images->first()?->url($variant);
    }
}
