<?php

namespace App\Services\Attributes;

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Listing;
use App\Models\MeasurementUnit;
use App\Services\Money\AreaConverter;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

/**
 * Writes validated attribute input (CategorySchema::rules()) as typed rows (database design §2.8)
 * and rebuilds `listings.attributes_summary`, the join-free copy used on cards:
 *   select / multiselect → label(s) (free text for "Other"), number / year → numeric string,
 *   boolean → bool, text → string, area → {value, unit, m2}.
 * Labels are stored in the default locale (English at launch).
 * Always replaces the listing's whole set, so a removed value never lingers.
 */
class AttributeValueWriter
{
    public function __construct(private readonly AreaConverter $converter) {}

    /** @param  array<string, mixed>  $input  the validated `attributes` array */
    public function write(Listing $listing, CategorySchema $schema, array $input): void
    {
        $attributes = Attribute::whereIn('key', $schema->keys())->get()->keyBy('key');
        $rows = [];
        $summary = [];

        foreach ($schema->fields() as $field) {
            $key = $field['key'];
            $value = $input[$key] ?? null;
            $attribute = $attributes->get($key);
            if ($attribute === null || $value === null || $value === '' || $value === []) {
                continue;
            }

            [$fieldRows, $summary[$key]] = $this->rowsFor($attribute, $field, $value, $input);
            foreach ($fieldRows as $row) {
                $rows[] = ['attribute_id' => $attribute->id] + $row;
            }
        }

        DB::transaction(function () use ($listing, $rows, $summary): void {
            $listing->attributeValues()->delete();
            $listing->attributeValues()->createMany($rows);
            $listing->attributes_summary = $summary === [] ? null : $summary;
            $listing->save();
        });
    }

    /**
     * The listing's stored values in the shape write() accepts (the `attributes` input), so an edit
     * form starts from what was saved: option values, "other" + its text, area value + unit code.
     *
     * @return array<string, mixed>
     */
    public function currentInput(Listing $listing, CategorySchema $schema): array
    {
        $byAttribute = $listing->attributeValues()->with(['attribute', 'option', 'inputUnit'])->get()->groupBy('attribute.key');
        $input = [];

        foreach ($schema->fields() as $field) {
            $key = $field['key'];
            $rows = $byAttribute->get($key);
            if ($rows === null || $rows->isEmpty()) {
                continue;
            }
            $row = $rows->first();

            switch (AttributeType::from($field['type'])) {
                case AttributeType::MultiSelect:
                    $input[$key] = $rows->map(fn ($r) => $r->option?->value)->filter()->values()->all();
                    break;
                case AttributeType::Select:
                    if ($row->option !== null) {
                        $input[$key] = $row->option->value;
                    } elseif ($row->value_text !== null) {
                        $input[$key] = AttributeOption::OTHER;
                        $input[$key.CategorySchema::OTHER_SUFFIX] = $row->value_text;
                    }
                    break;
                case AttributeType::Boolean:
                    $input[$key] = $row->value_bool ? '1' : '0';
                    break;
                case AttributeType::Text:
                    $input[$key] = $row->value_text;
                    break;
                case AttributeType::Year:
                case AttributeType::Number:
                    if ($field['dimension'] === MeasurementUnit::AREA && $row->input_value !== null) {
                        $input[$key] = Decimal::trim((string) $row->input_value);
                        $input[$key.CategorySchema::UNIT_SUFFIX] = $row->inputUnit?->code;
                    } else {
                        $input[$key] = $row->value_number === null ? null : Decimal::trim((string) $row->value_number);
                    }
                    break;
            }
        }

        return $input;
    }

    /** Removes every attribute value (category change). */
    public function clear(Listing $listing): void
    {
        DB::transaction(function () use ($listing): void {
            $listing->attributeValues()->delete();
            $listing->attributes_summary = null;
            $listing->save();
        });
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $input
     * @return array{0: list<array<string, mixed>>, 1: mixed}
     */
    private function rowsFor(Attribute $attribute, array $field, mixed $value, array $input): array
    {
        $key = $field['key'];

        return match (AttributeType::from($field['type'])) {
            AttributeType::Text => [[['value_text' => trim((string) $value)]], trim((string) $value)],
            AttributeType::Boolean => [[['value_bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN)]], filter_var($value, FILTER_VALIDATE_BOOLEAN)],
            AttributeType::Year => [[['value_number' => (string) (int) $value]], (string) (int) $value],
            AttributeType::Number => $field['dimension'] === MeasurementUnit::AREA
                ? $this->area($value, (string) $input[$key.CategorySchema::UNIT_SUFFIX])
                : [[['value_number' => Decimal::parse($value)]], Decimal::parse($value)],
            AttributeType::Select => $this->select($attribute, $field, (string) $value, $input),
            AttributeType::MultiSelect => $this->multiSelect($attribute, (array) $value),
        };
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: array{value: string, unit: string, m2: string}}
     */
    private function area(mixed $value, string $unitCode): array
    {
        $unit = MeasurementUnit::where('code', $unitCode)->firstOrFail();
        $entered = (string) Decimal::parse($value);
        $squareMetres = $this->converter->toBase($entered, $unit);

        return [
            [['value_number' => $squareMetres, 'input_value' => $entered, 'input_unit_id' => $unit->id]],
            ['value' => $entered, 'unit' => $unit->code, 'm2' => $squareMetres],
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $input
     * @return array{0: list<array<string, mixed>>, 1: string}
     */
    private function select(Attribute $attribute, array $field, string $value, array $input): array
    {
        if ($value === AttributeOption::OTHER) {
            $text = trim((string) ($input[$field['key'].CategorySchema::OTHER_SUFFIX] ?? ''));

            return [[['value_text' => $text]], $text];
        }

        // A dependent option is identified together with its parent (e.g. "Ranger" under Ford or Hino).
        $parentId = null;
        if ($attribute->depends_on_attribute_id !== null) {
            $parentId = AttributeOption::where('attribute_id', $attribute->depends_on_attribute_id)
                ->where('value', (string) $input[$field['depends_on']])->value('id');
        }

        $option = AttributeOption::where('attribute_id', $attribute->id)
            ->where('parent_option_id', $parentId)
            ->where('value', $value)
            ->firstOrFail();

        return [[['value_option_id' => $option->id]], $option->getTranslation('label', config('app.fallback_locale'))];
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array{0: list<array<string, mixed>>, 1: list<string>}
     */
    private function multiSelect(Attribute $attribute, array $values): array
    {
        $options = AttributeOption::where('attribute_id', $attribute->id)->whereNull('parent_option_id')
            ->whereIn('value', array_map('strval', $values))->orderBy('sort_order')->orderBy('id')->get();

        return [
            $options->map(fn (AttributeOption $option) => ['value_option_id' => $option->id])->all(),
            $options->map(fn (AttributeOption $option) => $option->getTranslation('label', config('app.fallback_locale')))->all(),
        ];
    }
}
