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

    // Extra hosts trusted temporarily during a domain migration (APP_ALIASES, comma-separated).
    'app_aliases' => array_values(array_filter(array_map('trim', explode(',', (string) env('APP_ALIASES', ''))))),

    // Proxies/CDNs in front of the site (TRUSTED_PROXIES, comma-separated IPs/CIDRs or *).
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),

    // Content-Security-Policy additions. Hosts are configuration, never code.
    'csp' => [
        'report_only' => (bool) env('CSP_REPORT_ONLY', false),
        // Turnstile needs its script and iframe host, only when configured.
        'script' => array_filter([env('TURNSTILE_SITE_KEY') ? 'https://challenges.cloudflare.com' : null]),
        'style' => [],
        'img' => array_filter([env('LISTINGS_URL')]),
        'font' => [],
        'connect' => [],
        'frame' => array_filter([env('TURNSTILE_SITE_KEY') ? 'https://challenges.cloudflare.com' : null]),
    ],

    // Cloudflare Turnstile bot protection (ADR-010). Disabled when keys are empty.
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'timeout' => 5,
    ],

    'listings' => [
        'expiry_days' => (int) env('MARKETPLACE_LISTING_EXPIRY_DAYS', 60),
        'expiry_reminder_days' => 3,
        'renew_window_days' => 7,
        // A sold ad keeps a public "sold" page (noindex) this long, then answers 410 (retention §1).
        'sold_page_days' => 30,
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
        // Folder the static sitemap.xml and sitemaps/*.xml are written to (served by the web server).
        'sitemap_root' => env('SITEMAP_ROOT', public_path()),
        'sitemap_chunk' => 10000,
    ],

    'geo' => [
        // Shown on the site as required by the GeoNames CC BY 4.0 licence.
        'attribution' => 'Location data © GeoNames (geonames.org), CC BY 4.0',
        // Folder with downloaded GeoNames dumps for geo:import-regions / geo:import-cities (never committed).
        'import_path' => env('GEONAMES_PATH', storage_path('app/geonames')),
        // Visitor country (G-2): remembered choice → CF-IPCountry (only from a trusted proxy and
        // only when enabled, i.e. when the site sits behind Cloudflare) → default_country.
        'country_cookie' => 'dtm_country',
        'country_cookie_days' => 365,
        'use_cf_ip_country' => (bool) env('GEO_USE_CF_IPCOUNTRY', false),
    ],

    // Seller contact (MSG-04–07, security doc §3: contact-reveal limits).
    'contact' => [
        // Site-wide switches (MSG-07); an Admin setting later (M6).
        'call_enabled' => (bool) env('CONTACT_CALL_ENABLED', true),
        'whatsapp_enabled' => (bool) env('CONTACT_WHATSAPP_ENABLED', true),
        'reveals_per_hour_user' => 30,
        'reveals_per_hour_guest' => 10,
        // Guests must pass Turnstile after this many reveals per hour (when Turnstile is configured).
        'guest_challenge_after' => 5,
    ],

    // Area units (global design §5). Countries without their own units offer these unit codes.
    'area' => [
        'fallback_units' => ['sqm', 'hectare'],
    ],

    // Staff session limits (docs/architecture/12-authentication-and-2fa.md §6).
    'staff_session' => [
        'idle_minutes' => (int) env('STAFF_SESSION_IDLE_MINUTES', 30),
        'absolute_minutes' => (int) env('STAFF_SESSION_ABSOLUTE_MINUTES', 480),
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
