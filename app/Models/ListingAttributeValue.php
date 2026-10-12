<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One typed attribute value of a listing (database design §2.8). Written only by the attribute
 * value writer (T4.2); a multiselect is one row per option, an area keeps the entered value and
 * unit with a normalised m² copy in value_number.
 *
 * @property int $id
 * @property int $listing_id
 * @property int $attribute_id
 * @property string|null $value_number
 * @property int|null $value_option_id
 * @property string|null $value_text
 * @property bool|null $value_bool
 * @property string|null $input_value
 * @property int|null $input_unit_id
 */
class ListingAttributeValue extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['attribute_id', 'value_number', 'value_option_id', 'value_text', 'value_bool', 'input_value', 'input_unit_id'];

    protected function casts(): array
    {
        return [
            'attribute_id' => 'integer',
            'value_number' => 'decimal:4',
            'value_option_id' => 'integer',
            'value_bool' => 'boolean',
            'input_value' => 'decimal:4',
            'input_unit_id' => 'integer',
        ];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<Attribute, $this> */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    /** @return BelongsTo<AttributeOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(AttributeOption::class, 'value_option_id');
    }

    /** @return BelongsTo<MeasurementUnit, $this> */
    public function inputUnit(): BelongsTo
    {
        return $this->belongsTo(MeasurementUnit::class, 'input_unit_id');
    }
}
