<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * The daily `queue:prune-failed` and `queue:prune-batches` in routes/console.php, which keep
 * the queue's own tables for `platform.retention.failed_jobs` and `job_batches` days.
 */
#[CoversNothing]
final class QueuePruneScheduleTest extends TestCase
{
    public function test_failed_jobs_are_pruned_daily_after_thirty_days(): void
    {
        $this->assertStringEndsWith('queue:prune-failed --hours=720', $this->scheduled('queue:prune-failed')->command);
        $this->assertSame('0 0 * * *', $this->scheduled('queue:prune-failed')->expression);
    }

    public function test_job_batches_are_pruned_daily_after_thirty_days_finished_or_not(): void
    {
        $this->assertStringEndsWith('queue:prune-batches --hours=720 --unfinished=720 --cancelled=720', $this->scheduled('queue:prune-batches')->command);
        $this->assertSame('0 0 * * *', $this->scheduled('queue:prune-batches')->expression);
    }

    private function scheduled(string $command): Event
    {
        return collect(resolve(Schedule::class)->events())
            ->sole(fn (Event $event): bool => str_contains((string) $event->command, $command));
    }
}
