<?php

namespace App\Services\Attributes;

use App\Enums\AttributeType;
use App\Models\AttributeOption;
use App\Rules\DependentOption;
use Illuminate\Validation\Rule;

/**
 * The attribute schema of one leaf category in one country (built by AttributeSchema).
 * Input shape: `attributes[<key>]`, plus `attributes[<key>__other]` for a free-text "Other".
 * rules() and form() read the same field definitions.
 */
final class CategorySchema
{
    public const INPUT = 'attributes';

    public const OTHER_SUFFIX = '__other';

    public const OTHER_MAX_LENGTH = 80;

    public const DEFAULT_TEXT_MAX_LENGTH = 120;

    public const DEFAULT_MIN_YEAR = 1900;

    /** @param  list<array<string, mixed>>  $fields */
    public function __construct(private readonly array $fields) {}

    /** @return list<array<string, mixed>> */
    public function fields(): array
    {
        return $this->fields;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_column($this->fields, 'key');
    }

    /** @return array<string, mixed>|null */
    public function field(string $key): ?array
    {
        foreach ($this->fields as $field) {
            if ($field['key'] === $key) {
                return $field;
            }
        }

        return null;
    }

    /** @return list<string> keys usable as search filters */
    public function filterableKeys(): array
    {
        return array_column(array_filter($this->fields, fn (array $f) => $f['filterable']), 'key');
    }

    /** @return list<string> keys shown on listing cards */
    public function cardKeys(): array
    {
        return array_column(array_filter($this->fields, fn (array $f) => $f['show_on_card']), 'key');
    }

    /**
     * Validation rules for the `attributes` input. The listing's `condition` field (if any) makes
     * condition-dependent attributes required (e.g. mileage for used vehicles).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $allowedKeys = [];
        $rules = [];

        foreach ($this->fields as $field) {
            $key = $field['key'];
            $name = self::INPUT.'.'.$key;
            $type = AttributeType::from($field['type']);
            $allowedKeys[] = $key;

            $rules[$name] = array_merge([$this->presenceRule($field)], $this->typeRules($field, $type));

            if ($type === AttributeType::MultiSelect) {
                $rules[$name.'.*'] = ['string', 'distinct', Rule::in(array_column($field['options'], 'value'))];
            }

            if ($type === AttributeType::Select && $field['allows_other']) {
                $allowedKeys[] = $key.self::OTHER_SUFFIX;
                $rules[$name.self::OTHER_SUFFIX] = [
                    'nullable',
                    'required_if:'.$name.','.AttributeOption::OTHER,
                    'prohibited_unless:'.$name.','.AttributeOption::OTHER,
                    'string',
                    'max:'.self::OTHER_MAX_LENGTH,
                ];
            }
        }

        return [self::INPUT => ['nullable', 'array:'.implode(',', $allowedKeys)]] + $rules;
    }

    /**
     * Human-readable names for validation messages, in the current locale.
     *
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        $names = [];
        foreach ($this->fields as $field) {
            $label = $this->translate($field['label']) ?? $field['key'];
            $names[self::INPUT.'.'.$field['key']] = $label;
            $names[self::INPUT.'.'.$field['key'].'.*'] = $label;
            $names[self::INPUT.'.'.$field['key'].self::OTHER_SUFFIX] = $label;
        }

        return $names;
    }

    /**
     * Form definition in the current locale (consumed by the posting wizard and filters).
     *
     * @return list<array<string, mixed>>
     */
    public function form(): array
    {
        return array_map(function (array $field) {
            $type = AttributeType::from($field['type']);

            return [
                'key' => $field['key'],
                'type' => $field['type'],
                'label' => $this->translate($field['label']) ?? $field['key'],
                'help' => $this->translate($field['help']),
                'unit' => $field['unit'],
                'required' => $field['required'],
                'required_for_conditions' => $field['required_for_conditions'],
                'group' => $field['group'],
                'depends_on' => $field['depends_on'],
                'allows_other' => $field['allows_other'],
                'min' => $this->min($field, $type),
                'max' => $this->max($field, $type),
                'step' => $field['rules']['step'] ?? null,
                'options' => array_map(fn (array $option) => [
                    'value' => $option['value'],
                    'label' => $this->translate($option['label']) ?? $option['value'],
                    'parent' => $option['parent'],
                ], $field['options']),
            ];
        }, $this->fields);
    }

    /** @param  array<string, mixed>  $field */
    private function presenceRule(array $field): mixed
    {
        if ($field['required']) {
            return 'required';
        }
        if (! empty($field['required_for_conditions'])) {
            return 'required_if:condition,'.implode(',', $field['required_for_conditions']);
        }

        return 'nullable';
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<mixed>
     */
    private function typeRules(array $field, AttributeType $type): array
    {
        $values = array_column($field['options'], 'value');

        return match ($type) {
            AttributeType::Text => ['string', 'max:'.($field['rules']['maxlength'] ?? self::DEFAULT_TEXT_MAX_LENGTH)],
            AttributeType::Number => array_filter([
                $this->isWholeNumber($field) ? 'integer' : 'numeric',
                'min:'.$this->min($field, $type),
                ($max = $this->max($field, $type)) !== null ? 'max:'.$max : null,
            ]),
            AttributeType::Year => ['integer', 'between:'.$this->min($field, $type).','.$this->max($field, $type)],
            AttributeType::Boolean => ['boolean'],
            AttributeType::Select => array_filter([
                'string',
                Rule::in($field['allows_other'] ? [...$values, AttributeOption::OTHER] : $values),
                $field['depends_on'] !== null ? new DependentOption($field['options'], self::INPUT.'.'.$field['depends_on'], $field['allows_other']) : null,
            ]),
            AttributeType::MultiSelect => ['array', 'max:'.max(1, count($values))],
        };
    }

    /** @param  array<string, mixed>  $field */
    private function min(array $field, AttributeType $type): int|float|null
    {
        return match ($type) {
            AttributeType::Number => $field['rules']['min'] ?? 0,
            AttributeType::Year => $field['rules']['min'] ?? self::DEFAULT_MIN_YEAR,
            default => null,
        };
    }

    /** Year maximum is relative to today ("current year + 1"), so it is computed now, not cached. */
    private function max(array $field, AttributeType $type): int|float|null
    {
        return match ($type) {
            AttributeType::Number => $field['rules']['max'] ?? null,
            AttributeType::Year => $field['rules']['max'] ?? (int) now()->year + (int) ($field['rules']['max_years_ahead'] ?? 0),
            default => null,
        };
    }

    /** @param  array<string, mixed>  $field */
    private function isWholeNumber(array $field): bool
    {
        $step = $field['rules']['step'] ?? null;

        return $step !== null && (float) $step === floor((float) $step);
    }

    /** @param  array<string, string>|null  $translations */
    private function translate(?array $translations): ?string
    {
        if ($translations === null || $translations === []) {
            return null;
        }

        return $translations[app()->getLocale()] ?? $translations[config('app.fallback_locale')] ?? reset($translations);
    }
}
