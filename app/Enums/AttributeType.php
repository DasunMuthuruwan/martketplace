<?php

namespace App\Enums;

/** Attribute input types (database design §2.4). The same type drives the form field and its rules. */
enum AttributeType: string
{
    case Text = 'text';
    case Number = 'number';
    case Select = 'select';
    case MultiSelect = 'multiselect';
    case Boolean = 'boolean';
    case Year = 'year';

    public function hasOptions(): bool
    {
        return $this === self::Select || $this === self::MultiSelect;
    }
}
