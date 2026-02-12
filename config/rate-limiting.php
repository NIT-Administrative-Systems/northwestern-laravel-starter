<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | API Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Controls the global rate limit applied to all API routes via the
    | throttleApi() middleware in bootstrap/app.php. This is keyed by
    | the authenticated user ID, or by IP for unauthenticated requests.
    |
    */

    'api' => [
        'per_minute' => (int) env('RATE_LIMIT_API_PER_MINUTE', 1800),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limits for the local login code authentication flow. These
    | protect the code request and verification endpoints from brute
    | force attacks, using layered limits per IP and per identifier.
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
    | Limits for the contact/support form submission endpoint. Uses a
    | tiered approach (per-minute, per-hour, per-day) to allow short
    | bursts while preventing sustained abuse over longer windows.
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
