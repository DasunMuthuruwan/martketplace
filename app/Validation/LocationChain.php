<?php

namespace App\Validation;

use App\Models\City;
use App\Models\Country;
use App\Models\Locality;
use App\Models\Region;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * Validates a listing location as one chain: country → region → [subregion] → city → [locality].
 *
 * Use as an after-validation hook so each error lands on its own field:
 *   FormRequest::after(): [new LocationChain]   or   Validator::make(...)->after(new LocationChain)
 *
 * Rules (docs/architecture/13-global-marketplace.md §4):
 * - every selected place exists, is active and belongs to its parent and to the country;
 * - the number of region levels and the required levels come from the country;
 * - a child (subregion, city, locality) can't be given without its parent;
 * - messages use the country's own terms (Province, State, County…).
 */
class LocationChain
{
    /**
     * @param  array{country: string, region: string, subregion: string, city: string, locality: string}  $fields
     */
    public function __construct(
        private readonly array $fields = [
            'country' => 'country_id',
            'region' => 'region_id',
            'subregion' => 'subregion_id',
            'city' => 'city_id',
            'locality' => 'locality_id',
        ],
    ) {}

    public function __invoke(Validator $validator): void
    {
        foreach ($this->check($validator->getData()) as $field => $message) {
            if (! $validator->errors()->has($field)) {
                $validator->errors()->add($field, $message);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string> field => message
     */
    public function check(array $data): array
    {
        $errors = [];
        $ids = [];

        foreach ($this->fields as $level => $field) {
            $ids[$level] = $this->parseId($data[$field] ?? null);
            if ($ids[$level] === false) {
                $errors[$field] = $this->message('geo.validation.invalid');
            }
        }
        if ($errors !== []) {
            return $errors;
        }

        $country = $ids['country'] === null ? null : Country::find($ids['country']);
        if ($ids['country'] === null) {
            return [$this->fields['country'] => $this->message('geo.validation.required', ['label' => $this->lower($this->message('geo.labels.country'))])];
        }
        if ($country === null || ! $country->is_active) {
            return [$this->fields['country'] => $this->message('geo.validation.unavailable', ['label' => $this->lower($this->message('geo.labels.country'))])];
        }

        $region = $this->checkRegion($country, $ids, $errors);
        $subregion = $this->checkSubregion($country, $ids, $region, $errors);
        $city = $this->checkCity($country, $ids, $region, $subregion, $errors);
        $this->checkLocality($country, $ids, $city, $errors);

        return $errors;
    }

    /**
     * @param  array<string, int|null>  $ids
     * @param  array<string, string>  $errors
     */
    private function checkRegion(Country $country, array $ids, array &$errors): ?Region
    {
        $field = $this->fields['region'];

        if ($country->region_levels === 0) {
            if ($ids['region'] !== null) {
                $errors[$field] = $this->notApplicable($country);
            }

            return null;
        }

        if ($ids['region'] === null) {
            if ($country->require_region || $ids['subregion'] !== null || $ids['city'] !== null) {
                $errors[$field] = $this->required($country, 'region');
            }

            return null;
        }

        $region = Region::find($ids['region']);
        if ($region === null || $region->country_id !== $country->id || $region->level !== 1) {
            $errors[$field] = $this->message('geo.validation.invalid');

            return null;
        }
        if (! $region->is_active) {
            $errors[$field] = $this->unavailable($country, 'region');

            return null;
        }

        return $region;
    }

    /**
     * @param  array<string, int|null>  $ids
     * @param  array<string, string>  $errors
     */
    private function checkSubregion(Country $country, array $ids, ?Region $region, array &$errors): ?Region
    {
        $field = $this->fields['subregion'];

        if ($country->region_levels < 2) {
            if ($ids['subregion'] !== null) {
                $errors[$field] = $this->notApplicable($country);
            }

            return null;
        }

        if ($ids['subregion'] === null) {
            if ($region !== null && ($country->require_region || $ids['city'] !== null)) {
                $errors[$field] = $this->required($country, 'subregion');
            }

            return null;
        }

        if ($region === null) {
            // Parent missing or invalid: that error is already reported on the region field.
            return null;
        }

        $subregion = Region::find($ids['subregion']);
        if ($subregion === null || $subregion->country_id !== $country->id || $subregion->level !== 2) {
            $errors[$field] = $this->message('geo.validation.invalid');

            return null;
        }
        if ($subregion->parent_id !== $region->id) {
            $errors[$field] = $this->mismatch($country, 'subregion', 'region');

            return null;
        }
        if (! $subregion->is_active) {
            $errors[$field] = $this->unavailable($country, 'subregion');

            return null;
        }

        return $subregion;
    }

    /**
     * @param  array<string, int|null>  $ids
     * @param  array<string, string>  $errors
     */
    private function checkCity(Country $country, array $ids, ?Region $region, ?Region $subregion, array &$errors): ?City
    {
        $field = $this->fields['city'];

        if ($ids['city'] === null) {
            if ($country->require_city || $ids['locality'] !== null) {
                $errors[$field] = $this->required($country, 'city');
            }

            return null;
        }

        $parentLevel = match ($country->region_levels) {
            2 => 'subregion',
            1 => 'region',
            default => null,
        };
        $parent = match ($parentLevel) {
            'subregion' => $subregion,
            'region' => $region,
            default => null,
        };

        if ($parentLevel !== null && $parent === null) {
            // The parent level has its own error (missing, invalid or inactive).
            return null;
        }

        $city = City::find($ids['city']);
        if ($city === null || $city->country_id !== $country->id) {
            $errors[$field] = $this->message('geo.validation.invalid');

            return null;
        }
        if ($city->region_id !== $parent?->id) {
            $errors[$field] = $this->mismatch($country, 'city', $parentLevel ?? 'country');

            return null;
        }
        if (! $city->is_active) {
            $errors[$field] = $this->unavailable($country, 'city');

            return null;
        }

        return $city;
    }

    /**
     * @param  array<string, int|null>  $ids
     * @param  array<string, string>  $errors
     */
    private function checkLocality(Country $country, array $ids, ?City $city, array &$errors): void
    {
        if ($ids['locality'] === null || $city === null) {
            return;
        }

        $field = $this->fields['locality'];
        $locality = Locality::find($ids['locality']);

        if ($locality === null) {
            $errors[$field] = $this->message('geo.validation.invalid');
        } elseif ($locality->city_id !== $city->id) {
            $errors[$field] = $this->mismatch($country, 'locality', 'city');
        } elseif (! $locality->is_active) {
            $errors[$field] = $this->unavailable($country, 'locality');
        }
    }

    /** @return int|false|null null when empty, false when not a positive integer */
    private function parseId(mixed $value): int|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value > 0 ? $value : false;
        }
        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        return false;
    }

    private function required(Country $country, string $level): string
    {
        return $this->message('geo.validation.required', ['label' => $this->lower($country->label($level))]);
    }

    private function unavailable(Country $country, string $level): string
    {
        return $this->message('geo.validation.unavailable', ['label' => $this->lower($country->label($level))]);
    }

    private function mismatch(Country $country, string $level, string $parentLevel): string
    {
        $parent = $parentLevel === 'country' ? $country->name : $this->lower($country->label($parentLevel));

        return $this->message('geo.validation.mismatch', ['label' => $this->lower($country->label($level)), 'parent' => $parent]);
    }

    private function notApplicable(Country $country): string
    {
        return $this->message('geo.validation.not_applicable', ['country' => $country->name]);
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function message(string $key, array $replace = []): string
    {
        $line = __($key, $replace);

        // A key that resolves to a group of lines is a translation bug: show the key, not an array.
        return is_string($line) ? $line : $key;
    }

    private function lower(string $label): string
    {
        return Str::lcfirst($label);
    }
}
