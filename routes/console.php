<?php

declare(strict_types=1);

use App\Console\Commands\SendApiTokenExpirationNotificationsCommand;
use Illuminate\Database\Console\PruneCommand;
use Illuminate\Support\Facades\Schedule;
use Laravel\Telescope\Console\PruneCommand as TelescopePruneCommand;
use Livewire\Features\SupportConsoleCommands\Commands\S3CleanupCommand as CleanTemporaryS3FilesCommand;
use Spatie\Health\Commands\DispatchQueueCheckJobsCommand;
use Spatie\Health\Commands\RunHealthChecksCommand;

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
Schedule::command(PruneCommand::class)->daily();

if (config('auth.api.expiration_notifications.enabled')) {
    Schedule::command(SendApiTokenExpirationNotificationsCommand::class)
        ->dailyAt('09:00');
}

/*
|--------------------------------------------------------------------------
| ⏱️ Hourly Commands
|--------------------------------------------------------------------------
| Commands executed every hour to process frequent updates or time-sensitive
| operations requiring regular intervals.
*/

//

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

Schedule::command(DispatchQueueCheckJobsCommand::class)->everyMinute();
Schedule::command(RunHealthChecksCommand::class)->everyMinute();
