<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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

    protected static function booted(): void
    {
        // Files go with the row. (Rows removed by a database cascade are handled by the retention job.)
        static::deleted(function (ListingImage $image): void {
            Storage::disk($image->disk)->delete(array_map(fn (string $variant) => $image->path($variant), $image->variantNames()));
        });
    }

    /** Path of a variant on the disk, e.g. 2026/10/42/{basename}-lg.webp. */
    public function path(string $variant): string
    {
        return "{$this->directory}/{$this->basename}-{$variant}.{$this->format}";
    }

    /** Public URL from the disk configuration, never stored (P2, P6). */
    public function url(string $variant = 'lg'): string
    {
        return Storage::disk($this->disk)->url($this->path($variant));
    }

    /** @return list<string> */
    public function variantNames(): array
    {
        return array_keys((array) config('marketplace.images.variants'));
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
