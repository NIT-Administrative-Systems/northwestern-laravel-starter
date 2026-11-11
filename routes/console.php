<?php

declare(strict_types=1);

use App\Console\Commands\DeactivateInactiveLocalUsersCommand;
use App\Console\Commands\NotifyAnnouncementAudiencesCommand;
use App\Console\Commands\PruneMcpClientsCommand;
use App\Console\Commands\RevokeIneligibleCredentialsCommand;
use App\Console\Commands\SendClientSecretExpirationNotificationsCommand;
use App\Console\Commands\SendPersonalAccessTokenExpirationNotificationsCommand;
use Illuminate\Database\Console\PruneCommand;
use Illuminate\Queue\Console\PruneBatchesCommand;
use Illuminate\Queue\Console\PruneFailedJobsCommand;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schedule;
use Laravel\Passport\Console\PurgeCommand;
use Laravel\Telescope\Console\PruneCommand as TelescopePruneCommand;
use Livewire\Features\SupportConsoleCommands\Commands\S3CleanupCommand as CleanTemporaryS3FilesCommand;
use Spatie\Health\Commands\DispatchQueueCheckJobsCommand;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Spatie\Health\Commands\ScheduleCheckHeartbeatCommand;
use Spatie\Health\Models\HealthCheckResultHistoryItem;

/*
|--------------------------------------------------------------------------
| Scheduling Outside Production
|--------------------------------------------------------------------------
|
| Databases below production scale to zero on RDS while
| idle, and every task that touches one wakes it up.
|
| Run frequent tasks in production only, with a weekday slot everywhere else:
|
| ```php
| if (App::isProduction()) {
|     Schedule::command(...)->everyFiveMinutes();
| } else {
|     Schedule::command(...)->weekdays()->at('12:00');
| }
| ```
|
| Put those slots together, so one wake-up serves them all.
|
*/

/*
|--------------------------------------------------------------------------
| Daily
|--------------------------------------------------------------------------
|
| Pruning, cleanup and reminders, once a day.
|
*/

Schedule::command(TelescopePruneCommand::class)->daily();
Schedule::command(CleanTemporaryS3FilesCommand::class)->daily();
// model:prune resolves --path from the project root and expands
// the wildcard, so the scheduler can start in any directory.
Schedule::command(PruneCommand::class, ['--path' => 'app/Domains/*/Models'])->daily();
Schedule::command(PruneCommand::class, ['--model' => [HealthCheckResultHistoryItem::class]])->daily();

if (config('api.client_secret_expiration_notifications.enabled')) {
    Schedule::command(SendClientSecretExpirationNotificationsCommand::class)->dailyAt('09:00');
}

if (config('api.personal_access_tokens.expiration_notifications.enabled')) {
    Schedule::command(SendPersonalAccessTokenExpirationNotificationsCommand::class)->dailyAt('09:00');
}

// OAuth tokens and codes that expired more than 31 days
// ago, revoked or not. Without `--expired`, Passport
// would delete everything revoked on the next run.
//
// Revoking access finds the refresh tokens through
// their access tokens, so an access token has to
// outlive its refresh token's 30-day lifetime.
Schedule::command(PurgeCommand::class, ['--expired', '--hours' => 24 * 31])->daily();

// Self-registered MCP clients nobody connected, or nobody uses any more.
Schedule::command(PruneMcpClientsCommand::class)->daily();

// The queue's own tables, which model:prune doesn't reach. Their retention is
// in days, like the others under platform.retention, and null keeps them.
if (filled($failedJobDays = config('platform.retention.failed_jobs'))) {
    Schedule::command(PruneFailedJobsCommand::class, ['--hours' => 24 * (int) $failedJobDays])->daily();
}

if (filled($jobBatchDays = config('platform.retention.job_batches'))) {
    $jobBatchHours = 24 * (int) $jobBatchDays;
    Schedule::command(PruneBatchesCommand::class, ['--hours' => $jobBatchHours, '--unfinished' => $jobBatchHours, '--cancelled' => $jobBatchHours])->daily();
}

// Local accounts nobody has signed in to for that many days.
if (filled(config('platform.accounts.local_inactivity_days'))) {
    Schedule::command(DeactivateInactiveLocalUsersCommand::class)->daily();
}

/*
|--------------------------------------------------------------------------
| Hourly
|--------------------------------------------------------------------------
*/

// The API already refuses these credentials; this marks them revoked.
if (App::isProduction()) {
    Schedule::command(RevokeIneligibleCredentialsCommand::class)->hourly();
}

/*
|--------------------------------------------------------------------------
| Weekdays
|--------------------------------------------------------------------------
|
| Outside production, the tasks that run often in production run
| together once a weekday, so idle databases can stay asleep.
|
*/

if (! App::isProduction()) {
    Schedule::command(RevokeIneligibleCredentialsCommand::class)->weekdays()->at('12:00');
    Schedule::command(NotifyAnnouncementAudiencesCommand::class)->weekdays()->at('12:00');
}

/*
|--------------------------------------------------------------------------
| Every Few Minutes
|--------------------------------------------------------------------------
*/

// Only a cache write, so it runs everywhere; the Schedule health check reads it.
Schedule::command(ScheduleCheckHeartbeatCommand::class)->everyMinute();

// Notifies an announcement's audience when it starts, if its author asked.
if (App::isProduction()) {
    Schedule::command(NotifyAnnouncementAudiencesCommand::class)->everyFiveMinutes();
}

if (App::isProduction()) {
    // Nobody reads these outside production; run `php artisan health:check` there.
    Schedule::command(DispatchQueueCheckJobsCommand::class)->everyFiveMinutes();
    Schedule::command(RunHealthChecksCommand::class)->everyMinute();
}
