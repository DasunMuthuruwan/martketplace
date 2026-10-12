<?php

namespace App\Actions\Listings;

use App\Actions\Listings\Concerns\EditsDrafts;
use App\Enums\ListingType;
use App\Enums\PriceMode;
use App\Enums\PriceType;
use App\Enums\RentPeriod;
use App\Models\Attribute;
use App\Models\Listing;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * Wizard step 4 (savePrice): price option, amount and rent period. The currency is never taken
 * from input: it is the listing country's (GLB-04). Decimals are limited to the currency's minor
 * units (global scenario 2). Categories with a calculated unit price (Land) need the area first;
 * the preview comes from ListingUnitPrice.
 */
class SaveListingPrice
{
    use EditsDrafts;

    /** DECIMAL(18,3): at most 15 digits before the decimal point. */
    private const MAX_INTEGER_DIGITS = 15;

    /**
     * @param  array<string, mixed>  $input  price_type, price, rent_period
     *
     * @throws ValidationException
     */
    public function handle(User $user, Listing $listing, array $input): Listing
    {
        $this->authorizeDraft($user, $listing);

        if ($listing->type === null) {
            throw ValidationException::withMessages(['price' => __('listings.details_first')]);
        }

        $category = $listing->category;
        $currency = $listing->currencyModel;
        $amountTypes = [PriceType::Fixed->value, PriceType::Negotiable->value];
        // A price is optional for "wanted" ads (a budget) and in categories where it is optional.
        $priceOptional = $listing->type === ListingType::Wanted || $category->price_mode === PriceMode::Optional;

        $validated = Validator::make($input, [
            'price_type' => ['required', Rule::in(array_map(fn (PriceType $type) => $type->value, $category->priceTypes()))],
            'price' => array_values(array_filter([
                'nullable',
                $priceOptional ? null : 'required_if:price_type,'.implode(',', $amountTypes),
                'prohibited_unless:price_type,'.implode(',', $amountTypes),
            ])),
            'rent_period' => $listing->type === ListingType::Rent
                ? ['required', Rule::enum(RentPeriod::class)]
                : ['prohibited'],
        ], [
            'price_type.in' => __('listings.price_type_not_allowed'),
            'price.required_if' => __('listings.price_required'),
            'price.prohibited_unless' => __('listings.price_not_applicable'),
            'rent_period.required' => __('listings.rent_period_required'),
        ])->after(function (ValidatorInstance $validator) use ($currency): void {
            $price = $validator->getData()['price'] ?? null;
            if ($price === null || $price === '' || $validator->errors()->has('price')) {
                return;
            }
            if ($message = $this->priceError($price, $currency->code, $currency->minor_units)) {
                $validator->errors()->add('price', $message);
            }
        })->validate();

        $this->requireArea($listing);

        $price = filled($validated['price'] ?? null) ? Decimal::parse($validated['price']) : null;
        $listing->forceFill([
            'price_type' => PriceType::from($validated['price_type']),
            'price' => $price,
            'rent_period' => $validated['rent_period'] ?? null,
        ])->save();

        return $listing->fresh();
    }

    private function priceError(mixed $price, string $currency, int $minorUnits): ?string
    {
        $value = Decimal::parse($price);
        if ($value === null) {
            return __('listings.price_invalid');
        }
        if (! Decimal::isPositive($value)) {
            return __('listings.price_positive');
        }
        if (Decimal::decimals($value) > $minorUnits) {
            return $minorUnits === 0
                ? __('listings.price_decimals_none', ['currency' => $currency])
                : __('listings.price_decimals', ['currency' => $currency, 'decimals' => $minorUnits]);
        }
        if (strlen(explode('.', ltrim($value, '-'))[0]) > self::MAX_INTEGER_DIGITS) {
            return __('listings.price_too_large');
        }

        return null;
    }

    /** Categories with a calculated price per unit (Land) need the area from the details step. */
    private function requireArea(Listing $listing): void
    {
        $attributeId = $listing->category->unit_price_attribute_id;
        if ($attributeId === null) {
            return;
        }

        $attribute = Attribute::find($attributeId);
        if ($attribute !== null && ! isset($listing->attributes_summary[$attribute->key])) {
            throw ValidationException::withMessages(['price' => __('listings.area_required', ['label' => mb_strtolower($attribute->label)])]);
        }
    }
}
