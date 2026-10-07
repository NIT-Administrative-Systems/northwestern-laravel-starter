<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Console\Commands\NotifyAnnouncementAudiencesCommand;
use App\Domains\Support\Jobs\NotifyAnnouncementAudience;
use App\Domains\Support\Models\Announcement;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(NotifyAnnouncementAudiencesCommand::class)]
final class NotifyAnnouncementAudiencesCommandTest extends TestCase
{
    public function test_it_queues_started_announcements_not_yet_notified(): void
    {
        Queue::fake();
        $started = Announcement::factory()->create(['notify_audience' => true]);
        Announcement::factory()->create(['notify_audience' => true, 'notified_at' => now()]);
        Announcement::factory()->scheduled()->create(['notify_audience' => true]);
        Announcement::factory()->create();

        $this->artisan(NotifyAnnouncementAudiencesCommand::class)
            ->expectsOutputToContain('Queued notifications for 1 announcement(s).')
            ->assertSuccessful();

        Queue::assertPushed(NotifyAnnouncementAudience::class, fn (NotifyAnnouncementAudience $job): bool => $job->announcement->is($started));
        Queue::assertPushed(NotifyAnnouncementAudience::class, 1);
    }
}
