<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Production Application URL
    |--------------------------------------------------------------------------
    |
    | This value is the absolute, stable URL of the live, production environment.
    | It is used by non-production environments (staging, QA) for redirects,
    | email links, and general information about the application's true home.
    |
    */

    'production_url' => env('PRODUCTION_URL', 'https://northwestern.edu'),

    /*
    |--------------------------------------------------------------------------
    | Schedule Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your scheduled tasks.
    | when this value is null, application timezone is used.
    |
    */

    'schedule_timezone' => 'America/Chicago',

    /*
    |--------------------------------------------------------------------------
    | Date / Time Display Format
    |--------------------------------------------------------------------------
    |
    | This value defines the default format used when displaying dates and
    | timestamps throughout the application. You may customize this to
    | any valid PHP `date()` format.
    |
    */

    'datetime_display_format' => 'M j, Y g:i A',
];
