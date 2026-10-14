<?php

namespace App\View;

use Illuminate\Support\ViewErrorBag;
use Illuminate\View\ComponentAttributeBag;

/**
 * Shared wiring for form controls (component doc §2): the field name (from `name` or
 * `wire:model`), a stable id, the error message from the error bag, and the
 * `aria-describedby` / `aria-invalid` attributes linking the control to its hint and error.
 */
final class FieldState
{
    public readonly string $name;

    public readonly string $id;

    public readonly ?string $error;

    public function __construct(ComponentAttributeBag $attributes, ?string $name, ?string $id, ?string $error, ?string $hint, mixed $errors)
    {
        $this->name = (string) ($name ?? $attributes->wire('model')->value() ?? '');
        $this->id = $id ?? ('f-'.(preg_replace('/[^A-Za-z0-9_-]+/', '-', $this->name) ?: bin2hex(random_bytes(4))));
        $this->error = $error ?? ($errors instanceof ViewErrorBag && $this->name !== '' ? $errors->first($this->dotted()) : null) ?: null;
        $this->hasHint = $hint !== null && $hint !== '';
    }

    private bool $hasHint;

    /** @return array<string, string> */
    public function aria(): array
    {
        $describedBy = array_filter([$this->hasHint ? $this->id.'-hint' : null, $this->error ? $this->id.'-error' : null]);

        return array_filter([
            'aria-describedby' => implode(' ', $describedBy),
            'aria-invalid' => $this->error ? 'true' : '',
        ], fn (string $value) => $value !== '');
    }

    /** `items[0][name]` → `items.0.name`, as validation keys are written. */
    private function dotted(): string
    {
        return trim(str_replace(['[', ']'], ['.', ''], $this->name), '.');
    }
}
