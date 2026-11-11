<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Local Sign-In
    |--------------------------------------------------------------------------
    |
    | Lets people without a NetID sign in with a code sent to their email, such as
    | collaborators, clients and partners from outside Northwestern. Their local
    | accounts are created by administrators or when people are added in bulk.
    |
    */

    'enabled' => env('LOCAL_AUTH_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Fixed Code
    |--------------------------------------------------------------------------
    |
    | Every code is 123456 instead of a random one, so the email flow can be
    | tested again and again. It works only in the local, testing and ci
    | environments, and anywhere else the application refuses to boot.
    |
    */

    'use_fixed_code' => (bool) env('LOCAL_AUTH_USE_FIXED_CODE', false),

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    |
    | How many codes an email address may request in an hour, and how
    | many one IP address may request across every email, so that
    | one address can't use up the limits of many other people.
    |
    */

    'rate_limit_per_hour' => (int) env('LOCAL_AUTH_RATE_LIMIT_PER_HOUR', 10),

    'rate_limit_per_ip_per_hour' => (int) env('LOCAL_AUTH_RATE_LIMIT_PER_IP_PER_HOUR', 20),

    /*
    |--------------------------------------------------------------------------
    | Codes
    |--------------------------------------------------------------------------
    |
    | A code's length and lifetime, the wrong guesses that lock it and for
    | how long, the wrong codes an email may enter in a day across all
    | of its codes, and the wait before the next code can be sent.
    |
    | Retention: see platform.retention.login_challenges.
    |
    */

    'code' => [
        'digits' => (int) env('LOCAL_AUTH_CODE_DIGITS', 6),
        'expires_in_minutes' => (int) env('LOCAL_AUTH_CODE_EXPIRES_MINUTES', 10),
        'max_attempts' => (int) env('LOCAL_AUTH_CODE_MAX_ATTEMPTS', 8),
        'lock_minutes' => (int) env('LOCAL_AUTH_CODE_LOCK_MINUTES', 15),
        'max_failed_per_day' => (int) env('LOCAL_AUTH_CODE_MAX_FAILED_PER_DAY', 20),
        'resend_cooldown_seconds' => (int) env('LOCAL_AUTH_CODE_RESEND_COOLDOWN', 30),
    ],

];
