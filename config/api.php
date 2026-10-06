<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | API Authentication & Configuration
    |--------------------------------------------------------------------------
    |
    | Configure all settings related to API access, authentication, and usage.
    | This includes a master switch, rate limiting, request logging parameters,
    | and token management features like expiration notifications.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Enable API Access
    |--------------------------------------------------------------------------
    |
    | A global toggle to enable or disable all API functionality. When set to
    | false, all API routes will respond with a 503 response. This is useful
    | for maintenance or temporary disabling the API layer.
    |
    */

    'enabled' => env('API_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | API Authentication Realm
    |--------------------------------------------------------------------------
    | This value is used in the `WWW-Authenticate` header for protected API
    | endpoints. It identifies the API "realm" so clients know which API
    | is requesting bearer credentials.
    |
    | This value will also be shown in access token expiration notifications
    | if the feature is enabled.
    |
    */

    // APP_NAME directly: config/api.php loads before the framework's app config, so config('app.name') is still empty here.
    'auth_realm' => env('APP_NAME', 'Laravel') . ' API',

    /*
    |--------------------------------------------------------------------------
    | API Request Logging
    |--------------------------------------------------------------------------
    |
    | Enable lightweight logging for requests authenticated via access tokens.
    | It's an internal record for troubleshooting to supplement external
    | observability platforms.
    |
    | For high-throughput apps, consider disabling this feature or configure
    | sampling to prevent high storage usage and minimize the I/O overhead.
    |
    */

    'request_logging' => [
        'enabled' => env('API_REQUEST_LOGGING_ENABLED', true),

        // Threshold (in milliseconds) used to categorize a request as "slow"
        // for internal monitoring/display purposes.
        'slow_request_threshold_ms' => (int) env('API_REQUEST_LOGGING_SLOW_THRESHOLD_MS', 500),

        // Retention: see platform.retention.api_request_logs.

        /*
        |--------------------------------------------------------------------------
        | Request Sampling
        |--------------------------------------------------------------------------
        |
        | When enabled, only a defined percentage of *successful* requests will be
        | logged to conserve resources. Note that *failed requests and errors* are
        | always logged, irrespective of this sampling setting.
        |
        */

        'sampling' => [
            'enabled' => env('API_REQUEST_LOGGING_SAMPLING_ENABLED', false),

            // The sampling rate for successful requests (float between 0.0 and 1.0).
            // Example: 0.1 logs 10% of successful requests.
            'rate' => (float) env('API_REQUEST_LOGGING_SAMPLE_RATE', 1.0),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Client Secret Expiration Notifications
    |--------------------------------------------------------------------------
    |
    | When to email an API user's contact address about a service client whose
    | secret is about to expire, so the integration's owner can rotate it in
    | time. A client stops working when its secret expires.
    |
    | 'intervals': Days before expiration to send notifications
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Personal Access Tokens
    |--------------------------------------------------------------------------
    |
    | People who hold the Create Personal Access Tokens permission create these
    | on the Account page to call the API as themselves. Each token chooses its
    | lifetime, up to `max_lifetime_days`, and a person can hold at most
    | `max_active` working tokens at once.
    |
    */

    'personal_access_tokens' => [
        'max_lifetime_days' => (int) env('API_PERSONAL_ACCESS_TOKEN_MAX_LIFETIME_DAYS', 365),
        'max_active' => (int) env('API_PERSONAL_ACCESS_TOKEN_MAX_ACTIVE', 10),
    ],

    'expiration_notifications' => [
        'enabled' => env('API_CLIENT_SECRET_EXPIRATION_NOTIFICATIONS_ENABLED', true),
        'intervals' => [30, 14, 7, 3, 1],
    ],

];
