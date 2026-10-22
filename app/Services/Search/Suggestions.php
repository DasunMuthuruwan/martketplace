<?php

namespace App\Services\Search;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Services\Catalogue\CategoryTree;
use App\Support\CountryScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Live search suggestions (G1, ADR-027): what matches the typed text, grouped as categories, makes
 * and models, places and popular searches, each with the address it opens. Country pages suggest
 * within the country; places and categories need a country. Cached per country and text for a few
 * minutes; no personal data.
 */
class Suggestions
{
    public const MIN_LENGTH = 2;

    private const PER_GROUP = 5;

    public function __construct(
        private readonly CategoryTree $tree,
        private readonly SearchTerms $terms,
    ) {}

    /** @return list<array{type: string, label: string, url: string}> */
    public function for(?Country $country, string $typed): array
    {
        $text = SearchTerms::normalise($typed);
        if ($text === null || mb_strlen($text) < self::MIN_LENGTH) {
            return [];
        }

        return Cache::remember('suggest:'.($country->iso2 ?? 'all').':'.md5($text), now()->addMinutes(10), fn () => [
            ...$this->categories($country, $text),
            ...$this->makes($country, $text),
            ...$this->places($country, $text),
            ...$this->popular($country, $text),
        ]);
    }

    /** @return list<array{type: string, label: string, url: string}> */
    private function categories(?Country $country, string $text): array
    {
        if ($country === null) {
            return [];
        }
        $cc = strtolower($country->iso2);
        $found = [];
        foreach ($this->tree->forCountry($country->iso2) as $root) {
            foreach ([$root, ...$root->children] as $category) {
                /** @var Category $category */
                if ($this->matches((string) $category->name, $text)) {
                    $found[] = [
                        'type' => 'category',
                        'label' => $category->parent_id === null ? (string) $category->name : $root->name.' › '.$category->name,
                        'url' => $category->parent_id === null
                            ? route('ads.category', ['cc' => $cc, 'category' => $category->slug])
                            : route('ads.category', ['cc' => $cc, 'category' => $root->slug, 'subcategory' => $category->slug]),
                    ];
                }
            }
        }

        return array_slice($found, 0, self::PER_GROUP);
    }

    /** @return list<array{type: string, label: string, url: string}> */
    private function makes(?Country $country, string $text): array
    {
        $found = [];
        foreach ($this->vehicleNames($country) as $name) {
            if ($this->matches($name, $text)) {
                $found[] = ['type' => 'make', 'label' => $name, 'url' => $this->keywordUrl($country, $name)];
                if (count($found) === self::PER_GROUP) {
                    break;
                }
            }
        }

        return $found;
    }

    /**
     * "Toyota", "Toyota Aqua", … from the make and model options, for the country.
     *
     * @return list<string>
     */
    private function vehicleNames(?Country $country): array
    {
        return Cache::remember('suggest-makes:'.($country->iso2 ?? 'all'), now()->addHour(), function () use ($country): array {
            $attributes = Attribute::whereIn('key', ['make', 'model'])->where('is_active', true)->pluck('id', 'key');
            if ($attributes->isEmpty()) {
                return [];
            }
            $options = AttributeOption::whereIn('attribute_id', $attributes->values())->where('is_active', true)
                ->orderBy('sort_order')->get(['id', 'attribute_id', 'parent_option_id', 'label', 'scope_countries']);
            $inScope = fn (AttributeOption $o) => $country === null || CountryScope::includes($o->scope_countries, $country->iso2);
            $makes = $options->where('attribute_id', $attributes['make'] ?? 0)->filter($inScope)->keyBy('id');
            $names = $makes->map(fn (AttributeOption $o) => (string) $o->label)->values()->all();
            foreach ($options->where('attribute_id', $attributes['model'] ?? 0)->filter($inScope) as $model) {
                $make = $makes->get($model->parent_option_id);
                if ($make !== null) {
                    $names[] = $make->label.' '.$model->label;
                }
            }

            return array_values(array_unique($names));
        });
    }

    /** @return list<array{type: string, label: string, url: string}> */
    private function places(?Country $country, string $text): array
    {
        if ($country === null) {
            return [];
        }

        return City::with('region')
            ->where('country_id', $country->id)->where('is_active', true)
            ->where('ascii_name', 'like', addcslashes(Str::ascii($text), '%_\\').'%')
            ->orderByDesc('population')->orderBy('ascii_name')
            ->limit(self::PER_GROUP)
            ->get()
            ->filter(fn (City $city) => $city->region !== null)
            ->map(fn (City $city) => [
                'type' => 'place',
                'label' => $city->name.', '.$city->region->name,
                'url' => route('ads.location', ['cc' => strtolower($country->iso2), 'region' => $city->region->slug, 'city' => $city->slug]),
            ])->values()->all();
    }

    /** @return list<array{type: string, label: string, url: string}> */
    private function popular(?Country $country, string $text): array
    {
        return array_map(fn (array $row) => [
            'type' => 'popular',
            'label' => $row['term'],
            'url' => $this->keywordUrl($country, $row['term']),
        ], $this->terms->popular($country, $text, 30, self::PER_GROUP));
    }

    /** A word of the name starts with the text, or the whole name does ("aq" → "Toyota Aqua"). */
    private function matches(string $name, string $text): bool
    {
        $name = mb_strtolower($name);
        if (str_starts_with($name, $text)) {
            return true;
        }
        foreach (preg_split('/[\s›\-\/]+/u', $name) ?: [] as $word) {
            if ($word !== '' && str_starts_with($word, $text)) {
                return true;
            }
        }

        return false;
    }

    private function keywordUrl(?Country $country, string $keyword): string
    {
        return $country === null
            ? route('ads.global', ['q' => $keyword])
            : route('ads.country', ['cc' => strtolower($country->iso2), 'q' => $keyword]);
    }
}
