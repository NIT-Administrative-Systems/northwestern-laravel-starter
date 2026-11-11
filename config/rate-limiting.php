<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | API Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Two limits apply. `per_ip_per_minute` runs before authentication on
    | every API route and on Passport's OAuth endpoints. `per_minute`
    | runs once the token is authenticated, by client for a service
    | client's token, and by user for every other kind of token.
    |
    */

    'api' => [
        'per_minute' => (int) env('RATE_LIMIT_API_PER_MINUTE', 1800),
        'per_ip_per_minute' => (int) env('RATE_LIMIT_API_PER_IP_PER_MINUTE', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Rate Limiting
    |--------------------------------------------------------------------------
    |
    | The limits on requesting and checking sign-in codes, by IP address and by
    | email address or challenge, so codes can't be guessed by brute force.
    |
    | Impersonating someone has a limit of its own.
    |
    */

    'auth' => [
        'login_code' => [
            'request' => [
                'per_minute' => (int) env('RATE_LIMIT_AUTH_LOGIN_CODE_REQUEST_PER_MINUTE', 5),
                'per_email_per_minute' => (int) env('RATE_LIMIT_AUTH_LOGIN_CODE_REQUEST_PER_EMAIL_PER_MINUTE', 3),
            ],
            'verify' => [
                'per_minute' => (int) env('RATE_LIMIT_AUTH_LOGIN_CODE_VERIFY_PER_MINUTE', 10),
                'per_challenge_per_minute' => (int) env('RATE_LIMIT_AUTH_LOGIN_CODE_VERIFY_PER_CHALLENGE_PER_MINUTE', 5),
            ],
        ],

        'impersonate' => [
            'per_minute' => (int) env('RATE_LIMIT_AUTH_IMPERSONATE_PER_MINUTE', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Support Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Limits on the contact form, per minute, hour and day,
    | which allow a short burst but stop a steady stream.
    |
    */

    'support' => [
        'contact' => [
            'per_minute' => (int) env('RATE_LIMIT_SUPPORT_CONTACT_PER_MINUTE', 2),
            'per_hour' => (int) env('RATE_LIMIT_SUPPORT_CONTACT_PER_HOUR', 5),
            'per_day' => (int) env('RATE_LIMIT_SUPPORT_CONTACT_PER_DAY', 10),
        ],
    ],

];
