<?php

namespace App\Actions\Listings;

use App\Actions\Listings\Concerns\EditsDrafts;
use App\Enums\ListingStatus;
use App\Exceptions\Listings\CountryChangeNeedsConfirmation;
use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Models\User;
use App\Services\Attributes\AttributeValueWriter;
use App\Services\Listings\ListingCountryChange;
use App\Validation\LocationChain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Wizard steps 1–2 (routes doc §3.1 selectCategory / setCountry / saveLocation): category and
 * location. Creates the draft on first save (daily limit, LST-09) with the country's default
 * currency (GLB-04). On an existing draft:
 * - a new country needs confirmation when values would be cleared (GLB-11), never converted;
 * - a new category clears the details entered for the old one.
 */
class SaveListingLocation
{
    use EditsDrafts;

    public function __construct(
        private readonly ListingCountryChange $countryChange,
        private readonly AttributeValueWriter $writer,
    ) {}

    /**
     * @param  array<string, mixed>  $input  category_id, country_id, region_id, subregion_id, city_id,
     *                                       locality_id, locality_text, confirm_country_change
     *
     * @throws ValidationException
     * @throws CountryChangeNeedsConfirmation
     */
    public function handle(User $user, ?Listing $listing, array $input): Listing
    {
        if ($listing === null) {
            Gate::forUser($user)->authorize('create', Listing::class);
        } else {
            $this->authorizeDraft($user, $listing);
        }

        $validated = Validator::make($input, [
            'category_id' => ['required', 'integer'],
            'country_id' => ['required', 'integer'],
            'region_id' => ['nullable'],
            'subregion_id' => ['nullable'],
            'city_id' => ['nullable'],
            'locality_id' => ['nullable'],
            'locality_text' => ['nullable', 'string', 'max:80', self::plainText()],
        ], [
            'locality_text.not_regex' => __('listings.html_not_allowed'),
        ])->after(new LocationChain)->validate();

        $country = Country::findOrFail((int) $validated['country_id']); // active: checked by LocationChain
        $category = $this->leafCategory((int) $validated['category_id'], $country);

        if ($listing === null) {
            $this->checkDailyLimit($user);
        } elseif ($listing->country_id !== $country->id) {
            $clears = $this->countryChange->impact($listing, $country);
            if ($clears !== [] && ! filter_var($input['confirm_country_change'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                throw new CountryChangeNeedsConfirmation($clears);
            }
        }

        return DB::transaction(function () use ($user, $listing, $validated, $country, $category): Listing {
            if ($listing === null) {
                $listing = new Listing(['category_id' => $category->id]);
                $listing->forceFill([
                    'user_id' => $user->id,
                    'status' => ListingStatus::Draft,
                    'country_id' => $country->id,
                    'currency' => $country->default_currency,
                ]);
            } else {
                $this->countryChange->apply($listing, $country);
                if ($listing->category_id !== $category->id) {
                    $this->changeCategory($listing, $category);
                }
            }

            $listing->fill([
                'region_id' => $validated['region_id'] ?? null,
                'subregion_id' => $validated['subregion_id'] ?? null,
                'city_id' => $validated['city_id'] ?? null,
                'locality_id' => $validated['locality_id'] ?? null,
                'locality_text' => filled($validated['locality_text'] ?? null) ? trim((string) $validated['locality_text']) : null,
            ])->save();

            return $listing->fresh();
        });
    }

    /** Listings are posted to active subcategories offered in the listing's country. */
    private function leafCategory(int $id, Country $country): Category
    {
        $category = Category::with('parent')->find($id);

        if ($category === null || $category->parent_id === null || ! $category->isAvailableIn($country->iso2)) {
            throw ValidationException::withMessages(['category_id' => __('listings.category_unavailable')]);
        }

        return $category;
    }

    /** LST-09: new ads per rolling 24 hours, deleted drafts included (no create/delete loop). */
    private function checkDailyLimit(User $user): void
    {
        $limit = (int) config('marketplace.listings.max_new_per_day');
        $created = Listing::withTrashed()->where('user_id', $user->id)->where('created_at', '>=', now()->subDay())->count();

        if ($created >= $limit) {
            throw ValidationException::withMessages(['category_id' => __('listings.daily_limit', ['limit' => $limit])]);
        }
    }

    private function changeCategory(Listing $listing, Category $category): void
    {
        $listing->category()->associate($category);
        $this->writer->clear($listing);

        // Keep the price option only if the new category offers it.
        if ($listing->price_type !== null && ! $category->allowsPriceType($listing->price_type)) {
            $listing->forceFill(['price_type' => null, 'price' => null]);
        }
        if ($listing->type !== null && ! $category->allowsListingType($listing->type)) {
            $listing->forceFill(['type' => null, 'rent_period' => null]);
        }
    }
}
