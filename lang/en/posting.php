<?php

// Posting wizard (layout doc §3.4, global UX §2–§4).
return [
    'title' => 'Post an ad',
    'edit_title' => 'Edit your ad',
    'exit' => 'Exit',
    'exit_saved' => 'Exit (saved as draft)',
    'saved' => 'Saved',
    'saved_draft' => 'Draft saved',
    'steps_label' => 'Steps',
    'step_of' => 'Step :n of :total',
    'done' => 'done',
    'back' => 'Back',
    'continue' => 'Continue',
    'change' => 'Change',
    'steps' => [
        'category' => 'Category',
        'location' => 'Location',
        'details' => 'Details',
        'price' => 'Price',
        'photos' => 'Photos',
        'review' => 'Contact and review',
    ],

    'category' => [
        'title' => 'What are you selling?',
        'intro' => 'Choose the category that fits best. Buyers search by category.',
        'change_intro' => 'Choose a different category for this ad.',
        'search' => 'Search categories',
        'all' => 'All categories',
        'clears' => 'Changing the category clears the details you entered for the old one. The title, description, price and photos stay.',
    ],

    'location' => [
        'title' => 'Where is it?',
        'intro' => 'Buyers search by area. Only the area is shown, never an address.',
        'category' => 'Category:',
        'choose' => 'Choose…',
        'optional' => 'Optional',
        'type_town' => 'Type to find your town',
        'choose_region_first' => 'Choose the :label first to see its towns.',
        'no_address' => 'Optional. Do not enter a house number or street address.',
        'not_listed' => 'My town is not listed',
        'not_listed_help' => 'Tell us the name and we will add it. For now, choose the nearest town above.',
        'town_name' => 'Town name',
        'refresh' => 'Show the places for my choice',
    ],

    'country_change' => [
        'title' => 'Change country to :country?',
        'intro' => 'These will change:',
        'keep' => 'Keep :country',
        'change' => 'Change to :country',
    ],

    'pending' => [
        'title' => 'This step is coming soon',
        'text' => 'Your draft is saved. The remaining steps of the posting form are being built.',
    ],
];
