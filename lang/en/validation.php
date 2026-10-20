<?php

// Field names in validation messages (F-06): without these Laravel prints the column name
// ("The country id field is required."). Merged over the framework's validation messages, which
// stay the source of every other line.
return [
    'attributes' => [
        'country_id' => 'country',
        'region_id' => 'region',
        'subregion_id' => 'area',
        'city_id' => 'town or city',
        'locality_id' => 'neighbourhood',
        'category_id' => 'category',
        'reportable_id' => 'reported item',
        'phone_country' => 'phone country',
        'price_type' => 'price type',
        'rent_period' => 'rent period',
        'contact_visibility' => 'who can see your number',
        'current_password' => 'current password',
        'proposed_city' => 'town name',
        'locality_text' => 'area',
    ],
];
