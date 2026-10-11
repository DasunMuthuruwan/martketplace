<?php

namespace App\Services\Money;

use App\Models\MeasurementUnit;
use App\Support\Decimal;
use InvalidArgumentException;

/**
 * Converts areas between units with bcmath via each unit's factor to m² (global design §5).
 * Seller input is kept as entered; the m² value is a normalised copy for filtering and sorting.
 */
class AreaConverter
{
    /** Decimal places kept for normalised m² values. */
    public const BASE_SCALE = 4;

    /** Area in m², rounded to BASE_SCALE decimals. */
    public function toBase(string|int|float $value, MeasurementUnit $unit): string
    {
        return Decimal::round(Decimal::mul($this->number($value), $unit->factor_to_base), self::BASE_SCALE);
    }

    public function convert(string|int|float $value, MeasurementUnit $from, MeasurementUnit $to, int $scale = self::BASE_SCALE): string
    {
        if ($from->dimension !== $to->dimension) {
            throw new InvalidArgumentException("Cannot convert {$from->code} to {$to->code}.");
        }
        if ($from->code === $to->code) {
            return Decimal::round($this->number($value), $scale);
        }

        return Decimal::round($this->exact($value, $from, $to), $scale);
    }

    /** Unrounded conversion (20 decimal places) for further calculation. */
    public function exact(string|int|float $value, MeasurementUnit $from, MeasurementUnit $to): string
    {
        return Decimal::div(Decimal::mul($this->number($value), $from->factor_to_base), $to->factor_to_base);
    }

    private function number(string|int|float $value): string
    {
        return Decimal::parse($value) ?? throw new InvalidArgumentException('Area must be a number.');
    }
}
