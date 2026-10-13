<?php

namespace App\Services\Search;

use App\Models\Listing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\DB;

/**
 * MySQL/MariaDB search (SRCH-02–05, database design §4, global design §6, §9). Always starts from
 * the public scope. Keywords: words of 3+ characters use the FULLTEXT index (boolean mode, prefix
 * match); shorter words, and every word on databases without FULLTEXT (SQLite in tests), use
 * LIKE on the title and description. Every word must match. Attribute filters are EXISTS
 * sub-queries on the typed EAV indexes. Prices are filtered and sorted only within one currency.
 */
class MysqlListingSearch implements ListingSearch
{
    /** InnoDB / MariaDB default innodb_ft_min_token_size. */
    public const FULLTEXT_MIN_WORD = 3;

    public function search(SearchCriteria $criteria): LengthAwarePaginator
    {
        $query = $this->query($criteria);
        $perPage = (int) config('marketplace.listings.per_page');

        $items = (clone $query)
            ->with(['category', 'country', 'currencyModel', 'coverImage', 'region', 'subregion', 'city'])
            ->forPage($criteria->page, $perPage)
            ->get(['listings.*']);

        return (new Paginator($items, $this->total($query), $perPage, $criteria->page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]))->withQueryString();
    }

    /**
     * Number of results, counted up to one more than can be browsed (max_page × per_page): beyond
     * that the exact figure is never shown ("2,400+") and counting it cost most of the query time
     * for broad searches (T5.4).
     *
     * @param  Builder<Listing>  $query
     */
    public function total(Builder $query): int
    {
        $cap = self::countCap();
        $limited = (clone $query)->toBase()->reorder()->select('listings.id')->limit($cap);

        return (int) DB::query()->fromSub($limited, 'capped')->count();
    }

    /** One more than the deepest result that can be browsed. */
    public static function countCap(): int
    {
        return (int) config('marketplace.listings.max_page') * (int) config('marketplace.listings.per_page') + 1;
    }

    /**
     * The exact query the search runs (also used by `marketplace:perf-report` to EXPLAIN it).
     *
     * @return Builder<Listing>
     */
    public function query(SearchCriteria $criteria): Builder
    {
        $query = Listing::query()->public();

        $this->keyword($query, $criteria->keyword);
        $this->filters($query, $criteria);
        $this->sort($query, $criteria);

        return $query;
    }

    /** @param  Builder<Listing>  $query */
    private function keyword(Builder $query, ?string $keyword): void
    {
        if ($keyword === null) {
            return;
        }

        // Only letters and digits reach SQL: no FULLTEXT operators, no LIKE wildcards.
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($keyword), $matches);
        $words = array_slice(array_values(array_unique($matches[0])), 0, 10);
        if ($words === []) {
            return;
        }

        $fullText = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
        $long = $fullText ? array_filter($words, fn (string $w) => mb_strlen($w) >= self::FULLTEXT_MIN_WORD) : [];
        $short = array_diff($words, $long);

        if ($long !== []) {
            $boolean = implode(' ', array_map(fn (string $w) => '+'.$w.'*', $long));
            $query->whereFullText(['listings.title', 'listings.description'], $boolean, ['mode' => 'boolean']);
        }
        foreach ($short as $word) {
            $query->where(fn (Builder $q) => $q->where('listings.title', 'like', '%'.$word.'%')
                ->orWhere('listings.description', 'like', '%'.$word.'%'));
        }
    }

    /** @param  Builder<Listing>  $query */
    private function filters(Builder $query, SearchCriteria $c): void
    {
        $query
            ->when($c->country !== null, fn ($q) => $q->where('listings.country_id', $c->country->id))
            ->when($c->categoryIds !== [], fn ($q) => $q->whereIn('listings.category_id', $c->categoryIds))
            ->when($c->regionId !== null, fn ($q) => $q->where('listings.region_id', $c->regionId))
            ->when($c->subregionId !== null, fn ($q) => $q->where('listings.subregion_id', $c->subregionId))
            ->when($c->cityIds !== null, fn ($q) => $q->whereIn('listings.city_id', $c->cityIds))
            ->when($c->localityId !== null, fn ($q) => $q->where('listings.locality_id', $c->localityId))
            ->when($c->type !== null, fn ($q) => $q->where('listings.type', $c->type))
            ->when($c->condition !== null, fn ($q) => $q->where('listings.condition', $c->condition));

        if ($c->usesPrice()) {
            // Never compare across currencies (global design §6, scenario 7).
            $query->where('listings.currency', $c->currency)
                ->when($c->minPrice !== null, fn ($q) => $q->where('listings.price', '>=', $c->minPrice))
                ->when($c->maxPrice !== null, fn ($q) => $q->where('listings.price', '<=', $c->maxPrice));
        }

        foreach ($c->attributeFilters as $filter) {
            $query->whereExists(function ($sub) use ($filter): void {
                $sub->selectRaw('1')->from('listing_attribute_values as lav')
                    ->whereColumn('lav.listing_id', 'listings.id')
                    ->where('lav.attribute_id', $filter->attributeId);

                if ($filter->optionIds !== null) {
                    $sub->whereIn('lav.value_option_id', $filter->optionIds === [] ? [0] : $filter->optionIds);
                } elseif ($filter->bool !== null) {
                    $sub->where('lav.value_bool', $filter->bool);
                } else {
                    $sub->when($filter->min !== null, fn ($q) => $q->where('lav.value_number', '>=', $filter->min))
                        ->when($filter->max !== null, fn ($q) => $q->where('lav.value_number', '<=', $filter->max));
                }
            });
        }
    }

    /** @param  Builder<Listing>  $query */
    private function sort(Builder $query, SearchCriteria $c): void
    {
        if ($c->currency !== null && $c->sort !== SearchCriteria::SORT_NEWEST) {
            // Ads without an amount (free, on request) come last in both directions.
            $query->orderByRaw('listings.price IS NULL')
                ->orderBy('listings.price', $c->sort === SearchCriteria::SORT_PRICE_ASC ? 'asc' : 'desc');
        }

        $query->orderByDesc('listings.published_at')->orderByDesc('listings.id');
    }
}
