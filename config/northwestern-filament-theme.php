<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Unit Lockup
    |--------------------------------------------------------------------------
    |
    | This value is the unit lockup shown as the brand logo of every panel. It
    | may be a full URL or a path within the public directory. If null, the
    | legacy lockup is used, then the Department Templates 4.0 wordmark.
    |
    */

    'lockup' => env('NU_LOCKUP'),

    /*
    |--------------------------------------------------------------------------
    | Unit Information
    |--------------------------------------------------------------------------
    |
    | These values describe the unit responsible for this application.
    | The Web Style Guide requires its address, phone, fax (if any)
    | and email to appear in the footer of every page it serves.
    |
    | A null value falls back to the legacy northwestern-theme office key,
    | then to Information Technology. An empty string hides that field.
    |
    */

    'unit' => [
        'name' => env('NU_UNIT_NAME'),
        'address' => env('NU_UNIT_ADDRESS'),
        'city' => env('NU_UNIT_CITY'),
        'phone' => env('NU_UNIT_PHONE'),
        'fax' => env('NU_UNIT_FAX'),
        'email' => env('NU_UNIT_EMAIL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Footer Links
    |--------------------------------------------------------------------------
    |
    | Quick links are label => URL pairs shown beside the required links,
    | which can't be removed. Social accounts are network => URL pairs
    | shown under "Connect", and an empty array hides that section.
    |
    | Supported: "bluesky", "facebook", "flickr", "instagram", "linkedin",
    |            "pinterest", "rss", "spotify", "threads", "tiktok",
    |            "tumblr", "vimeo", "wordpress", "x", "youtube"
    |
    */

    'footer' => [
        'links' => [],

        'social' => [
            'facebook' => 'https://www.facebook.com/NorthwesternU',
            'instagram' => 'https://instagram.com/northwesternu',
            'youtube' => 'https://www.youtube.com/user/NorthwesternU',
        ],
    ],

];
