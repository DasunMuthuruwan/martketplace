<?php

namespace App\Services\Insights;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use Illuminate\Support\Facades\DB;

/**
 * Addresses of make/model landing pages (G4, ADR-027): the page for an ad's make and model (linked
 * from the ad page) and every make and model page with live ads in a country (sitemap).
 */
class MakePages
{
    /** @return array{url: string, name: string}|null "More Toyota Aqua ads" for this ad */
    public function forListing(Listing $listing): ?array
    {
        $parent = $listing->category?->parent;
        $keys = $this->attributeIds();
        if ($parent === null || ! isset($keys['make'])) {
            return null;
        }
        $values = DB::table('listing_attribute_values')->where('listing_id', $listing->id)
            ->whereIn('attribute_id', array_values($keys))->whereNotNull('value_option_id')
            ->pluck('value_option_id', 'attribute_id');
        $make = isset($values[$keys['make']]) ? AttributeOption::find($values[$keys['make']]) : null;
        if ($make === null || ! $make->is_active) {
            return null;
        }
        $model = isset($keys['model'], $values[$keys['model']]) ? AttributeOption::find($values[$keys['model']]) : null;
        if ($model !== null && (! $model->is_active || $model->parent_option_id !== $make->id)) {
            $model = null;
        }

        return [
            'url' => route('ads.make', array_filter(['cc' => strtolower($listing->country->iso2), 'category' => $parent->slug,
                'subcategory' => $listing->category->slug, 'make' => $make->value, 'model' => $model?->value])),
            'name' => trim($make->label.' '.($model->label ?? '')),
        ];
    }

    /**
     * Every make page and make/model page with live ads in the country.
     *
     * @return list<string>
     */
    public function urlsFor(Country $country): array
    {
        $keys = $this->attributeIds();
        if (! isset($keys['make'])) {
            return [];
        }
        $cc = strtolower($country->iso2);
        $rows = Listing::query()->public()->where('listings.country_id', $country->id)
            ->join('listing_attribute_values as mk', fn ($j) => $j->on('mk.listing_id', '=', 'listings.id')->where('mk.attribute_id', '=', $keys['make']))
            ->leftJoin('listing_attribute_values as md', fn ($j) => $j->on('md.listing_id', '=', 'listings.id')->where('md.attribute_id', '=', $keys['model'] ?? 0))
            ->whereNotNull('mk.value_option_id')
            ->distinct()
            ->toBase()
            ->get(['listings.category_id', 'mk.value_option_id as make_id', 'md.value_option_id as model_id']);
        $options = AttributeOption::whereIn('id', $rows->pluck('make_id')->merge($rows->pluck('model_id'))->filter()->unique()->all())
            ->where('is_active', true)->get()->keyBy('id');
        $categories = Category::with('parent')->whereIn('id', $rows->pluck('category_id')->unique()->all())->get()->keyBy('id');

        $urls = [];
        foreach ($rows as $row) {
            $category = $categories->get($row->category_id);
            $make = $options->get($row->make_id);
            if ($category?->parent === null || $make === null) {
                continue;
            }
            $base = ['cc' => $cc, 'category' => $category->parent->slug, 'subcategory' => $category->slug, 'make' => $make->value];
            $urls[] = route('ads.make', $base);
            $model = $row->model_id !== null ? $options->get($row->model_id) : null;
            if ($model !== null && $model->parent_option_id === $make->id) {
                $urls[] = route('ads.make', $base + ['model' => $model->value]);
            }
        }

        return array_values(array_unique($urls));
    }

    /** @return array<string, int> attribute key => id, for make and model */
    private function attributeIds(): array
    {
        return Attribute::whereIn('key', ['make', 'model'])->where('is_active', true)->pluck('id', 'key')->map(fn ($id) => (int) $id)->all();
    }
}
