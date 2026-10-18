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

    // Location levels and labels decided per country (G-3, architecture doc 13 §12). Applied when the
    // country is first created; the Owner can change them in Admin → Countries until regions exist.
    'location_overrides' => [
        'LK' => ['region_levels' => 2, 'region_label' => 'Province', 'subregion_label' => 'District', 'city_label' => 'Town', 'locality_label' => 'Area'],
        'US' => ['region_levels' => 1, 'region_label' => 'State', 'city_label' => 'City', 'locality_label' => 'Neighborhood'],
        'GB' => ['region_levels' => 2, 'region_label' => 'Country', 'subregion_label' => 'County', 'city_label' => 'Town', 'locality_label' => 'Area'],
        'IN' => ['region_levels' => 1, 'region_label' => 'State', 'city_label' => 'City', 'locality_label' => 'Locality'],
    ],

    // Symbols local users expect where ICU's English symbol is just the ISO code.
    'currency_symbol_overrides' => [
        'LKR' => 'Rs',
    ],
];
