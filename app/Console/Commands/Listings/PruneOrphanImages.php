<?php

namespace App\Console\Commands\Listings;

use App\Models\ListingImage;
use Illuminate\Console\Command;

/**
 * Deletes photos never attached to a listing (security doc §4: orphan uploads after 24 h),
 * with their files. Scheduled daily; safe to run any time.
 */
class PruneOrphanImages extends Command
{
    protected $signature = 'marketplace:prune-images';

    protected $description = 'Delete listing photos that were never attached to a listing';

    public function handle(): int
    {
        $cutoff = now()->subHours((int) config('marketplace.images.orphan_ttl_hours'));
        $deleted = 0;

        ListingImage::whereNull('listing_id')->where('created_at', '<', $cutoff)
            ->chunkById(200, function ($images) use (&$deleted): void {
                foreach ($images as $image) {
                    $image->delete(); // model event removes the files
                    $deleted++;
                }
            });

        $this->info("Deleted {$deleted} orphan photo(s).");

        return self::SUCCESS;
    }
}
