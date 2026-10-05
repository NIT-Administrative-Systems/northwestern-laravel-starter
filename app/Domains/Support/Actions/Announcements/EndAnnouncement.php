<?php

declare(strict_types=1);

namespace App\Domains\Support\Actions\Announcements;

use App\Domains\Support\Enums\AnnouncementStatus;
use App\Domains\Support\Models\Announcement;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Ends a live announcement now. It leaves the banner and stays on the Announcements page as a
 * past announcement. An ended announcement can't be published again; duplicate it instead.
 */
readonly class EndAnnouncement
{
    public function __invoke(Announcement $announcement): void
    {
        if ($announcement->status !== AnnouncementStatus::Live) {
            throw new InvalidArgumentException('Only a live announcement can be ended.');
        }

        $announcement->forceFill(['ends_at' => Carbon::now()])->save();
    }
}
