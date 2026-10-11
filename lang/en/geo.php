<?php

return [
    // Fallback labels when a country has no specific term configured.
    'labels' => [
        'country' => 'Country',
        'region' => 'Region',
        'subregion' => 'Area',
        'city' => 'City or town',
        'locality' => 'Neighbourhood',
    ],

    // Location chain validation (App\Validation\LocationChain). :label is the country's own term.
    'validation' => [
        'invalid' => 'Choose a valid option from the list.',
        'required' => 'Select the :label.',
        'unavailable' => 'The selected :label is no longer available. Choose another.',
        'mismatch' => 'The selected :label is not in this :parent.',
        'not_applicable' => 'This field does not apply to :country.',
    ],
];
