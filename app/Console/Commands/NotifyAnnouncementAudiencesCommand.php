<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Support\Jobs\NotifyAnnouncementAudience;
use App\Domains\Support\Models\Announcement;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Notifies the audiences of announcements that have started,
 * when their authors asked for it. Publishing notifies at
 * once for an announcement that starts straight away;
 * this catches the ones scheduled to start later.
 */
class NotifyAnnouncementAudiencesCommand extends Command
{
    protected $signature = 'announcements:notify';

    protected $description = 'Notify the audiences of announcements that have started, when their authors asked for it';

    public function handle(): int
    {
        $count = 0;

        foreach (Announcement::query()->live()->where('notify_audience', true)->whereNull('notified_at')->lazyById() as $announcement) {
            NotifyAnnouncementAudience::dispatch($announcement);
            $count++;
        }

        $this->components->info("Queued notifications for {$count} " . Str::plural('announcement', $count) . '.');

        return self::SUCCESS;
    }
}
