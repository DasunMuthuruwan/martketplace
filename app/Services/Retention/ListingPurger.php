<?php

namespace App\Services\Retention;

use App\Models\Listing;
use App\Models\ListingImage;

/**
 * Removes listing photos (files and rows) and whole listings for the retention job. Photos are
 * deleted through the model so their files go too (a database cascade would leave the files).
 * Attribute values, favourites and contact reveals go with the listing by cascade; reports keep
 * their reason and show "no longer exists".
 */
class ListingPurger
{
    /** @return int photos deleted */
    public function deleteImages(Listing $listing): int
    {
        $count = 0;
        foreach (ListingImage::where('listing_id', $listing->id)->get() as $image) {
            $image->delete();
            $count++;
        }

        return $count;
    }

    public function purge(Listing $listing): void
    {
        $this->deleteImages($listing);
        $listing->forceDelete();
    }
}
