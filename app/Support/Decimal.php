<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact decimal arithmetic on numeric strings (bcmath), used for prices and areas so that no
 * float rounding ever reaches a stored or displayed amount (global design §3.2). Rounding is
 * half away from zero.
 */
final class Decimal
{
    /** Internal precision for intermediate results. */
    public const SCALE = 20;

    /** A canonical numeric string, or null when the value is not a finite number. */
    public static function parse(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            if (! is_finite($value)) {
                return null;
            }
            $value = rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        }
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if (! preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)$/', $value)) {
            return null;
        }

        // bcmath needs digits on both sides of the point: ".5" → "0.5", "5." → "5".
        $value = rtrim(ltrim($value, '+'), '.');
        $value = preg_replace('/^(-?)\./', '${1}0.', $value) ?? $value;

        return bcadd($value, '0', self::scaleOf($value));
    }

    public static function round(string $value, int $scale): string
    {
        $value = self::require($value);
        $half = '0.'.str_repeat('0', $scale).'5';
        $shifted = str_starts_with($value, '-') ? bcsub($value, $half, $scale + 1) : bcadd($value, $half, $scale + 1);

        return self::stripNegativeZero(bcadd($shifted, '0', $scale));
    }

    public static function mul(string $a, string $b): string
    {
        return bcmul(self::require($a), self::require($b), self::SCALE);
    }

    public static function div(string $a, string $b): string
    {
        $b = self::require($b);
        if (bccomp($b, '0', self::SCALE) === 0) {
            throw new InvalidArgumentException('Division by zero.');
        }

        return bcdiv(self::require($a), $b, self::SCALE);
    }

    public static function compare(string $a, string $b): int
    {
        return bccomp(self::require($a), self::require($b), self::SCALE);
    }

    public static function isPositive(string $value): bool
    {
        return self::compare($value, '0') > 0;
    }

    public static function isWhole(string $value): bool
    {
        $value = self::require($value);

        return bccomp($value, bcadd($value, '0', 0), self::SCALE) === 0;
    }

    /** Number of digits after the decimal point, ignoring trailing zeros. */
    public static function decimals(string $value): int
    {
        $value = self::require($value);
        $fraction = str_contains($value, '.') ? rtrim(substr($value, strpos($value, '.') + 1), '0') : '';

        return strlen($fraction);
    }

    private static function require(string $value): string
    {
        $parsed = self::parse($value);
        if ($parsed === null) {
            throw new InvalidArgumentException("Not a decimal number: [{$value}].");
        }

        return $parsed;
    }

    private static function scaleOf(string $value): int
    {
        $dot = strpos($value, '.');

        return $dot === false ? 0 : strlen($value) - $dot - 1;
    }

    private static function stripNegativeZero(string $value): string
    {
        return bccomp($value, '0', self::SCALE) === 0 ? ltrim($value, '-') : $value;
    }
}
