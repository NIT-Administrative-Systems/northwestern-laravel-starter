<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Core\Health\DirectorySearchCheck;
use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\OptimizedAppCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\RedisCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Facades\Health;
use Spatie\SecurityAdvisoriesHealthCheck\SecurityAdvisoriesCheck;

class HealthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Health::checks([
            // Sub-production databases scale to zero on AWS RDS. During idle periods
            // the check would trip on connection timeouts and report noise, so
            // the probe is prod-only.
            DatabaseCheck::new()
                ->if(App::isProduction()),

            // Only monitor Redis when the app actually talks to it.
            RedisCheck::new()
                ->if($this->usesRedisDriver()),

            // QueueCheck reads a heartbeat written by the `health:queue-check-heartbeat`
            // scheduled command. That command is prod-only (see routes/console.php), so
            // the check is prod-only too — otherwise non-prod would always report the
            // queue as stale. Heartbeat dispatch runs every 5 min in prod, so the
            // staleness threshold is 15 min to leave headroom and avoid flapping.
            QueueCheck::new()
                ->if(App::isProduction())
                ->failWhenHealthJobTakesLongerThanMinutes(15),

            CacheCheck::new(),
            ScheduleCheck::new(),

            DebugModeCheck::new()
                ->unless(App::isLocal()),
            OptimizedAppCheck::new()
                ->unless(App::isLocal()),

            // Per-check `everyXMinutes()` schedules only average out cleanly when
            // `RunHealthChecksCommand` ticks every minute on the scheduler. When
            // the endpoint runs checks on-demand (non-prod), any tick off-schedule
            // records the check as "skipped" instead of its real state. Leaving
            // these at the default every-minute cadence avoids that false reading;
            // Packagist + Directory Search are cheap enough to poll at that rate.
            SecurityAdvisoriesCheck::new()
                ->ignoredPackages([
                    //
                ]),

            DirectorySearchCheck::new(),
        ]);
    }

    private function usesRedisDriver(): bool
    {
        $redisDrivers = ['redis', 'predis'];

        return in_array(config('database.redis.client'), $redisDrivers, true)
            || in_array(config('cache.default'), $redisDrivers, true)
            || in_array(config('queue.default'), $redisDrivers, true)
            || in_array(config('session.driver'), $redisDrivers, true);
    }
}
