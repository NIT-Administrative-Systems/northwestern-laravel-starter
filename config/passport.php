<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Passport Guard
    |--------------------------------------------------------------------------
    |
    | Here you may specify which authentication guard Passport will use when
    | authenticating users. This value should correspond with one of your
    | guards that is already present in your "auth" configuration file.
    |
    */

    'guard' => 'web',

    // Passport's OAuth endpoints share the API's per-IP limit. No other site may frame the consent screen.
    // No token is issued or refreshed for a credential whose feature is off. Whether someone may connect
    // a client is decided by App\Domains\Auth\Passport\OAuthConsent. A person sent to consent by a deleted
    // or revoked client sees a page instead of Passport's JSON.
    'middleware' => [
        'throttle:api',
        App\Domains\Auth\Http\Middleware\DenyFramingOfConsent::class,
        App\Domains\Auth\Http\Middleware\RefuseTokensWhileFeatureOff::class,
        App\Domains\Auth\Http\Middleware\AuthorizeOAuthConsent::class,
        Northwestern\SysDev\Chassis\Http\Middleware\DetectUnknownOAuthClient::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption Keys
    |--------------------------------------------------------------------------
    |
    | Passport uses encryption keys while generating secure access tokens for
    | your application. By default, the keys are stored as local files but
    | can be set via environment variables when that is more convenient.
    |
    */

    'private_key' => env('PASSPORT_PRIVATE_KEY'),

    'public_key' => env('PASSPORT_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Passport Database Connection
    |--------------------------------------------------------------------------
    |
    | By default, Passport's models will utilize your application's default
    | database connection. If you wish to use a different connection you
    | may specify the configured name of the database connection here.
    |
    */

    'connection' => env('PASSPORT_CONNECTION'),

];
