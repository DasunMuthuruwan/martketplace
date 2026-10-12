<?php

namespace App\Actions\Listings;

use App\Actions\Listings\Concerns\EditsDrafts;
use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Enums\PriceMode;
use App\Enums\PriceType;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use App\Rules\PassesTurnstile;
use App\Services\Attributes\AttributeSchema;
use App\Services\Contact\ContactChannels;
use App\Services\Listings\ListingPublisher;
use App\Services\Moderation\ListingScreener;
use App\Services\Moderation\ModerationRules;
use App\Services\Security\Turnstile;
use App\Validation\LocationChain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Wizard step 6 (routes doc §3.1 submit): a draft or rejected ad goes to review or, depending on
 * the moderation mode, straight to public (LST-03, LST-05, CD-06). Checks, in order: Turnstile
 * (fails open: the ad is moderated anyway), completeness of every step, at least one photo
 * (LST-02), a visible contact channel (MSG-03), active-listing limit (LST-09) and the screener
 * (blocked terms refuse; flags force review).
 */
class SubmitListing
{
    use EditsDrafts;

    public function __construct(
        private readonly AttributeSchema $schemas,
        private readonly ContactChannels $contact,
        private readonly ListingScreener $screener,
        private readonly ListingPublisher $publisher,
        private readonly ModerationRules $rules,
    ) {}

    /**
     * @param  array<string, mixed>  $input  the Turnstile token (Turnstile::RESPONSE_FIELD)
     *
     * @throws ValidationException
     */
    public function handle(User $user, Listing $listing, array $input = []): Listing
    {
        $this->authorizeDraft($user, $listing);

        Validator::make($input, [
            Turnstile::RESPONSE_FIELD => [Rule::requiredIf(fn () => app(Turnstile::class)->enabled()), new PassesTurnstile(failOpen: true)],
        ], [Turnstile::RESPONSE_FIELD.'.required' => __('security.turnstile_failed')])->validate();

        $errors = $this->incompleteSteps($listing);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $screening = $this->screener->screen($listing);
        if ($screening->isBlocked()) {
            throw ValidationException::withMessages(['description' => __('moderation.blocked_terms', ['terms' => implode(', ', $screening->blocked)])]);
        }

        return DB::transaction(function () use ($user, $listing, $screening): Listing {
            // Serialise submissions of one seller so the active-listing limit cannot be raced.
            User::whereKey($user->id)->lockForUpdate()->first();
            $this->checkActiveLimit($user, $listing);

            $listing->forceFill(['flagged' => $screening->isFlagged(), 'submitted_at' => now()]);

            if ($this->rules->requiresReview($user, $listing, $screening->isFlagged())) {
                $listing->forceFill(['status' => ListingStatus::PendingReview, 'moderation_note' => null])->save();
            } else {
                $this->publisher->publish($listing);
            }

            return $listing->fresh();
        });
    }

    /** @return array<string, string> field => message for every step that is not finished */
    private function incompleteSteps(Listing $listing): array
    {
        $errors = [];
        $country = $listing->country;
        $category = $listing->category()->with('parent')->first();

        if (! $country->is_active || $category === null || ! $category->isAvailableIn($country->iso2)) {
            $errors['category_id'] = __('listings.category_unavailable');
        }
        $errors += (new LocationChain)->check($listing->only(['country_id', 'region_id', 'subregion_id', 'city_id', 'locality_id']));

        if ($listing->type === null || $listing->title === null || $listing->description === null) {
            $errors['title'] = __('moderation.incomplete.details');
        } elseif ($category !== null && ! $category->allowsListingType($listing->type)) {
            $errors['type'] = __('listings.type_not_allowed');
        } elseif ($category !== null && isset($country->iso2)) {
            $missing = $this->missingAttributes($listing, $category, $country->iso2);
            if ($missing !== []) {
                $errors['attributes'] = __('moderation.incomplete.attributes', ['fields' => implode(', ', $missing)]);
            }
        }

        if ($listing->price_type === null) {
            $errors['price_type'] = __('moderation.incomplete.price');
        } elseif ($category !== null && ! $category->allowsPriceType($listing->price_type)) {
            $errors['price_type'] = __('listings.price_type_not_allowed');
        } elseif ($this->needsAmount($listing) && $listing->price === null) {
            $errors['price'] = __('listings.price_required');
        }
        if ($listing->type === ListingType::Rent && $listing->rent_period === null) {
            $errors['rent_period'] = __('listings.rent_period_required');
        }

        if (! $listing->images()->exists()) {
            $errors['image'] = __('moderation.incomplete.photos');
        }
        if (! $this->contact->hasVisibleChannel($listing)) {
            $errors['contact'] = __('moderation.incomplete.contact');
        }

        return $errors;
    }

    /**
     * Required attributes (including condition-dependent ones) missing from the stored values.
     *
     * @return list<string> labels
     */
    private function missingAttributes(Listing $listing, Category $category, string $iso2): array
    {
        $summary = $listing->attributes_summary ?? [];
        $missing = [];

        foreach ($this->schemas->for($category, $iso2)->form() as $field) {
            $required = $field['required']
                || ($listing->condition !== null && in_array($listing->condition->value, $field['required_for_conditions'] ?? [], true));
            if ($required && ! array_key_exists($field['key'], $summary)) {
                $missing[] = $field['label'];
            }
        }

        return $missing;
    }

    private function needsAmount(Listing $listing): bool
    {
        return in_array($listing->price_type, [PriceType::Fixed, PriceType::Negotiable], true)
            && $listing->type !== ListingType::Wanted
            && $listing->category->price_mode !== PriceMode::Optional;
    }

    private function checkActiveLimit(User $user, Listing $listing): void
    {
        $limit = (int) config('marketplace.listings.max_active_per_user');
        $live = Listing::where('user_id', $user->id)->whereKeyNot($listing->id)
            ->whereIn('status', [ListingStatus::Active->value, ListingStatus::PendingReview->value])
            ->count();

        if ($live >= $limit) {
            throw ValidationException::withMessages(['status' => __('moderation.active_limit', ['limit' => $limit])]);
        }
    }
}
