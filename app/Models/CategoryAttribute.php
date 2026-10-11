<?php

namespace App\Models;

use App\Enums\ItemCondition;
use App\Services\Catalogue\CatalogueCache;
use App\Support\CountryScope;
use Illuminate\Database\Eloquent\Relations\Pivot;
use InvalidArgumentException;

/**
 * How an attribute is used in a category (database design §2.6). A subcategory inherits its
 * parent's links; its own link for the same attribute overrides the parent's.
 *
 * @property int $category_id
 * @property int $attribute_id
 * @property bool $is_required
 * @property list<string>|null $required_for_conditions required only for these item conditions
 * @property bool $is_filterable
 * @property bool $show_on_card
 * @property string|null $group
 * @property int $sort_order
 * @property list<string>|null $scope_countries
 */
class CategoryAttribute extends Pivot
{
    public const COLUMNS = ['is_required', 'required_for_conditions', 'is_filterable', 'show_on_card', 'group', 'sort_order', 'scope_countries'];

    protected $table = 'category_attribute';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'required_for_conditions' => 'array',
            'is_filterable' => 'boolean',
            'show_on_card' => 'boolean',
            'sort_order' => 'integer',
            'scope_countries' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CategoryAttribute $link): void {
            $link->scope_countries = CountryScope::normalise($link->scope_countries);

            $conditions = $link->required_for_conditions;
            if ($conditions !== null) {
                foreach ($conditions as $condition) {
                    if (ItemCondition::tryFrom((string) $condition) === null) {
                        throw new InvalidArgumentException("Unknown item condition [{$condition}].");
                    }
                }
                $link->required_for_conditions = $conditions === [] ? null : array_values(array_unique($conditions));
            }
        });
        static::saved(fn () => CatalogueCache::flush());
        static::deleted(fn () => CatalogueCache::flush());
    }
}
