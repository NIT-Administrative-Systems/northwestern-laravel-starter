<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Production Application URL
    |--------------------------------------------------------------------------
    |
    | The address of the production environment. Other environments link to it,
    | such as the lockdown page, which sends people to the real application.
    |
    */

    'production_url' => env('PRODUCTION_URL', 'https://northwestern.edu'),

    /*
    |--------------------------------------------------------------------------
    | Date / Time Display Format
    |--------------------------------------------------------------------------
    |
    | How dates and times are shown across the application, in PHP's `date()` format.
    |
    */

    'datetime_display_format' => 'M j, Y g:i A',

    /*
    |--------------------------------------------------------------------------
    | Default Time Zone
    |--------------------------------------------------------------------------
    |
    | The time zone a new account starts with, until the person changes it.
    |
    */

    'default_user_timezone' => env('DEFAULT_USER_TIMEZONE', 'America/Chicago'),

    /*
    |--------------------------------------------------------------------------
    | Stakeholder Accounts
    |--------------------------------------------------------------------------
    |
    | NetIDs that StakeholderSeeder gives the Super Administrator role on
    | every deploy. Set SUPER_ADMIN_NETIDS to a comma-separated list,
    | or write the array here instead of reading the environment.
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
    | Downloads each person's Wildcard photo into object
    | storage and keeps it with their account. Leave
    | it off when the application shows no photos.
    |
    */

    'wildcard_photo_sync' => env('WILDCARD_PHOTO_SYNC_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Environment Lockdown
    |--------------------------------------------------------------------------
    |
    | When enabled, only people who hold a role beyond the default one may use
    | the application. Everyone else sees the lockdown page, which keeps a
    | staging or demo environment private from people who find its URL.
    |
    | It is on by default except in production, local development, CI and tests.
    | Anyone impersonating passes through, and so do the routes named below,
    | which sign people in and out, impersonate, or show the lockdown page.
    |
    */

    'lockdown' => [
        'enabled' => env('ENVIRONMENT_LOCKDOWN_ENABLED', match (env('APP_ENV')) {
            'production', 'local', 'testing', 'ci' => false,
            default => true,
        }),

        'exempted_routes' => [
            // Authentication
            'login-oauth-redirect',
            'login-oauth-callback',
            'login-oauth-logout',
            'login-websso-logout',
            'filament.app.auth.login',
            'filament.app.auth.login-code',
            'logout',

            // Impersonation
            'impersonate',
            'impersonate.leave',

            // Lockdown Page
            'filament.app.environment-lockdown',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail Capture URL
    |--------------------------------------------------------------------------
    |
    | Links the Developer Tools group in Administration to the server that captures
    | outgoing mail, such as Mailpit. Only people who may view Telescope see it.
    |
    */

    'mail-capture' => [
        'url' => env('MAIL_CAPTURE_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    |
    | Days to keep each kind of record before model:prune deletes it; null keeps it.
    |
    | Audits are kept by default because they often back FERPA, HIPAA or university
    | record-keeping requirements, so check yours before you set a limit on them.
    | Impersonation logs, a small security record, are kept by default as well.
    |
    | A busy API may prefer an observability tool such as Sentry to keeping
    | its request logs for long. Exports count from when they finish, and
    | the export files are deleted along with them when they're pruned.
    |
    | Notifications are the bell in each panel's top bar. Failed jobs and job
    | batches are the queue's own tables, pruned by queue:prune-failed and
    | queue:prune-batches. Imports take their failed rows with them, and
    | support tickets are kept by default as a record of what's asked.
    |
    */

    'retention' => [
        'audits' => env('AUDIT_RETENTION_DAYS'),
        'login_records' => env('LOGIN_RECORD_RETENTION_DAYS', 365),
        'impersonation_logs' => env('IMPERSONATION_LOG_RETENTION_DAYS'),
        'login_challenges' => env('LOGIN_CHALLENGE_RETENTION_DAYS', 30),
        'api_request_logs' => env('API_REQUEST_LOG_RETENTION_DAYS', 90),
        'exports' => env('EXPORT_RETENTION_DAYS', 7),
        'notifications' => env('NOTIFICATION_RETENTION_DAYS', 90),
        'failed_jobs' => env('FAILED_JOB_RETENTION_DAYS', 30),
        'job_batches' => env('JOB_BATCH_RETENTION_DAYS', 30),
        'imports' => env('IMPORT_RETENTION_DAYS', 30),
        'failed_import_rows' => env('FAILED_IMPORT_ROW_RETENTION_DAYS', 30),
        'support_tickets' => env('SUPPORT_TICKET_RETENTION_DAYS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Account Lifecycle
    |--------------------------------------------------------------------------
    |
    | Days a local account may go without a sign-in before it is deactivated by
    | the daily users:deactivate-inactive-local. A new account gets the same
    | time for its first sign-in. Null leaves every local account active.
    |
    | The last sign-in comes from the sign-in records, so keep the
    | login_records retention at least as long as this setting.
    |
    */

    'accounts' => [
        'local_inactivity_days' => env('LOCAL_ACCOUNT_INACTIVITY_DAYS'),
    ],

];
