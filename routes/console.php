<?php

declare(strict_types=1);

use App\Console\Commands\NotifyAnnouncementAudiencesCommand;
use App\Console\Commands\PruneMcpClientsCommand;
use App\Console\Commands\RevokeIneligibleCredentialsCommand;
use App\Console\Commands\SendClientSecretExpirationNotificationsCommand;
use App\Console\Commands\SendPersonalAccessTokenExpirationNotificationsCommand;
use Illuminate\Database\Console\PruneCommand;
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
|--------------------------------------------------------------------------|
| ❗⚠️ IMPORTANT ⚠️❗
|--------------------------------------------------------------------------|
| When defining schedules in non-production environments, take into account
| that our RDS databases automatically scale down to zero during periods of
| inactivity. Running frequent or unnecessary tasks (especially over nights
| or weekends) can repeatedly wake the databases, negating the cost-saving
| benefits we aim to achieve.
|
|--------------------------------------------------------------------------|
| 🔄 Environment-Specific Scheduling
|--------------------------------------------------------------------------|
| To prevent unnecessary database wake-ups in non-production environments,
| reduced-frequency schedules should *always* be wrapped in an
| `App::isProduction()` conditional.
|
| Example:
|   if (App::isProduction()) {
|       Schedule::job(...)->everyMinute();
|   } else {
|       Schedule::job(...)->weekdays()->at('12:00');
|   }
|
|--------------------------------------------------------------------------|
| 🗓️ Task Grouping Best Practice
|--------------------------------------------------------------------------|
| Whenever possible, group non-production schedules close together in time.
| If the database is already awake for one task, later tasks executed will
| complete more efficiently without triggering additional cold-starts.
*/

/*
|--------------------------------------------------------------------------
| 🌞 Daily Commands
|--------------------------------------------------------------------------
| Commands executed once per day to handle tasks such as maintenance, data
| syncing, and periodic cleanups to ensure the application runs smoothly.
*/

Schedule::command(TelescopePruneCommand::class)->daily();
Schedule::command(CleanTemporaryS3FilesCommand::class)->daily();
Schedule::command(PruneCommand::class, ['--path' => glob('app/Domains/*/Models')])->daily();
Schedule::command(PruneCommand::class, ['--model' => [HealthCheckResultHistoryItem::class]])->daily();

if (config('api.expiration_notifications.enabled')) {
    Schedule::command(SendClientSecretExpirationNotificationsCommand::class)
        ->dailyAt('09:00');
    Schedule::command(SendPersonalAccessTokenExpirationNotificationsCommand::class)
        ->dailyAt('09:00');
}

// Delete revoked and expired OAuth tokens and codes. Keep them past the 30-day refresh token
// lifetime: refresh tokens are found through their access tokens when access is revoked.
Schedule::command(PurgeCommand::class, ['--hours' => 24 * 31])->daily();

// Self-registered MCP clients nobody connected, or nobody uses any more.
Schedule::command(PruneMcpClientsCommand::class)->daily();

/*
|--------------------------------------------------------------------------
| ⏱️ Hourly Commands
|--------------------------------------------------------------------------
| Commands executed every hour to process frequent updates or time-sensitive
| operations requiring regular intervals.
*/

// The API refuses these credentials on every request already; this marks them revoked.
Schedule::command(RevokeIneligibleCredentialsCommand::class)->hourly();

/*
|--------------------------------------------------------------------------
| 📅 Weekly Commands
|--------------------------------------------------------------------------
| Commands executed once per week for tasks such as recurring notifications
| or summary reports, typically scheduled on a specific day of the week.
*/

//

/*
|--------------------------------------------------------------------------
| 📅 Weekday Commands
|--------------------------------------------------------------------------
| Commands executed only on weekdays for business-related notifications or
| tasks that align with regular business hours.
*/

//

/*
|--------------------------------------------------------------------------
| ⚡ Frequent Jobs
|--------------------------------------------------------------------------
| High-priority or real-time processing jobs that need to run frequently
| to handle time-sensitive data or events.
*/

// ScheduleCheckHeartbeatCommand writes a single cache entry — no DB or queue
// traffic — so it runs in every environment. Without it, Spatie's ScheduleCheck
// reports "the schedule did not run yet" indefinitely.
Schedule::command(ScheduleCheckHeartbeatCommand::class)->everyMinute();

// Notifies the audience of a scheduled announcement once it starts, when its author asked.
// Hourly outside production, so idle databases can scale to zero.
if (App::isProduction()) {
    Schedule::command(NotifyAnnouncementAudiencesCommand::class)->everyFiveMinutes();
} else {
    Schedule::command(NotifyAnnouncementAudiencesCommand::class)->hourly();
}

if (App::isProduction()) {
    // The other two health commands touch infrastructure on every tick and have
    // no consumer in non-prod. Run `php artisan health:check` on demand in
    // local/dev when you need a snapshot.
    //
    // Queue heartbeat runs every 5 min (QueueCheck staleness threshold is 15 min
    // in HealthServiceProvider so there's no flapping). RunHealthChecksCommand
    // ticks every minute so individual checks decide their own cadence via
    // ->everyMinute() / ->everyFiveMinutes() etc. on the Check instance.
    Schedule::command(DispatchQueueCheckJobsCommand::class)->everyFiveMinutes();
    Schedule::command(RunHealthChecksCommand::class)->everyMinute();
}
