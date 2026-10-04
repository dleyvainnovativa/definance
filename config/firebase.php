<?php

/*
|--------------------------------------------------------------------------
| Firebase configuration (kreait/laravel-firebase compatible)
|--------------------------------------------------------------------------
| Server-side credentials come from a Service Account JSON file whose path
| is set in FIREBASE_CREDENTIALS. The project id is read from that file, so
| token verification never depends on env() at runtime — this file is the
| single source of truth and is safe under `php artisan config:cache`.
|
| Publish/refresh the vendor default any time with:
|   php artisan vendor:publish --tag=firebase-config
*/

return [

    'default' => env('FIREBASE_PROJECT', 'app'),

    'projects' => [

        'app' => [

            /*
             | Absolute or relative path to the Service Account JSON.
             | Keep this file OUT of version control. See .env.example.
             */
            'credentials' => env('FIREBASE_CREDENTIALS', env('GOOGLE_APPLICATION_CREDENTIALS')),

            'auth' => [
                // Tenant id for multi-tenant Firebase Auth (not used here).
                'tenant_id' => env('FIREBASE_AUTH_TENANT_ID'),
            ],

            // We only use Auth in this project; other services can be added later.
            'database' => [
                'url' => env('FIREBASE_DATABASE_URL'),
            ],

            'dynamic_links' => [
                'default_domain' => env('FIREBASE_DYNAMIC_LINKS_DEFAULT_DOMAIN'),
            ],

            'storage' => [
                'default_bucket' => env('FIREBASE_STORAGE_DEFAULT_BUCKET'),
            ],

            'cache_store' => env('FIREBASE_CACHE_STORE', 'file'),

            'logging' => [
                'http_log_channel' => env('FIREBASE_HTTP_LOG_CHANNEL'),
                'http_debug_log_channel' => env('FIREBASE_HTTP_DEBUG_LOG_CHANNEL'),
            ],

            'http_client_options' => [
                'proxy' => env('FIREBASE_HTTP_CLIENT_PROXY'),
                'timeout' => env('FIREBASE_HTTP_CLIENT_TIMEOUT'),
            ],
        ],
    ],
];
