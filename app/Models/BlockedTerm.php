<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A prohibited or suspicious term (MOD-03). `word` matches whole words, `phrase` matches the text
 * anywhere; `block` refuses the ad, `flag` sends it to review. Null country = every country.
 *
 * @property int $id
 * @property string $term
 * @property string $match
 * @property string $action
 * @property int|null $country_id
 */
class BlockedTerm extends Model
{
    public const MATCH_WORD = 'word';

    public const MATCH_PHRASE = 'phrase';

    public const ACTION_FLAG = 'flag';

    public const ACTION_BLOCK = 'block';

    /** @var list<string> */
    protected $fillable = ['term', 'match', 'action', 'country_id', 'created_by'];

    protected function casts(): array
    {
        return ['country_id' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (BlockedTerm $term): void {
            $term->term = mb_strtolower(trim($term->term));
        });
    }

    /** @param  Builder<BlockedTerm>  $query */
    public function scopeForCountry(Builder $query, int $countryId): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('country_id')->orWhere('country_id', $countryId));
    }
}
