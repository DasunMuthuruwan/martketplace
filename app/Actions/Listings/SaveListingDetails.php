<?php

namespace App\Actions\Listings;

use App\Actions\Listings\Concerns\EditsDrafts;
use App\Enums\ItemCondition;
use App\Enums\ListingType;
use App\Models\Listing;
use App\Models\User;
use App\Services\Attributes\AttributeSchema;
use App\Services\Attributes\AttributeValueWriter;
use App\Services\Attributes\CategorySchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Wizard step 3 (saveDetails): title, description, type, condition and category attributes.
 * Attribute rules come from the AttributeSchema of the listing's category and country, so
 * out-of-scope attributes and options are rejected (global scenario 9).
 */
class SaveListingDetails
{
    use EditsDrafts;

    public const TITLE_MIN = 10;

    public const TITLE_MAX = 100;

    public const DESCRIPTION_MIN = 20;

    public const DESCRIPTION_MAX = 5000;

    public function __construct(
        private readonly AttributeSchema $schemas,
        private readonly AttributeValueWriter $writer,
    ) {}

    /**
     * @param  array<string, mixed>  $input  title, description, type, condition, attributes[...]
     *
     * @throws ValidationException
     */
    public function handle(User $user, Listing $listing, array $input): Listing
    {
        $this->authorizeEdit($user, $listing);

        $category = $listing->category;
        $schema = $this->schemas->for($category, $listing->country->iso2);

        $input['title'] = $this->singleLine($input['title'] ?? null);
        $input['description'] = $this->multiLine($input['description'] ?? null);

        $validated = Validator::make($input, [
            'title' => ['required', 'string', 'min:'.self::TITLE_MIN, 'max:'.self::TITLE_MAX, self::plainText()],
            'description' => ['required', 'string', 'min:'.self::DESCRIPTION_MIN, 'max:'.self::DESCRIPTION_MAX, self::plainText()],
            'type' => ['required', Rule::in($category->allowed_types)],
            'condition' => $category->show_condition
                ? ['nullable', 'required_unless:type,'.ListingType::Wanted->value, Rule::enum(ItemCondition::class)]
                : ['prohibited'],
        ] + $schema->rules(), [
            'title.not_regex' => __('listings.html_not_allowed'),
            'description.not_regex' => __('listings.html_not_allowed'),
            'type.in' => __('listings.type_not_allowed'),
        ], $schema->validationAttributes())->validate();

        DB::transaction(function () use ($listing, $validated, $schema): void {
            $type = ListingType::from($validated['type']);
            $listing->fill([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'type' => $type,
                'condition' => $validated['condition'] ?? null,
            ]);
            if ($type !== ListingType::Rent) {
                $listing->rent_period = null;
            }
            $listing->save();

            $this->writer->write($listing, $schema, $validated[CategorySchema::INPUT] ?? []);
            $this->afterMaterialChange($listing);
        });

        return $listing->fresh();
    }

    /** Trims and collapses whitespace (titles are one line). */
    private function singleLine(mixed $value): mixed
    {
        return is_string($value) ? trim((string) preg_replace('/\s+/u', ' ', $value)) : $value;
    }

    /** Normalises line breaks and trims; at most one empty line between paragraphs. */
    private function multiLine(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = (string) preg_replace("/[ \t]+\n/u", "\n", $value);

        return trim((string) preg_replace("/\n{3,}/u", "\n\n", $value));
    }
}
