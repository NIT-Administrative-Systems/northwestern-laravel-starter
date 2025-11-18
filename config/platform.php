<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Stakeholder Accounts
    |--------------------------------------------------------------------------
    |
    | These configuration values define special application stakeholders such as
    | Super Administrators. They are primarily used during seeding (see the
    | StakeholderSeeder) to automatically provision and assign roles for known
    | NetIDs in a given environment.
    |
    | `SUPER_ADMIN_NETIDS` should be a comma-separated list of NetIDs.
    |
    | If managing environment variables is inconvenient for your workflow, you may
    | alternatively hard-code an array of NetIDs in this file and bypass the env()
    | lookup entirely. Only users listed here will receive stakeholder roles during
    | seeding.
    |
    */
    'stakeholders' => [
        'super_admins' => env('SUPER_ADMIN_NETIDS', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Wildcard Photo Sync
    |--------------------------------------------------------------------------
    |
    | When enabled, the application will attempt to store user Wildcard photos
    | in the object storage and sync them with the user record. This is not
    | always necessary, as the application may not require this feature.
    |
    | Supported: bool
    */

    'wildcard_photo_sync' => env('WILDCARD_PHOTO_SYNC_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Mail Capture URL
    |--------------------------------------------------------------------------
    |
    | This setting controls whether a link to the MailPit server (or similar)
    | is shown in the navigation to all users. This should be available to
    | every user when in use, since anyone testing may need to see mail.
    |
    | Supported: string|null
    */
    'mail-capture' => [
        'url' => env('MAIL_CAPTURE_URL'),
    ],
];
