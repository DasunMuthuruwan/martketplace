<?php

namespace Tests\Unit\Money;

use App\Enums\ListingType;
use App\Enums\PriceType;
use App\Models\Currency;
use App\Models\MeasurementUnit;
use App\Services\Money\AreaConverter;
use App\Services\Money\UnitPrice;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** ADR-018/021 examples and guards (global design §5, scenario 8). */
class AreaAndUnitPriceTest extends TestCase
{
    /** Same factors as database/data/measurement_units.php. */
    private const FACTORS = [
        'sqm' => '1', 'sqft' => '0.09290304', 'perch' => '25.29285264', 'rood' => '1011.7141056',
        'acre' => '4046.8564224', 'hectare' => '10000', 'cent' => '40.468564224',
    ];

    private function unit(string $code, string $dimension = MeasurementUnit::AREA): MeasurementUnit
    {
        return new MeasurementUnit(['code' => $code, 'symbol' => $code, 'dimension' => $dimension, 'factor_to_base' => self::FACTORS[$code] ?? '1']);
    }

    private function currency(string $code = 'LKR', int $minorUnits = 2): Currency
    {
        return new Currency(['code' => $code, 'minor_units' => $minorUnits]);
    }

    private function perUnit(mixed $price, mixed $area, ?string $areaUnit, ?string $target, ListingType $type = ListingType::Sale, PriceType $priceType = PriceType::Fixed, ?Currency $currency = null): ?string
    {
        return app(UnitPrice::class)->per(
            $price,
            $currency ?? $this->currency(),
            $type,
            $priceType,
            $area,
            $areaUnit === null ? null : $this->unit($areaUnit),
            $target === null ? null : $this->unit($target),
        );
    }

    // ---- AreaConverter ---------------------------------------------------------------------

    public function test_conversions_follow_the_exact_definitions(): void
    {
        $converter = app(AreaConverter::class);

        $this->assertSame('1264.6426', $converter->toBase('50', $this->unit('perch')));
        $this->assertSame('40.0000', $converter->convert('1', $this->unit('rood'), $this->unit('perch')));
        $this->assertSame('160.0000', $converter->convert('1', $this->unit('acre'), $this->unit('perch')));
        $this->assertSame('43560.0000', $converter->convert('1', $this->unit('acre'), $this->unit('sqft')));
        $this->assertSame('2.4711', $converter->convert('1', $this->unit('hectare'), $this->unit('acre')));
        $this->assertSame('100.0000', $converter->convert('1', $this->unit('acre'), $this->unit('cent')));
        $this->assertSame('12.5000', $converter->convert('12.5', $this->unit('perch'), $this->unit('perch')));
    }

    public function test_converting_between_dimensions_or_non_numbers_fails(): void
    {
        $converter = app(AreaConverter::class);

        try {
            $converter->convert('1', $this->unit('sqm'), $this->unit('sqm', 'length'));
            $this->fail('Different dimensions converted');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);
        $converter->toBase('fifty', $this->unit('perch'));
    }

    // ---- UnitPrice -------------------------------------------------------------------------

    public function test_documented_per_perch_examples(): void
    {
        // Rs 12,500,000 for 50 perches → Rs 250,000 / perch.
        $this->assertSame('250000.00', $this->perUnit('12500000', '50', 'perch', 'perch'));
        // The same land entered as 1.25 roods (1 rood 10 perches) gives the same value.
        $this->assertSame('250000.00', $this->perUnit('12500000', '1.25', 'rood', 'perch'));
        // Rent: same calculation; the period is shown by the caller ("/ perch / month").
        $this->assertSame('2000.00', $this->perUnit('100000', '50', 'perch', 'perch', ListingType::Rent, PriceType::Negotiable));
    }

    public function test_other_countries_units(): void
    {
        // GB: £500,000 for 2 acres → £250,000 / acre; same land entered in hectares.
        $gbp = $this->currency('GBP');
        $this->assertSame('250000.00', $this->perUnit('500000', '2', 'acre', 'acre', currency: $gbp));
        $this->assertSame('250000.00', $this->perUnit('500000', '0.80937128448', 'hectare', 'acre', currency: $gbp));
        // IN: per sq ft from an area entered in cents (1 cent = 435.6 sq ft).
        $this->assertSame('4591.37', $this->perUnit('2000000', '1', 'cent', 'sqft', currency: $this->currency('INR')));
        // JPY has no minor units.
        $this->assertSame('33333', $this->perUnit('100000', '3', 'sqm', 'sqm', currency: $this->currency('JPY', 0)));
    }

    public function test_rounding_and_large_values(): void
    {
        $this->assertSame('333333.33', $this->perUnit('1000000', '3', 'perch', 'perch'));
        $this->assertSame('666666.67', $this->perUnit('2000000', '3', 'perch', 'perch'));
        $this->assertSame('999999999999999.99', $this->perUnit('999999999999999.99', '1', 'perch', 'perch'));
        $this->assertSame('1000000.00', $this->perUnit('100', '0.0001', 'perch', 'perch'), 'Tiny area');
    }

    /** @return array<string, array{mixed, mixed, string|null, string|null, ListingType, PriceType}> */
    public static function hiddenCases(): array
    {
        return [
            'no price' => [null, '50', 'perch', 'perch', ListingType::Sale, PriceType::Fixed],
            'zero price' => ['0', '50', 'perch', 'perch', ListingType::Sale, PriceType::Fixed],
            'negative price' => ['-5', '50', 'perch', 'perch', ListingType::Sale, PriceType::Fixed],
            'garbage price' => ['abc', '50', 'perch', 'perch', ListingType::Sale, PriceType::Fixed],
            'no area' => ['12500000', null, 'perch', 'perch', ListingType::Sale, PriceType::Fixed],
            'zero area' => ['12500000', '0', 'perch', 'perch', ListingType::Sale, PriceType::Fixed],
            'negative area' => ['12500000', '-50', 'perch', 'perch', ListingType::Sale, PriceType::Fixed],
            'non-numeric area' => ['12500000', 'fifty', 'perch', 'perch', ListingType::Sale, PriceType::Fixed],
            'no area unit' => ['12500000', '50', null, 'perch', ListingType::Sale, PriceType::Fixed],
            'country without price-per unit' => ['12500000', '50', 'perch', null, ListingType::Sale, PriceType::Fixed],
            'wanted listing' => ['12500000', '50', 'perch', 'perch', ListingType::Wanted, PriceType::Fixed],
            'free' => ['12500000', '50', 'perch', 'perch', ListingType::Sale, PriceType::Free],
            'on request' => ['12500000', '50', 'perch', 'perch', ListingType::Sale, PriceType::OnRequest],
        ];
    }

    #[DataProvider('hiddenCases')]
    public function test_is_hidden_when_meaningless(mixed $price, mixed $area, ?string $areaUnit, ?string $target, ListingType $type, PriceType $priceType): void
    {
        $this->assertNull($this->perUnit($price, $area, $areaUnit, $target, $type, $priceType));
    }

    public function test_mismatched_dimensions_are_hidden(): void
    {
        $price = app(UnitPrice::class)->per('100', $this->currency(), ListingType::Sale, PriceType::Fixed, '5', $this->unit('sqm'), $this->unit('sqm', 'length'));

        $this->assertNull($price);
    }
}
