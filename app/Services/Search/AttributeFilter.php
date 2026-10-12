<?php

namespace App\Services\Search;

/**
 * One whitelisted attribute filter: option ids (select/multiselect, OR), a numeric range
 * (number/year; area already converted to m²) or a boolean.
 */
final class AttributeFilter
{
    /**
     * @param  list<int>|null  $optionIds
     */
    public function __construct(
        public readonly int $attributeId,
        public readonly ?array $optionIds = null,
        public readonly ?string $min = null,
        public readonly ?string $max = null,
        public readonly ?bool $bool = null,
    ) {}
}
