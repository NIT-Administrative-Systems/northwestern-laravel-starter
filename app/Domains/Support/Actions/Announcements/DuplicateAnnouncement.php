<?php

declare(strict_types=1);

namespace App\Domains\Support\Actions\Announcements;

use App\Domains\Support\Models\Announcement;
use App\Domains\User\Models\User;

/**
 * Copies an announcement's content and audience into a new draft, for announcing something
 * again or ending one early and starting over.
 */
readonly class DuplicateAnnouncement
{
    public function __invoke(Announcement $announcement, User $by): Announcement
    {
        $copy = $announcement->replicate(['published_at', 'starts_at', 'ends_at', 'notify_audience', 'notified_at', 'created_by_user_id']);
        $copy->forceFill(['created_by_user_id' => $by->getKey()])->save();

        return $copy;
    }
}
