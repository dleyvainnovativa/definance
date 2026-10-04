<?php

/*
|--------------------------------------------------------------------------
| Legacy database connection (for the one-time data migration)
|--------------------------------------------------------------------------
| Point these at a MySQL database holding the OLD DeFinance dump. The
| migrate-legacy command registers this as the `legacy` connection at runtime,
| so there is no need to edit config/database.php.
|
| .env:
|   LEGACY_DB_HOST=127.0.0.1
|   LEGACY_DB_PORT=3306
|   LEGACY_DB_DATABASE=definance_old
|   LEGACY_DB_USERNAME=root
|   LEGACY_DB_PASSWORD=
*/

return [
    'driver' => 'mysql',
    'host' => env('LEGACY_DB_HOST', '127.0.0.1'),
    'port' => env('LEGACY_DB_PORT', '3306'),
    'database' => env('LEGACY_DB_DATABASE', 'definance_old'),
    'username' => env('LEGACY_DB_USERNAME', 'root'),
    'password' => env('LEGACY_DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => false,
];
