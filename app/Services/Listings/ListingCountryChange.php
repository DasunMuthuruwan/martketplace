<?php

namespace App\Services\Listings;

use App\Models\Country;
use App\Models\Listing;
use App\Models\ListingAttributeValue;
use App\Services\Attributes\AttributeSchema;
use App\Services\Attributes\CategorySchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Changing a draft's country (GLB-11, routes doc §3.1 setCountry): lists what would be cleared
 * (the seller confirms first) and clears it. Nothing is ever converted: the price is removed,
 * the currency becomes the new country's default, and attribute values the new country does not
 * offer (scoped attributes or options, area units it does not use) are removed. The location
 * below the country is always reset.
 */
class ListingCountryChange
{
    public function __construct(private readonly AttributeSchema $schemas) {}

    /**
     * @return array<string, string> key => human-readable label of what will be cleared
     */
    public function impact(Listing $listing, Country $country): array
    {
        if ($listing->country_id === $country->id) {
            return [];
        }

        $clears = [];
        if ($listing->price !== null) {
            $clears['price'] = __('listings.country_change.price');
        }
        if ($listing->region_id !== null || $listing->city_id !== null || $listing->locality_text !== null) {
            $clears['location'] = __('listings.country_change.location');
        }

        $schema = $this->schemas->for($listing->category, $country->iso2);
        foreach ($this->incompatibleValues($listing, $schema) as $value) {
            $clears[$value->attribute->key] = $value->attribute->label;
        }

        return $clears;
    }

    /** Moves the draft to the new country, clearing what does not carry over. */
    public function apply(Listing $listing, Country $country): void
    {
        if ($listing->country_id === $country->id) {
            return;
        }

        $schema = $this->schemas->for($listing->category, $country->iso2);

        DB::transaction(function () use ($listing, $country, $schema): void {
            $incompatible = $this->incompatibleValues($listing, $schema);
            ListingAttributeValue::whereIn('attribute_id', $incompatible->pluck('attribute_id')->unique())
                ->where('listing_id', $listing->id)
                ->delete();

            $listing->forceFill([
                'country_id' => $country->id,
                'currency' => $country->default_currency,
                'price' => null,
                'region_id' => null,
                'subregion_id' => null,
                'city_id' => null,
                'locality_id' => null,
                'locality_text' => null,
            ]);
            $listing->attributes_summary = $this->summaryWithout($listing, $incompatible->pluck('attribute.key')->unique()->all());
            $listing->save();
        });
    }

    /**
     * Values whose attribute, option or area unit the new country's schema does not offer.
     *
     * @return Collection<int, ListingAttributeValue>
     */
    private function incompatibleValues(Listing $listing, CategorySchema $schema): Collection
    {
        return $listing->attributeValues()->with(['attribute', 'option', 'inputUnit'])->get()
            ->filter(function (ListingAttributeValue $value) use ($schema): bool {
                $field = $schema->field($value->attribute->key);
                if ($field === null) {
                    return true;
                }
                if ($value->option !== null && ! in_array($value->option->value, array_column($field['options'], 'value'), true)) {
                    return true;
                }

                return $value->inputUnit !== null && ! in_array($value->inputUnit->code, array_column($field['units'], 'code'), true);
            })
            ->values();
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, mixed>|null
     */
    private function summaryWithout(Listing $listing, array $keys): ?array
    {
        $summary = array_diff_key($listing->attributes_summary ?? [], array_flip($keys));

        return $summary === [] ? null : $summary;
    }
}
