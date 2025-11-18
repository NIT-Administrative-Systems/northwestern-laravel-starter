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
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Facades\Health;
use Spatie\SecurityAdvisoriesHealthCheck\SecurityAdvisoriesCheck;

class HealthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $checks = [
            QueueCheck::new(),
            CacheCheck::new(),
            ScheduleCheck::new(),
            DebugModeCheck::new()
                ->unless(App::isLocal()),
            OptimizedAppCheck::new()
                ->unless(App::isLocal()),
            SecurityAdvisoriesCheck::new()
                ->ignoredPackages([
                    //
                ]),
            DirectorySearchCheck::new(),
        ];

        /**
         * Commonly, sub-production databases are hosted through AWS RDS and scale to
         * zero when not in use. This causes intermittent connection failures that
         * would trigger false alarms in health checks.
         */
        if (App::isProduction()) {
            array_unshift($checks, DatabaseCheck::new());
        }

        Health::checks($checks);
    }
}
