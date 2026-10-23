<?php

namespace App\Services\Insights;

use App\Enums\ListingStatus;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Services\Search\MysqlListingSearch;
use App\Services\Search\SearchTerms;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Popular, trending and most-marked-sold lists per country (G2, ADR-027). Aggregates are cached for
 * `trends.cache_minutes`; nothing shows below the minimum counts, so thin data never misleads.
 * Popularity is views only (one per visitor and day): paid placements never count. "Most sold" is
 * what sellers marked as sold, which cannot be verified (E3).
 */
class Trends
{
    /**
     * Most viewed live ads in the last 7 days.
     *
     * @return Collection<int, Listing>
     */
    public function popularAds(Country $country, int $limit = 8): Collection
    {
        $ids = Cache::remember($this->key('popular', $country), $this->ttl(), fn (): array => DB::table('listing_daily_views as v')
            ->join('listings as l', 'l.id', '=', 'v.listing_id')
            ->where('l.country_id', $country->id)
            ->where('v.date', '>=', now()->subDays(6)->toDateString())
            ->groupBy('v.listing_id')
            ->havingRaw('SUM(v.views) >= ?', [(int) config('marketplace.trends.min_views')])
            ->orderByRaw('SUM(v.views) DESC')
            ->orderByDesc('v.listing_id')
            ->limit($limit * 2)
            ->pluck('v.listing_id')->map(fn ($id) => (int) $id)->all());
        if ($ids === []) {
            return new Collection;
        }
        $order = array_flip($ids);

        // Public right now (an ad sold since the aggregate was cached drops out).
        return Listing::public()->whereIn('listings.id', $ids)
            ->with(MysqlListingSearch::CARD_RELATIONS)->withCount('images')->get()
            ->sortBy(fn (Listing $l) => $order[$l->id])->values()->take($limit);
    }

    /**
     * Searches rising this week against last week (counts only, at least `search.min_searches`).
     *
     * @return list<array{term: string, searches: int}>
     */
    public function trendingSearches(Country $country, int $limit = 8): array
    {
        return Cache::remember($this->key('searches', $country), $this->ttl(), function () use ($country, $limit): array {
            $week = now()->subDays(6)->toDateString();
            $rows = DB::table('search_terms')
                ->where('country_id', $country->id)
                ->where('date', '>=', now()->subDays(13)->toDateString())
                ->groupBy('term')
                ->select('term')
                ->selectRaw('SUM(CASE WHEN date >= ? THEN searches ELSE 0 END) as this_week', [$week])
                ->selectRaw('SUM(CASE WHEN date < ? THEN searches ELSE 0 END) as last_week', [$week])
                ->get();

            return $rows->filter(fn ($r) => (int) $r->this_week >= (int) config('marketplace.search.min_searches') && (int) $r->this_week > (int) $r->last_week
                    && ! SearchTerms::looksPersonal((string) $r->term))
                ->sortByDesc(fn ($r) => [(int) $r->this_week - (int) $r->last_week, (int) $r->this_week])
                ->take($limit)
                ->map(fn ($r) => ['term' => (string) $r->term, 'searches' => (int) $r->this_week])
                ->values()->all();
        });
    }

