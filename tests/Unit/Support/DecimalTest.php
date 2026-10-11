<?php

namespace Tests\Unit\Support;

use App\Support\Decimal;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DecimalTest extends TestCase
{
    /** @return array<string, array{mixed, string|null}> */
    public static function parseCases(): array
    {
        return [
            'int' => [12500000, '12500000'],
            'string' => ['12500000.000', '12500000.000'],
            'plus sign and spaces' => [' +42.5 ', '42.5'],
            'leading point' => ['.5', '0.5'],
            'negative leading point' => ['-.5', '-0.5'],
            'trailing point' => ['5.', '5'],
            'float' => [0.1, '0.1'],
            'non-numeric' => ['12abc', null],
            'exponent' => ['1e5', null],
            'empty' => ['', null],
            'thousands separator' => ['1,000', null],
            'infinite float' => [INF, null],
            'array' => [['1'], null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('parseCases')]
    public function test_parse(mixed $input, ?string $expected): void
    {
        $this->assertSame($expected, Decimal::parse($input));
    }

    /** @return array<string, array{string, int, string}> */
    public static function roundCases(): array
    {
        return [
            'half up' => ['2.345', 2, '2.35'],
            'below half' => ['2.344', 2, '2.34'],
            'to whole' => ['250000.5', 0, '250001'],
            'negative half away from zero' => ['-2.345', 2, '-2.35'],
            'negative tiny to zero' => ['-0.001', 2, '0.00'],
            'pads decimals' => ['7', 3, '7.000'],
            'long fraction' => ['0.33333333333333333333', 4, '0.3333'],
        ];
    }

    #[DataProvider('roundCases')]
    public function test_round(string $value, int $scale, string $expected): void
    {
        $this->assertSame($expected, Decimal::round($value, $scale));
    }

    public function test_arithmetic_is_exact(): void
    {
        $this->assertSame(0, Decimal::compare(Decimal::mul('0.1', '3'), '0.3'), 'No float error');
        $this->assertSame(0, Decimal::compare(Decimal::div('12500000', '50'), '250000'));
        $this->assertTrue(Decimal::isWhole('12500000.000'));
        $this->assertFalse(Decimal::isWhole('12500.5'));
        $this->assertSame(1, Decimal::decimals('12500.50'));
        $this->assertSame(0, Decimal::decimals('12500'));
        $this->assertTrue(Decimal::isPositive('0.0001'));
        $this->assertFalse(Decimal::isPositive('0'));
    }

    public function test_division_by_zero_and_garbage_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Decimal::div('1', '0.000');
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Decimal::round('abc', 2);
    }
}
