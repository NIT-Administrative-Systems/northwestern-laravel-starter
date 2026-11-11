<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enable the Contact Form
    |--------------------------------------------------------------------------
    |
    | Turns the contact form on. While it is off, the form and its routes don't
    | exist, and nothing in the interface links to them or mentions support.
    |
    */

    'enabled' => (bool) env('SUPPORT_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Documentation Link
    |--------------------------------------------------------------------------
    |
    | The application's user documentation, linked from the Help menu. The link
    | is hidden while this is empty, whether or not the contact form is on.
    |
    */

    'documentation_url' => env('SUPPORT_DOCUMENTATION_URL'),

    /*
    |--------------------------------------------------------------------------
    | Limited Support Warning
    |--------------------------------------------------------------------------
    |
    | Shows a banner on the contact form saying that support is limited in this
    | environment. It is on by default in every environment but production.
    |
    */

    'limited_support_warning' => (bool) env('SUPPORT_LIMITED_WARNING', env('APP_ENV') !== 'production'),

    /*
    |--------------------------------------------------------------------------
    | Gateway Driver
    |--------------------------------------------------------------------------
    |
    | The system that receives support tickets: "mail" or "team-dynamix". It
    | is resolved through the TicketSystemGateway interface, so bind your
    | own gateway to it in a service provider to send them elsewhere.
    |
    */

    'driver' => env('SUPPORT_DRIVER', 'mail'),

    /*
    |--------------------------------------------------------------------------
    | Mail Gateway
    |--------------------------------------------------------------------------
    |
    | Tickets sent by email go to this address. Each gets a reference such as
    | "SUP-47" in its subject and in the confirmation the person receives,
    | made from the prefix, while TeamDynamix numbers its own tickets.
    |
    | Mail is also the fallback when another driver fails, and
    | the fallback ticket's reference uses the same prefix.
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
    |
    | Where TeamDynamix tickets are filed and how they are classified. The API
    | credentials TeamDynamix is called with are in config/team-dynamix.php.
    |
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
