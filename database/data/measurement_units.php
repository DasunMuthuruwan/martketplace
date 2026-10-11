<?php

/**
 * Area units and the units each country offers (global design §5, ADR-021, G-6 defaults).
 * Factors are exact international definitions in square metres:
 *   1 ft = 0.3048 m → 1 sq ft = 0.09290304 m²; 1 yd² = 0.83612736 m²;
 *   1 perch (square rod, 272.25 sq ft) = 25.29285264 m²; 1 rood = 40 perches; 1 acre = 160 perches;
 *   1 cent = 1/100 acre; 1 hectare = 10,000 m².
 * Seeded once: existing units and country assignments are never overwritten (admin edits survive).
 */

return [
    'units' => [
        ['code' => 'sqm', 'name' => 'square metre', 'symbol' => 'm²', 'factor' => '1', 'sort' => 1],
        ['code' => 'sqft', 'name' => 'square foot', 'symbol' => 'sq ft', 'factor' => '0.09290304', 'sort' => 2],
        ['code' => 'sqyd', 'name' => 'square yard', 'symbol' => 'sq yd', 'factor' => '0.83612736', 'sort' => 3],
        ['code' => 'perch', 'name' => 'perch', 'symbol' => 'P', 'factor' => '25.29285264', 'sort' => 4],
        ['code' => 'cent', 'name' => 'cent', 'symbol' => 'cent', 'factor' => '40.468564224', 'sort' => 5],
        ['code' => 'rood', 'name' => 'rood', 'symbol' => 'R', 'factor' => '1011.7141056', 'sort' => 6],
        ['code' => 'acre', 'name' => 'acre', 'symbol' => 'ac', 'factor' => '4046.8564224', 'sort' => 7],
        ['code' => 'hectare', 'name' => 'hectare', 'symbol' => 'ha', 'factor' => '10000', 'sort' => 8],
    ],

    // ISO2 => units offered (first = default) and the land "price per" unit (null = none).
    'countries' => [
        'LK' => ['units' => ['perch', 'rood', 'acre', 'hectare'], 'land_price_unit' => 'perch'],
        'IN' => ['units' => ['sqft', 'sqm', 'acre', 'hectare', 'cent'], 'land_price_unit' => 'sqft'],
        'GB' => ['units' => ['acre', 'hectare', 'sqft', 'sqm'], 'land_price_unit' => 'acre'],
        'US' => ['units' => ['acre', 'sqft'], 'land_price_unit' => 'acre'],
    ],
];
