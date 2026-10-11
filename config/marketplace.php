<?php

/*
|--------------------------------------------------------------------------
| Marketplace settings
|--------------------------------------------------------------------------
|
| Defaults for marketplace behaviour. Values that admins change at runtime
| live in the `settings` table and fall back to these. No country, currency
| or unit is hardcoded here: those are database records (countries,
| currencies, measurement_units). See docs/architecture/.
|
*/

return [

    // ISO 3166-1 alpha-2 of the fallback country. Null = first active country.
    'default_country' => env('MARKETPLACE_DEFAULT_COUNTRY'),

    'listings' => [
        'expiry_days' => (int) env('MARKETPLACE_LISTING_EXPIRY_DAYS', 60),
        'expiry_reminder_days' => 3,
        'renew_window_days' => 7,
        'max_active_per_user' => (int) env('MARKETPLACE_MAX_ACTIVE_LISTINGS', 50),
        'max_new_per_day' => (int) env('MARKETPLACE_MAX_NEW_LISTINGS_PER_DAY', 10),
        'per_page' => 24,
        'max_page' => 100,
    ],

    // all | new_users | none (CD-06: every new listing is reviewed at launch)
    'moderation_mode' => env('MARKETPLACE_MODERATION_MODE', 'all'),

    'images' => [
        'driver' => env('IMAGE_DRIVER', 'gd'),
        'format' => env('IMAGE_FORMAT', 'webp'),
        'max_upload_kb' => 8192,
        'default_max_per_listing' => 8,
        'min_width' => 400,
        'min_height' => 300,
        'max_megapixels' => 40,
        'variants' => [
            'lg' => ['max' => 1600, 'quality' => 80],
            'sm' => ['max' => 480, 'quality' => 75],
        ],
        'orphan_ttl_hours' => 24,
    ],

    'mail' => [
        'sending_enabled' => (bool) env('MAIL_SENDING_ENABLED', false),
        'hourly_limit' => (int) env('MAIL_HOURLY_LIMIT', 80),
    ],

    'web_runner' => [
        'enabled' => (bool) env('WEB_RUNNER_ENABLED', false),
        'interval' => (int) env('WEB_RUNNER_INTERVAL', 60),
        'max_seconds' => (int) env('WEB_RUNNER_MAX_SECONDS', 15),
    ],

    'seo' => [
        'indexing' => (bool) env('SEO_INDEXING', false),
    ],

    'geo' => [
        // Shown on the site as required by the GeoNames CC BY 4.0 licence.
        'attribution' => 'Location data © GeoNames (geonames.org), CC BY 4.0',
    ],

    // Retention periods in days (docs/architecture/09-data-retention.md).
    // Initial plan approved (CD-07); subject to privacy/legal review before production.
    'retention' => [
        'deleted_account_grace' => 30,
        'banned_identity_hash' => 730,
        'inactive_account' => 1095,
        'deleted_listing_images' => 30,
        'deleted_listing_rows' => 180,
        'expired_listing_renewable' => 90,
        'expired_listing_rows' => 180,
        'sold_listing_images' => 90,
        'sold_listing_rows' => 180,
        'rejected_listing' => 90,
        'stale_draft' => 60,
        'resolved_report' => 730,
        'audit_log' => 730,
        'contact_reveal' => 90,
        'read_notification' => 90,
        'failed_job' => 30,
    ],

];
