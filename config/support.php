<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Feature Toggle
    |--------------------------------------------------------------------------
    |
    | When disabled, no support routes are registered and no UI elements
    | are rendered. Set to true in projects that want the contact form.
    |
    */

    'enabled' => (bool) env('SUPPORT_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Limited Support Warning
    |--------------------------------------------------------------------------
    |
    | When true, displays a warning banner on the contact form indicating
    | that support is limited in this environment. Defaults to true for
    | all non-production environments when support is enabled.
    |
    */

    'limited_support_warning' => (bool) env('SUPPORT_LIMITED_WARNING', env('APP_ENV') !== 'production'),

    /*
    |--------------------------------------------------------------------------
    | Gateway Driver
    |--------------------------------------------------------------------------
    |
    | The ticket system backend to use for creating support tickets.
    | Supported: "mail", "team-dynamix"
    |
    | The gateway is resolved via the TicketSystemGateway interface. To use
    | a custom gateway, rebind the interface in your AppServiceProvider.
    |
    */

    'driver' => env('SUPPORT_DRIVER', 'mail'),

    /*
    |--------------------------------------------------------------------------
    | Mail Gateway
    |--------------------------------------------------------------------------
    |
    | Configuration for the "mail" driver. When mail is the primary driver,
    | tickets are delivered via email to the address below.
    |
    | The reference prefix is used to generate a human-readable ticket
    | identifier (e.g., "SUP-47") that appears in the email subject line
    | and confirmation sent to the user. This only applies when the mail
    | gateway is active — external providers like TeamDynamix return their
    | own native ticket references automatically.
    |
    | The mail gateway also serves as the automatic fallback when a
    | non-mail primary driver fails, in which case the same prefix
    | is used for the fallback reference.
    |
    */

    'mail' => [
        'to' => env('SUPPORT_MAIL_TO', 'your-team@northwestern.edu'),
        'reference_prefix' => env('SUPPORT_REFERENCE_PREFIX', 'SUP'),
    ],

    /*
    |--------------------------------------------------------------------------
    | TeamDynamix Gateway
    |--------------------------------------------------------------------------
    */

    'team-dynamix' => [
        'assign_to_group' => env('TDX_ASSIGNEE_ID'),
        'ticket_type' => env('TDX_TICKET_TYPE', 'Default'),
        'form_type' => env('TDX_FORM_TYPE', 'NU Base Service Request'),
        'ticket_status' => env('TDX_TICKET_STATUS', 'New'),
        'ticket_priority' => env('TDX_TICKET_PRIORITY', 'Low (P4)'),
        'service' => env('TDX_SERVICE', env('APP_NAME')),
    ],

];
