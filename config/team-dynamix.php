<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| TeamDynamix SDK
|--------------------------------------------------------------------------
|
| The connection the support ticket gateway uses when SUPPORT_DRIVER is
| "team-dynamix". This file replaces the SDK's own config, which reads the
| two application names with config() instead of env() (tdx-php-sdk v0.6.0),
| so TDX_TICKET_APP_NAME and TDX_CLIENT_APP_NAME would otherwise be ignored.
|
*/

return [

    // The base URL for TeamDynamix, including the environment.
    'apiBaseUrl' => env('TDX_API_BASE_URL'),

    'auth' => [
        'username' => env('TDX_USERNAME'),
        'password' => env('TDX_PASSWORD'),
    ],

    'apps' => [
        // The TDTickets application where tickets are created.
        'ticketing' => env('TDX_TICKET_APP_NAME'),

        // The TDClient application where services are managed.
        'client' => env('TDX_CLIENT_APP_NAME'),
    ],

];
