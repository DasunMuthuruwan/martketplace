<?php

namespace App\Services\Seo;

use App\Enums\ListingType;
use App\Models\Listing;

/**
 * schema.org data for listing pages (SEO-04, global scenario 13): Product with an Offer in the
 * listing's own currency, a PostalAddress without any street (locality, region, ISO country),
 * and a BreadcrumbList. URLs and images come from APP_URL / the listings disk.
 */
class StructuredData
{
    /** @return array<string, mixed> */
    public function product(Listing $listing): array
    {
        $images = $listing->images->map(fn ($image) => $image->url('lg'))->all();

        $address = array_filter([
            '@type' => 'PostalAddress',
            'addressLocality' => $listing->city->name ?? ($listing->locality_text ?: null),
            'addressRegion' => $listing->subregion_id !== null ? $listing->subregion->name : ($listing->region_id !== null ? $listing->region->name : null),
            'addressCountry' => $listing->country->iso2,
        ]);

        $offer = array_filter([
            '@type' => 'Offer',
            'url' => $listing->publicUrl(),
            'price' => $listing->price === null ? null : rtrim(rtrim((string) $listing->price, '0'), '.'),
            'priceCurrency' => $listing->currency,
            'availability' => 'https://schema.org/InStock',
            'itemCondition' => match ($listing->condition?->value) {
                'new' => 'https://schema.org/NewCondition',
                'used' => 'https://schema.org/UsedCondition',
                'reconditioned' => 'https://schema.org/RefurbishedCondition',
                default => null,
            },
            'availableAtOrFrom' => ['@type' => 'Place', 'address' => $address],
        ], fn ($value) => $value !== null);

        return array_filter([
            '@type' => 'Product',
            'name' => $listing->title,
            'description' => mb_substr((string) $listing->description, 0, 500),
            'url' => $listing->publicUrl(),
            'image' => $images ?: null,
            'category' => $listing->category->name,
            // A "wanted" ad is a request, not an offer.
            'offers' => $listing->type === ListingType::Wanted ? null : $offer,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $items  [name, url] from the top
     * @return array<string, mixed>
     */
    public function breadcrumbs(array $items): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $item, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item[0],
                'item' => $item[1],
            ], $items, array_keys($items)),
        ];
    }
}
