<?php

namespace App\Rules;

use App\Models\AttributeOption;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

/**
 * A dependent select (Model) must match the chosen parent (Make): its option must belong to the
 * parent's option. With "Other" as the parent, only "Other" (free text) is possible.
 */
class DependentOption implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    /**
     * @param  list<array{value: string, parent: string|null}>  $options
     * @param  string  $parentField  dotted input name of the parent select
     */
    public function __construct(
        private readonly array $options,
        private readonly string $parentField,
        private readonly bool $allowsOther,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->allowsOther && $value === AttributeOption::OTHER) {
            return;
        }

        $parent = Arr::get($this->data, $this->parentField);
        if (! is_string($parent) || $parent === '' || $parent === AttributeOption::OTHER) {
            $fail(__('catalogue.dependent_parent_missing'));

            return;
        }

        foreach ($this->options as $option) {
            if ($option['value'] === $value && $option['parent'] === $parent) {
                return;
            }
        }

        $fail(__('catalogue.dependent_mismatch'));
    }
}
