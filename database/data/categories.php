<?php

/**
 * Approved working category tree (docs/architecture/04-category-tree.md §1, CD-08): 7 top-level
 * categories, 48 leaves. Leaves inherit their parent's `settings` unless they override a key.
 * Country scoping per §6 / G-13. Icons are Lucide names (design system §icons).
 * Seeded once: existing categories (matched by slug) are never overwritten, so admin edits survive.
 */
$vehicles = ['allowed_types' => ['sale', 'rent', 'wanted'], 'show_condition' => true, 'max_images' => 10];
$property = ['allowed_types' => ['sale', 'rent', 'wanted'], 'show_condition' => false, 'max_images' => 10];
$goods = ['allowed_types' => ['sale', 'wanted'], 'show_condition' => true, 'max_images' => 8];
$household = $goods + ['allow_free' => true];
$rentOnly = ['allowed_types' => ['rent', 'wanted']];
$threeWheelerCountries = ['scope_countries' => ['IN', 'LK']];

return [
    [
        'slug' => 'vehicles', 'name' => 'Vehicles', 'icon' => 'car', 'settings' => $vehicles,
        'children' => [
            ['slug' => 'cars', 'name' => 'Cars'],
            ['slug' => 'vans', 'name' => 'Vans'],
            ['slug' => 'suvs-jeeps', 'name' => 'SUVs & Jeeps'],
            ['slug' => 'motorcycles-scooters', 'name' => 'Motorcycles & Scooters'],
            ['slug' => 'three-wheelers', 'name' => 'Three-wheelers', 'settings' => $threeWheelerCountries],
            ['slug' => 'lorries-trucks', 'name' => 'Lorries & Trucks'],
            ['slug' => 'buses', 'name' => 'Buses'],
            ['slug' => 'heavy-machinery-tractors', 'name' => 'Heavy Machinery & Tractors'],
            ['slug' => 'bicycles', 'name' => 'Bicycles'],
            ['slug' => 'boats', 'name' => 'Boats'],
        ],
    ],
    [
        'slug' => 'property', 'name' => 'Property', 'icon' => 'house', 'settings' => $property,
        'children' => [
            // Land: total price + calculated price per unit (ADR-018/021); free and on-request are not allowed.
            ['slug' => 'land', 'name' => 'Land', 'settings' => ['allowed_price_types' => ['fixed', 'negotiable']]],
            ['slug' => 'houses', 'name' => 'Houses'],
            ['slug' => 'apartments', 'name' => 'Apartments'],
            ['slug' => 'commercial-property', 'name' => 'Commercial Property'],
            ['slug' => 'rooms-annexes', 'name' => 'Rooms & Annexes', 'settings' => $rentOnly],
            ['slug' => 'holiday-short-stay-rentals', 'name' => 'Holiday & Short-stay Rentals', 'settings' => $rentOnly],
        ],
    ],
    [
        'slug' => 'vehicle-parts-accessories', 'name' => 'Vehicle Parts & Accessories', 'icon' => 'wrench', 'settings' => $goods,
        'children' => [
            ['slug' => 'car-parts', 'name' => 'Car Parts'],
            ['slug' => 'motorcycle-parts', 'name' => 'Motorcycle Parts'],
            ['slug' => 'three-wheeler-parts', 'name' => 'Three-wheeler Parts', 'settings' => $threeWheelerCountries],
            ['slug' => 'tyres-rims', 'name' => 'Tyres & Rims'],
            ['slug' => 'car-audio-electronics', 'name' => 'Car Audio & Electronics'],
            ['slug' => 'vehicle-accessories', 'name' => 'Accessories'],
        ],
    ],
    [
        'slug' => 'mobile-phones-tablets', 'name' => 'Mobile Phones & Tablets', 'icon' => 'smartphone', 'settings' => $goods,
        'children' => [
            ['slug' => 'mobile-phones', 'name' => 'Mobile Phones'],
            ['slug' => 'tablets', 'name' => 'Tablets'],
            ['slug' => 'phone-tablet-accessories', 'name' => 'Phone & Tablet Accessories'],
            ['slug' => 'smart-watches-wearables', 'name' => 'Smart Watches & Wearables'],
        ],
    ],
    [
        'slug' => 'electronics-computers', 'name' => 'Electronics & Computers', 'icon' => 'monitor', 'settings' => $goods,
        'children' => [
            ['slug' => 'laptops', 'name' => 'Laptops'],
            ['slug' => 'desktop-computers', 'name' => 'Desktop Computers'],
            ['slug' => 'computer-accessories-parts', 'name' => 'Computer Accessories & Parts'],
            ['slug' => 'tvs', 'name' => 'TVs'],
            ['slug' => 'audio-speakers', 'name' => 'Audio & Speakers'],
            ['slug' => 'cameras-drones', 'name' => 'Cameras & Drones'],
            ['slug' => 'video-games-consoles', 'name' => 'Video Games & Consoles'],
            ['slug' => 'other-electronics', 'name' => 'Other Electronics'],
        ],
    ],
    [
        'slug' => 'home-furniture-appliances', 'name' => 'Home, Furniture & Appliances', 'icon' => 'sofa', 'settings' => $household,
        'children' => [
            ['slug' => 'furniture', 'name' => 'Furniture'],
            ['slug' => 'home-appliances', 'name' => 'Home Appliances'],
            ['slug' => 'kitchen-items', 'name' => 'Kitchen Items'],
            ['slug' => 'home-decor', 'name' => 'Home Décor'],
            ['slug' => 'garden', 'name' => 'Garden'],
        ],
    ],
    [
        'slug' => 'other-items', 'name' => 'Other Items', 'icon' => 'package', 'settings' => $household,
        'children' => [
            ['slug' => 'fashion-clothing', 'name' => 'Fashion & Clothing'],
            ['slug' => 'watches-jewellery', 'name' => 'Watches & Jewellery'],
            ['slug' => 'sports-fitness', 'name' => 'Sports & Fitness'],
            ['slug' => 'books-education', 'name' => 'Books & Education'],
            ['slug' => 'musical-instruments', 'name' => 'Musical Instruments'],
            ['slug' => 'baby-kids', 'name' => 'Baby & Kids'],
            ['slug' => 'health-beauty', 'name' => 'Health & Beauty'],
            ['slug' => 'business-industrial-equipment', 'name' => 'Business & Industrial Equipment'],
            ['slug' => 'everything-else', 'name' => 'Everything Else'],
        ],
    ],
];
