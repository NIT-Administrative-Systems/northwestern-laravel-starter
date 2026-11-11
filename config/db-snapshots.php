<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | PostgreSQL Tools
    |--------------------------------------------------------------------------
    |
    | Where `psql` and `pg_dump` are. Left empty, Chassis looks for Herd's copies.
    |
    */

    'pg_bin_directory' => env('PG_BIN_DIRECTORY'),

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | The disk snapshots are stored on, and the database connection they are
    | taken from, where null means the application's default connection.
    |
    | Where dumps are written while a snapshot is made, and whether they're gzipped.
    |
    */

    'disk' => 'snapshots',

    'default_connection' => null,

    'temporary_directory_path' => storage_path('app/laravel-db-snapshots/temp'),

    'compress' => false,

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    |
    | Snapshot only these tables, or every table except these; null means all.
    |
    */

    'tables' => null,

    'exclude' => null,

];
