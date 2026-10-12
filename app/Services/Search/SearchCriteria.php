<?php

namespace App\Services\Search;

use App\Models\Category;
use App\Models\Country;

/**
 * A validated, normalised search (SRCH-02–05, global design §6). Built only by
 * SearchCriteriaFactory, so every value here is safe to turn into SQL.
 */
final class SearchCriteria
{
    public const SORT_NEWEST = 'newest';

    public const SORT_PRICE_ASC = 'price_asc';

    public const SORT_PRICE_DESC = 'price_desc';

    /**
     * @param  list<int>  $categoryIds  leaf categories to include (empty = all)
     * @param  list<int>|null  $cityIds  null = no city filter
     * @param  list<AttributeFilter>  $attributeFilters
     * @param  bool  $priceIgnored  price filters or price sort were asked for without a single currency (global search)
     */
    public function __construct(
        public readonly ?Country $country,
        public readonly ?string $keyword,
        public readonly ?Category $category,
        public readonly array $categoryIds,
        public readonly ?int $regionId,
        public readonly ?int $subregionId,
        public readonly ?array $cityIds,
        public readonly ?int $localityId,
        public readonly ?string $type,
        public readonly ?string $condition,
        public readonly ?string $currency,
        public readonly ?string $minPrice,
        public readonly ?string $maxPrice,
        public readonly array $attributeFilters,
        public readonly string $sort,
        public readonly int $page,
        public readonly bool $priceIgnored,
    ) {}

    /** Price filters or sort are applied, so results are limited to $currency. */
    public function usesPrice(): bool
    {
        return $this->currency !== null
            && ($this->minPrice !== null || $this->maxPrice !== null || $this->sort !== self::SORT_NEWEST);
    }
}
