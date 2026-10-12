<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A processed listing photo (database design §2.9). Only re-encoded variants are stored
 * (`{basename}-lg.{format}`, `{basename}-sm.{format}`); URLs come from the disk, never the database.
 * Created only by the image pipeline (T4.3).
 *
 * @property int $id
 * @property int|null $listing_id null while attached to an unsaved draft
 * @property int $user_id
 * @property string $disk
 * @property string $directory
 * @property string $basename
 * @property string $format
 * @property int $width
 * @property int $height
 * @property int $bytes
 * @property string $sha256
 * @property int $sort_order
 */
class ListingImage extends Model
{
    /** @var list<string> */
    protected $fillable = ['sort_order'];

    protected function casts(): array
    {
        return [
            'listing_id' => 'integer',
            'user_id' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'bytes' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
