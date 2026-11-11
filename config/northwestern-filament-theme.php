<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Unit Lockup
    |--------------------------------------------------------------------------
    |
    | The unit lockup shown as every panel's logo, as a full
    | URL or a path in the public directory. Without one,
    | the Department Templates 4.0 wordmark is shown.
    |
    */

    'lockup' => env('NU_LOCKUP'),

    /*
    |--------------------------------------------------------------------------
    | Unit Information
    |--------------------------------------------------------------------------
    |
    | The unit responsible for this application, whose address, phone, fax (if any)
    | and email the Web Style Guide requires in the footer of each of its pages.
    |
    | Null shows Information Technology's details; an empty string hides the field.
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
