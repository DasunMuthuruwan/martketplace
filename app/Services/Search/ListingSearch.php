<?php

namespace App\Services\Search;

use App\Models\Listing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Listing search behind an interface (architecture §2, ADR: MySQL FULLTEXT for the MVP, an
 * external engine such as Meilisearch can be swapped in later without touching callers).
 */
interface ListingSearch
{
    /** @return LengthAwarePaginator<int, Listing> */
    public function search(SearchCriteria $criteria): LengthAwarePaginator;

    /**
     * Which of the given listings the search would return (top ads, ADR-026), keyed by id and
     * loaded for cards.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Listing>
     */
    public function among(SearchCriteria $criteria, array $ids): Collection;
}
