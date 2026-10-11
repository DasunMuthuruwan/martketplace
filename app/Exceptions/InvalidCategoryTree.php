<?php

namespace App\Exceptions;

use DomainException;

/** A change that would break the two-level category tree (category tree doc §1). */
class InvalidCategoryTree extends DomainException
{
    public static function selfParent(): self
    {
        return new self('A category cannot be its own parent.');
    }

    public static function tooDeep(): self
    {
        return new self('Categories have at most two levels: the parent must be a top-level category.');
    }

    public static function parentHasChildren(): self
    {
        return new self('A category with subcategories cannot become a subcategory.');
    }

    public static function hasChildren(): self
    {
        return new self('Move or delete the subcategories first.');
    }

    public static function invalidValue(string $field, string $value): self
    {
        return new self("Unknown value [{$value}] for [{$field}].");
    }

    public static function noListingTypes(): self
    {
        return new self('A category must allow at least one listing type.');
    }
}
