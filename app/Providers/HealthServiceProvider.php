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
            // Databases below production scale to zero on RDS,
            // and one still waking up would fail the check.
            DatabaseCheck::new()
                ->if(App::isProduction()),

            RedisCheck::new()
                ->if($this->usesRedis()),

            // The heartbeat it reads is dispatched every
            // five minutes, in production only, so 15
            // minutes leaves room for a slow queue.
            QueueCheck::new()
                ->if(App::isProduction())
                ->failWhenHealthJobTakesLongerThanMinutes(15),

            CacheCheck::new(),
            ScheduleCheck::new(),

            DebugModeCheck::new()
                ->unless(App::isLocal()),
            OptimizedAppCheck::new()
                ->unless(App::isLocal()),

            // Outside production the endpoint runs the checks on demand, where a check with
            // its own schedule reads as skipped between runs, so these run every minute.
            SecurityAdvisoriesCheck::new()
                ->ignoredPackages([
                    //
                ]),

            // Optional locally, but a deployed environment without a key should still fail.
            DirectorySearchCheck::new()
                ->unless(App::isLocal() && blank(config('nusoa.directorySearch.apiKey'))),
        ]);
    }

    /**
     * Determine whether the cache, queue or sessions are stored in Redis.
     */
    private function usesRedis(): bool
    {
        return in_array('redis', [
            config('cache.default'),
            config('queue.default'),
            config('session.driver'),
        ], true);
    }
}
