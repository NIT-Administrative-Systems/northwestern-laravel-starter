<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enable API Access
    |--------------------------------------------------------------------------
    |
    | Turns the REST API on or off. While it is off, every API route answers 404, no
    | credential can get a new token, and existing tokens are refused. Nothing is
    | revoked, so every credential works again once the API is turned back on.
    |
    */

    'enabled' => env('API_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Authentication Realm
    |--------------------------------------------------------------------------
    |
    | The realm named in the `WWW-Authenticate` header of the API's 401 responses.
    | It reads APP_NAME directly, because this file loads before the app config.
    |
    */

    'auth_realm' => env('APP_NAME', 'Laravel') . ' API',

    /*
    |--------------------------------------------------------------------------
    | Request Logging
    |--------------------------------------------------------------------------
    |
    | Records each request made with an access token, for troubleshooting
    | in Administration alongside an observability tool. A busy API may
    | turn it off or sample it, to keep its storage and writes down.
    |
    | Requests slower than `slow_request_threshold_ms` are marked as
    | slow. While sampling is on, only `rate` (0.0 to 1.0) of the
    | successful requests are kept, though failures always are.
    |
    | Retention: see platform.retention.api_request_logs.
    |
    */

    'request_logging' => [
        'enabled' => env('API_REQUEST_LOGGING_ENABLED', true),
        'slow_request_threshold_ms' => (int) env('API_REQUEST_LOGGING_SLOW_THRESHOLD_MS', 500),

        'sampling' => [
            'enabled' => env('API_REQUEST_LOGGING_SAMPLING_ENABLED', false),
            'rate' => (float) env('API_REQUEST_LOGGING_SAMPLE_RATE', 1.0),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Client Secret Expiration Notifications
    |--------------------------------------------------------------------------
    |
    | A service client stops working when its secret expires, so
    | the contact address of its API user is emailed this many
    | days before, giving the owner time to have it rotated.
    |
    */

    'client_secret_expiration_notifications' => [
        'enabled' => env('API_CLIENT_SECRET_EXPIRATION_NOTIFICATIONS_ENABLED', true),
        'intervals' => [30, 14, 7, 3, 1],
    ],

    /*
    |--------------------------------------------------------------------------
    | Personal Access Tokens
    |--------------------------------------------------------------------------
    |
    | People with the Create Personal Access Tokens permission create
    | these on the Account page to call the API as themselves. Each
    | one lasts up to `max_lifetime_days`, and a person may hold
    | up to `max_active` that haven't expired or been revoked.
    |
    | Owners are emailed this many days before
    | a token expires, unless they turn the
    | reminders off in their preferences.
    |
    */

    'personal_access_tokens' => [
        'max_lifetime_days' => (int) env('API_PERSONAL_ACCESS_TOKEN_MAX_LIFETIME_DAYS', 365),
        'max_active' => (int) env('API_PERSONAL_ACCESS_TOKEN_MAX_ACTIVE', 10),

        'expiration_notifications' => [
            'enabled' => env('API_PERSONAL_ACCESS_TOKEN_EXPIRATION_NOTIFICATIONS_ENABLED', true),
            'intervals' => [30, 14, 7, 3, 1],
        ],
    ],

];
