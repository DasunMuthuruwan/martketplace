<?php

// Public, account and sign-in layouts (navigation doc §2). The product name is never written here:
// it comes from config('app.name') (Q-UX-2).
return [
    'skip' => 'Skip to content',
    'home_label' => ':name home',
    'home_suffix' => 'home',
    'tagline' => 'Buy and sell directly with people near you.',
    'search' => 'Search',
    'search_label' => 'Search ads',
    'search_placeholder' => 'Search ads…',
    'country_label' => 'Country: :country. Change country',
    'all_countries' => 'All countries',
    'categories' => 'Categories',
    'help' => 'Help',
    'legal' => 'Legal',
    'post_ad' => 'Post ad',
    'saved' => 'Saved',
    'my_ads' => 'My ads',
    'settings' => 'Settings',
    'admin_panel' => 'Admin panel',
    'account_menu' => 'Account menu',
    'log_out' => 'Log out',
    'back_to_site' => 'Back to the site',

    // Default parent line (Q-UX-1, ADR-024); MARKETPLACE_PARENT_NAME / _URL override or hide it.
    'parent' => [
        'name' => 'A Dev Talk service',
        'url' => 'https://dev-talk.com',
    ],

    // Mobile bottom navigation (Q-UX-3).
    'nav' => [
        'label' => 'Main',
        'home' => 'Home',
        'search' => 'Search',
        'post' => 'Post',
        'saved' => 'Saved',
        'account' => 'Account',
        'soon' => 'Coming soon',
    ],
];
