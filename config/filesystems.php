<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            // No signed "serve" route: private files are never served directly (T4.3 decision).
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Listing photos (security doc §4). Only re-encoded variants are stored here. The driver,
        // root and public URL come from env so storage can move without code changes (P6):
        //   local   → storage/app/public/listings, served via the public/storage symlink;
        //   uploads → public/uploads/listings, for hosts without symlinks (script execution blocked
        //             by an .htaccess that App\Support\ListingsDisk writes);
        //   s3      → S3-compatible object storage (AWS_* settings).
        'listings' => match (env('LISTINGS_DISK_DRIVER', 'local')) {
            's3' => [
                'driver' => 's3',
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
                'region' => env('AWS_DEFAULT_REGION'),
                'bucket' => env('AWS_BUCKET'),
                'url' => env('LISTINGS_URL') ?: env('AWS_URL'),
                'endpoint' => env('AWS_ENDPOINT'),
                'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
                'root' => env('LISTINGS_ROOT') ?: 'listings',
                'visibility' => 'public',
                'throw' => true,
            ],
            'uploads' => [
                'driver' => 'local',
                'root' => env('LISTINGS_ROOT') ?: public_path('uploads/listings'),
                'url' => env('LISTINGS_URL') ?: rtrim(env('APP_URL', 'http://localhost'), '/').'/uploads/listings',
                'visibility' => 'public',
                'throw' => true,
            ],
            default => [
                'driver' => 'local',
                'root' => env('LISTINGS_ROOT') ?: storage_path('app/public/listings'),
                'url' => env('LISTINGS_URL') ?: rtrim(env('APP_URL', 'http://localhost'), '/').'/storage/listings',
                'visibility' => 'public',
                'throw' => true,
            ],
        },

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
