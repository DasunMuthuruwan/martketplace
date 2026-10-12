<?php

namespace Tests\Support;

use App\Actions\Listings\ManageListingImages;
use App\Actions\Listings\SaveListingDetails;
use App\Actions\Listings\SaveListingLocation;
use App\Actions\Listings\SaveListingPrice;
use App\Enums\ContactVisibility;
use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Builds ads through the real wizard actions, against the seeded catalogue (DatabaseSeeder with
 * LK active and Storage::fake on the listings disk are expected).
 */
trait BuildsListings
{
    protected function sellerWithPhone(): User
    {
        $seller = User::factory()->create(['phone' => '+94771234567', 'phone_country' => 'LK']);
        $seller->forceFill(['contact_visibility' => ContactVisibility::Registered])->save();

        return $seller;
    }

    protected function photo(): UploadedFile
    {
        $image = imagecreatetruecolor(800, 600);
        $path = tempnam(sys_get_temp_dir(), 'ad').'.jpg';
        imagejpeg($image, $path);

        return new UploadedFile($path, 'photo.jpg', null, null, true);
    }

    /** A draft that has gone through every wizard step (a phone ad in Sri Lanka). */
    protected function completeDraft(User $seller, array $details = []): Listing
    {
        $listing = app(SaveListingLocation::class)->handle($seller, null, [
            'category_id' => Category::where('slug', 'mobile-phones')->value('id'),
            'country_id' => Country::where('iso2', 'LK')->value('id'),
        ]);
        $listing = app(SaveListingDetails::class)->handle($seller, $listing, $details + $this->phoneDetails());
        $listing = app(SaveListingPrice::class)->handle($seller, $listing, ['price_type' => 'negotiable', 'price' => '185000']);
        app(ManageListingImages::class)->add($seller, $listing, $this->photo());

        return $listing->fresh();
    }

    /** @return array<string, mixed> */
    protected function phoneDetails(): array
    {
        return [
            'title' => 'iPhone 15 in excellent condition',
            'description' => 'Battery health 95%, always used with a case. Box included.',
            'type' => 'sale',
            'condition' => 'used',
            'attributes' => ['phone_brand' => 'apple', 'phone_model' => 'iphone-15', 'storage' => '128-gb'],
        ];
    }

    /** @return array<string, string> field => first message */
    protected function errors(callable $callback): array
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            return array_map(fn (array $messages) => $messages[0], $e->errors());
        }
        $this->fail('Expected a validation error');
    }
}
