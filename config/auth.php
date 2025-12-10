<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eager-load-eloquent',
            'model' => App\Domains\User\Models\User::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Local User Authentication
    |--------------------------------------------------------------------------
    |
    | Enable passwordless authentication for external (non-Northwestern) users.
    | When enabled, administrators can create local user accounts and invite
    | them via email with magic links for secure, password-free access.
    |
    | Use Case: External collaborators, clients, or partners who don't have
    | Northwestern credentials but need to access specific features.
    |
    */

    'local' => [
        // Enable/disable local authentication system
        'enabled' => env('LOCAL_AUTH_ENABLED', true),

        // How long login links remain valid
        'login_link_expiration_minutes' => env('LOCAL_AUTH_LOGIN_LINK_EXPIRATION_MINUTES', 15),

        // Maximum requests per hour (applies to both form submissions and login link sends)
        'rate_limit_per_hour' => env('LOCAL_AUTH_RATE_LIMIT_PER_HOUR', 10),

        // Where to send users after a successful login (route name or path)
        'redirect_after_login' => env('LOCAL_AUTH_REDIRECT_AFTER_LOGIN', '/'),
    ],

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

    'api' => [

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

        'auth_realm' => config('app.name') . ' API',

        /*
        |--------------------------------------------------------------------------
        | API Rate Limiting Configuration
        |--------------------------------------------------------------------------
        |
        | Settings for global API request rate limiting to mitigate abuse and ensure
        | consistent service availability. Applied via the 'api' middleware group.
        |
        | 'max_attempts': The maximum number of requests permitted per time window.
        |
        */

        'rate_limit' => [
            'max_attempts' => env('API_RATE_LIMIT_ATTEMPTS', 1800),
        ],

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
            'slow_request_threshold_ms' => 500,

            /*
            |--------------------------------------------------------------------------
            | Data Retention
            |--------------------------------------------------------------------------
            |
            | Automatically delete logs older than this many days to prevent unbounded
            | database growth.
            |
            | Set to null to disable automatic pruning (not recommended for production).
            | For high-traffic apps, consider using a dedicated observability tool
            | instead (Sentry, New Relic, Datadog, etc.).
            |
            */

            'retention_days' => (int) env('API_REQUEST_LOG_RETENTION_DAYS', 90),

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
        | Access Token Expiration Notifications
        |--------------------------------------------------------------------------
        |
        | Configure when and how often users are notified about expiring API
        | tokens. Multiple notification intervals ensure users have adequate
        | warning to rotate tokens before they expire.
        |
        | 'intervals': Days before expiration to send notifications
        |
        */

        'expiration_notifications' => [
            'enabled' => env('API_ACCESS_TOKEN_EXPIRATION_NOTIFICATIONS_ENABLED', true),
            'intervals' => [30, 14, 7, 3, 1],
        ],

        /*
        |--------------------------------------------------------------------------
        | Demo Access Token
        |--------------------------------------------------------------------------
        |
        | The plaintext Bearer token to be assigned to the demo API user account
        | created by the DemoUserSeeder.
        |
        | If not specified, a secure random token will be auto-generated. Set this
        | to a known, simple value in local development for convenient API testing
        | with consistent credentials across database refreshes. The system will
        | automatically hash this value before storing it.
        |
        */

        'demo_user_token' => env('API_DEMO_USER_ACCESS_TOKEN'),

    ],

];
