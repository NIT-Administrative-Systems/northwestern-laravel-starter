<?php

declare(strict_types=1);

namespace App\Domains\Support\Actions\Announcements;

use App\Domains\Support\Enums\AnnouncementStatus;
use App\Domains\Support\Jobs\NotifyAnnouncementAudience;
use App\Domains\Support\Models\Announcement;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Publishes a draft: it shows from `$startsAt` until `$endsAt`, if given. With `$notify`, its
 * audience is told once, as soon as it starts: now, or by `announcements:notify` for one
 * that starts later.
 */
readonly class PublishAnnouncement
{
    public function __invoke(Announcement $announcement, CarbonInterface $startsAt, ?CarbonInterface $endsAt, bool $notify): void
    {
        if ($announcement->status !== AnnouncementStatus::Draft) {
            throw new InvalidArgumentException('Only a draft can be published.');
        }

        if ($endsAt instanceof CarbonInterface && $endsAt->lte($startsAt)) {
            throw new InvalidArgumentException('An announcement must end after it starts.');
        }

        $announcement->forceFill([
            'published_at' => Carbon::now(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'notify_audience' => $notify,
        ])->save();

        if ($notify && $announcement->status === AnnouncementStatus::Live) {
            NotifyAnnouncementAudience::dispatch($announcement);
        }
    }
}
