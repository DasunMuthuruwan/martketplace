<?php

namespace App\Actions\Listings;

use App\Actions\Listings\Concerns\EditsDrafts;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use App\Services\Images\ListingImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Wizard step 5 (routes doc §3.1 updatedPhotos / reorderImages / removeImage / setCover).
 * Every call checks ownership; ids must belong to the listing. The first photo becomes the cover;
 * removing the cover promotes the next photo.
 */
class ManageListingImages
{
    use EditsDrafts;

    public function __construct(private readonly ListingImageProcessor $processor) {}

    /** @throws ValidationException */
    public function add(User $user, Listing $listing, UploadedFile $file): ListingImage
    {
        $this->authorizeDraft($user, $listing);

        return $this->processor->store($user, $listing, $file);
    }

    public function remove(User $user, Listing $listing, int $imageId): void
    {
        $this->authorizeDraft($user, $listing);
        $image = $this->ownedImage($listing, $imageId);

        DB::transaction(function () use ($listing, $image): void {
            $wasCover = $listing->cover_image_id === $image->id;
            if ($wasCover) {
                $listing->forceFill(['cover_image_id' => null])->save();
            }
            $image->delete();
            if ($wasCover) {
                $listing->forceFill(['cover_image_id' => $listing->images()->value('id')])->save();
            }
        });
    }

    /**
     * @param  list<int>  $orderedIds  every image id of the listing, in the new order
     *
     * @throws ValidationException
     */
    public function reorder(User $user, Listing $listing, array $orderedIds): void
    {
        $this->authorizeDraft($user, $listing);

        $ids = array_map('intval', $orderedIds);
        $current = $listing->images()->pluck('id')->all();
        if (count($ids) !== count(array_unique($ids)) || array_diff($ids, $current) !== [] || array_diff($current, $ids) !== []) {
            throw ValidationException::withMessages([ListingImageProcessor::FIELD => __('images.reorder_invalid')]);
        }

        DB::transaction(function () use ($listing, $ids): void {
            foreach ($ids as $position => $id) {
                $listing->images()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });
    }

    public function setCover(User $user, Listing $listing, int $imageId): void
    {
        $this->authorizeDraft($user, $listing);

        $listing->forceFill(['cover_image_id' => $this->ownedImage($listing, $imageId)->id])->save();
    }

    /** @throws ValidationException */
    private function ownedImage(Listing $listing, int $imageId): ListingImage
    {
        $image = $listing->images()->whereKey($imageId)->first();
        if ($image === null) {
            throw ValidationException::withMessages([ListingImageProcessor::FIELD => __('images.not_found')]);
        }

        return $image;
    }
}
