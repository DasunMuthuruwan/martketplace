<?php

namespace App\Services\Search;

use App\Models\Listing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Listing search behind an interface (architecture §2, ADR: MySQL FULLTEXT for the MVP, an
 * external engine such as Meilisearch can be swapped in later without touching callers).
 */
interface ListingSearch
{
    /** @return LengthAwarePaginator<int, Listing> */
    public function search(SearchCriteria $criteria): LengthAwarePaginator;
}
