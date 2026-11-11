<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Result Stores
    |--------------------------------------------------------------------------
    |
    | Where check results are stored. The database store keeps five days of
    | history; the results can be kept in more than one store at a time.
    |
    */

    'result_stores' => [
        Spatie\Health\ResultStores\EloquentHealthResultStore::class => [
            'connection' => env('HEALTH_DB_CONNECTION', env('DB_CONNECTION')),
            'model' => Spatie\Health\Models\HealthCheckResultHistoryItem::class,
            'keep_history_for_days' => 5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Emails HEALTH_NOTIFICATION_EMAIL when checks start failing, at most once
    | an hour, so a failing check doesn't send a message every minute. Slack
    | works as well once laravel/slack-notification-channel is installed.
    |
    */

    'notifications' => [
        'enabled' => (bool) env('HEALTH_NOTIFICATIONS_ENABLED', false),

        'notifications' => [
            Spatie\Health\Notifications\CheckFailedNotification::class => ['mail'],
        ],

        'notifiable' => Spatie\Health\Notifications\Notifiable::class,

        'throttle_notifications_for_minutes' => 60,
        'throttle_notifications_key' => 'health:latestNotificationSentAt:',

        'mail' => [
            'to' => env('HEALTH_NOTIFICATION_EMAIL'),

            'from' => [
                'address' => env('MAIL_FROM_ADDRESS'),
                'name' => env('MAIL_FROM_NAME'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fresh Results
    |--------------------------------------------------------------------------
    |
    | The starter doesn't use Oh Dear, but the JSON results controller behind
    | /api/health reads `always_send_fresh_results` from here, so it runs
    | the checks on each request rather than return the last results.
    |
    */

    'oh_dear_endpoint' => [
        'enabled' => false,
        'always_send_fresh_results' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Schedule Heartbeat
    |--------------------------------------------------------------------------
    |
    | A URL the Schedule check pings when it passes, so a monitor can alert you
    | when the scheduler stops running, since the pings stop arriving there.
    |
    */

    'schedule' => [
        'heartbeat_url' => env('SCHEDULE_HEARTBEAT_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Display
    |--------------------------------------------------------------------------
    |
    | The local results page's theme, "light" or "dark", and whether Horizon
    | hides the finished HealthQueueJob runs in its list of silenced jobs.
    |
    */

    'theme' => 'light',

    'silence_health_queue_job' => true,

    /*
    |--------------------------------------------------------------------------
    | Health Endpoint
    |--------------------------------------------------------------------------
    |
    | What /api/health answers when a check fails, and the
    | X-Secret-Token every request to it must carry. It
    | refuses every request until the token is set.
    |
    | Spatie counts a skipped check as a failure by default. HealthServiceProvider
    | skips the database, queue and Redis checks where they don't apply, and a
    | check skipped that way mustn't turn /api/health into a 503 response.
    |
    */

    'json_results_failure_status' => 503,

    'secret_token' => env('HEALTH_SECRET_TOKEN'),

    'treat_skipped_as_failure' => false,

];
