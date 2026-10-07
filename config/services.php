<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Azure, AWS and more. This file provides the de facto location for
    | this type of information, allowing packages to have a conventional
    | file to locate the various service credentials.
    |
    */

    'northwestern-azure' => [
        'client_id' => env('AZURE_CLIENT_ID'),
        'client_secret' => env('AZURE_CLIENT_SECRET'),
        // Socialite requires the key to exist. laravel-soa leaves it unused and builds the callback URL
        // from the login-oauth-callback route.
        'redirect' => null,
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),

        // This is DIFFERENT from our normal AWS region.
        // Don't lose it during Laravel upgrades, or you'll break all the emails.
        'region' => env('AWS_SES_EMAIL_REGION', 'us-east-1'),
    ],

];
