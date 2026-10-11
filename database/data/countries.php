<?php

/*
| Country seed adjustments (data, not code). See docs/architecture/13-global-marketplace.md §2.
*/

return [
    // Obsolete ISO codes still present in GeoNames countryInfo.txt.
    'excluded' => [
        'CS', // Serbia and Montenegro (dissolved 2006)
        'AN', // Netherlands Antilles (dissolved 2010)
    ],

    // Default display timezone for multi-zone countries whose capital doesn't match a zone name.
    // All other countries: the zone named after the capital, else the only/first zone.
    'timezone_overrides' => [
        'US' => 'America/New_York',
        'CA' => 'America/Toronto',
        'AU' => 'Australia/Sydney',
        'BR' => 'America/Sao_Paulo',
        'RU' => 'Europe/Moscow',
        'CN' => 'Asia/Shanghai',
        'IN' => 'Asia/Kolkata',
        'KZ' => 'Asia/Almaty',
        'MN' => 'Asia/Ulaanbaatar',
        'CD' => 'Africa/Kinshasa',
        'NZ' => 'Pacific/Auckland',
        'ES' => 'Europe/Madrid',
        'PT' => 'Europe/Lisbon',
        'EC' => 'America/Guayaquil',
        'CL' => 'America/Santiago',
        'MY' => 'Asia/Kuala_Lumpur',
        'UA' => 'Europe/Kyiv',
        'GL' => 'America/Nuuk',
        'PG' => 'Pacific/Port_Moresby',
        'FM' => 'Pacific/Pohnpei',
        'KI' => 'Pacific/Tarawa',
        'PF' => 'Pacific/Tahiti',
        'UM' => 'Pacific/Midway',
    ],

    // Symbols local users expect where ICU's English symbol is just the ISO code.
    'currency_symbol_overrides' => [
        'LKR' => 'Rs',
    ],
];
