<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Local User Authentication
    |--------------------------------------------------------------------------
    |
    | Enable passwordless authentication for external (non-Northwestern) users.
    | When enabled, administrators can create local user accounts that can
    | authenticate using a verification code sent via email.
    |
    | Use Case: External collaborators, clients, or partners who don't have
    | Northwestern credentials but need to access specific features.
    |
    */

    // Enable/disable local authentication system
    'enabled' => env('LOCAL_AUTH_ENABLED', true),

    // Use a fixed, predictable verification code (e.g. "123456") instead of random codes.
    // Useful for local development and CI where you need to repeatedly verify codes.
    // Blocked from running in production, develop, and QA as a safety measure.
    'use_fixed_code' => (bool) env('LOCAL_AUTH_USE_FIXED_CODE', false),

    // Maximum requests per hour (applies to both form submissions and code sends)
    'rate_limit_per_hour' => env('LOCAL_AUTH_RATE_LIMIT_PER_HOUR', 10),

    // Where to send users after a successful login (route name or path)
    'redirect_after_login' => env('LOCAL_AUTH_REDIRECT_AFTER_LOGIN', '/'),

    'code' => [
        // Number of digits in the verification code
        'digits' => 6,

        // Minutes before the code expires
        'expires_in_minutes' => 10,

        // Maximum failed attempts before lockout
        'max_attempts' => 8,

        // Minutes a challenge stays locked after max attempts
        'lock_minutes' => 15,

        // Cooldown before resending another code
        'resend_cooldown_seconds' => 30,

        // Days to retain login challenge records before pruning
        // Set to null to disable automatic pruning
        'retention_days' => (int) env('LOGIN_CHALLENGE_RETENTION_DAYS', 30),
    ],

];
