<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Domains\User\Models\UserLoginRecord;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule as ScheduleFacade;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * The daily `model:prune` of the domain models in
 * routes/console.php. The scheduler can start
 * from any working directory, so the models
 * it finds must not depend on which one.
 */
#[CoversNothing]
final class ModelPruneScheduleTest extends TestCase
{
    public function test_domain_models_are_pruned_when_the_scheduler_starts_outside_the_project(): void
    {
        config(['platform.retention.login_records' => 30]);
        $expired = UserLoginRecord::factory()->create(['logged_in_at' => now()->subDays(31)]);
        $kept = UserLoginRecord::factory()->create(['logged_in_at' => now()->subDays(29)]);

        $workingDirectory = getcwd();
        chdir(sys_get_temp_dir());

        try {
            $command = $this->scheduledDomainPrune();
            Artisan::call('model:prune' . explode('model:prune', $command, 2)[1]);
        } finally {
            chdir((string) $workingDirectory);
        }

        $this->assertModelMissing($expired);
        $this->assertModelExists($kept);
    }

    /**
     * Define the schedule again from the current working directory and
     * return the command of the `model:prune` that searches paths.
     */
    private function scheduledDomainPrune(): string
    {
        $schedule = new Schedule();
        $this->app->instance(Schedule::class, $schedule);
        ScheduleFacade::clearResolvedInstance(Schedule::class);

        require base_path('routes/console.php');

        /** @var Event $event */
        $event = collect($schedule->events())
            ->sole(fn (Event $event): bool => str_contains((string) $event->command, 'model:prune') && str_contains((string) $event->command, '--path'));

        return (string) $event->command;
    }
}
