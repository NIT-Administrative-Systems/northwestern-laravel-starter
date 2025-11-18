<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Brand Lockup Image
    |--------------------------------------------------------------------------
    |
    | The primary lockup image used throughout the application. This can be
    | left unchanged to use the default Northwestern lockup image, or you
    | can provide a custom department/school/unit lockup image.
    |
    | Supported: Full image URL or a relative path to an asset stored in the
    |            public directory.
    |
    */

    'lockup' => env('NU_THEME_LOCKUP', 'https://common.northwestern.edu/v8/css/images/northwestern.svg'),

    /*
    |--------------------------------------------------------------------------
    | Office Information
    |--------------------------------------------------------------------------
    |
    | Contact information for the primary office or department responsible for
    | the application. This information is displayed in the footer of the
    | application.
    |
    */

    'office' => [
        'name' => env('NU_THEME_OFFICE_NAME', 'Information Technology'),
        'addr' => env('NU_THEME_OFFICE_ADDR', '1800 Sherman Ave'),
        'city' => env('NU_THEME_OFFICE_CITY', 'Evanston, IL 60201'),
        'phone' => env('NU_THEME_OFFICE_PHONE', '847-491-4357 (1-HELP)'),
        'email' => env('NU_THEME_OFFICE_EMAIL', 'consultant@northwestern.edu'),
    ],

    // If specified, the Sentry browser SDK will be activated.
    'sentry-dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),
    'sentry-enable-apm-js' => env('SENTRY_ENABLE_APM_FOR_JS', true),
    'sentry-traces-sample-rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.0),

    'globalAlerts' => [
        App\Domains\Core\GlobalAlerts\UserImpersonated::class,
    ],
];