    /**
     * Makes and models whose ads gain the most views this week against last week, in the country
     * (and a category with its subcategories).
     *
     * @return list<array{label: string, views: int}>
     */
    public function trendingModels(Country $country, ?Category $category = null, int $limit = 8): array
    {
        return Cache::remember($this->key('models', $country, $category), $this->ttl(), function () use ($country, $category, $limit): array {
            $model = $this->modelAttributeId();
            if ($model === null) {
                return [];
            }
            $week = now()->subDays(6)->toDateString();
            $rows = DB::table('listing_daily_views as v')
                ->join('listings as l', 'l.id', '=', 'v.listing_id')
                ->join('listing_attribute_values as lav', fn ($j) => $j->on('lav.listing_id', '=', 'l.id')->where('lav.attribute_id', '=', $model))
                ->where('l.country_id', $country->id)
                ->whereNotNull('lav.value_option_id')
                ->when($category !== null, fn ($q) => $q->whereIn('l.category_id', $this->subtree($category)))
                ->where('v.date', '>=', now()->subDays(13)->toDateString())
                ->groupBy('lav.value_option_id')
                ->select('lav.value_option_id as option_id')
                ->selectRaw('SUM(CASE WHEN v.date >= ? THEN v.views ELSE 0 END) as this_week', [$week])
                ->selectRaw('SUM(CASE WHEN v.date < ? THEN v.views ELSE 0 END) as last_week', [$week])
                ->get();
            $rows = $rows->filter(fn ($r) => (int) $r->this_week >= (int) config('marketplace.trends.min_views') && (int) $r->this_week > (int) $r->last_week)
                ->sortByDesc(fn ($r) => [(int) $r->this_week - (int) $r->last_week, (int) $r->this_week])
                ->take($limit);
            $names = $this->modelNames($rows->pluck('option_id')->all());

            return $rows->filter(fn ($r) => isset($names[(int) $r->option_id]))
                ->map(fn ($r) => ['label' => $names[(int) $r->option_id], 'views' => (int) $r->this_week])
                ->values()->all();
        });
    }

    /**
     * Makes and models most often marked as sold by sellers in the last 90 days (E3: unverified).
     *
     * @return list<array{label: string, sold: int}>
     */
    public function mostMarkedSold(Country $country, int $limit = 8): array
    {
        return Cache::remember($this->key('sold', $country), $this->ttl(), function () use ($country, $limit): array {
            $model = $this->modelAttributeId();
            if ($model === null) {
                return [];
            }
            $rows = DB::table('listings as l')
                ->join('listing_attribute_values as lav', fn ($j) => $j->on('lav.listing_id', '=', 'l.id')->where('lav.attribute_id', '=', $model))
                ->where('l.country_id', $country->id)
                ->where('l.status', ListingStatus::Sold->value)
                ->where('l.sold_at', '>=', now()->subDays(90))
                ->whereNull('l.deleted_at')
                ->whereNotNull('lav.value_option_id')
                ->groupBy('lav.value_option_id')
                ->havingRaw('COUNT(*) >= ?', [(int) config('marketplace.trends.min_sold')])
                ->orderByRaw('COUNT(*) DESC')
                ->limit($limit)
                ->get(['lav.value_option_id as option_id', DB::raw('COUNT(*) as sold')]);
            $names = $this->modelNames($rows->pluck('option_id')->all());

            return $rows->filter(fn ($r) => isset($names[(int) $r->option_id]))
                ->map(fn ($r) => ['label' => $names[(int) $r->option_id], 'sold' => (int) $r->sold])
                ->values()->all();
        });
    }

    private function modelAttributeId(): ?int
    {
        $id = Attribute::where('key', 'model')->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * "Toyota Aqua" for model option ids.
     *
     * @param  list<int|string>  $ids
     * @return array<int, string>
     */
    private function modelNames(array $ids): array
    {
        $models = AttributeOption::with('parent')->whereIn('id', $ids)->get();

        return $models->mapWithKeys(fn (AttributeOption $o) => [$o->id => trim(($o->parent->label ?? '').' '.$o->label)])->all();
    }

    /** @return list<int> the category and its subcategories */
    private function subtree(Category $category): array
    {
        return [$category->id, ...Category::where('parent_id', $category->id)->pluck('id')->map(fn ($id) => (int) $id)->all()];
    }

    private function key(string $list, Country $country, ?Category $category = null): string
    {
        return "trends:{$list}:{$country->id}:".($category->id ?? 'all');
    }

    private function ttl(): \DateTimeInterface
    {
        return now()->addMinutes((int) config('marketplace.trends.cache_minutes'));
    }
}
