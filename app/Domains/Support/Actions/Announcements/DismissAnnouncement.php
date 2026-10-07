<?php

declare(strict_types=1);

namespace App\Domains\Support\Actions\Announcements;

use App\Domains\Support\Models\Announcement;
use App\Domains\Support\Models\AnnouncementDismissal;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

/**
 * Hides an announcement from a person's banner. It stays on their Announcements page. Critical
 * announcements can't be dismissed, and an administrator impersonating someone can't dismiss
 * for them.
 */
readonly class DismissAnnouncement
{
    /**
     * @throws AuthorizationException
     */
    public function __invoke(Announcement $announcement, User $user): void
    {
        if ($user->isImpersonated()) {
            throw new AuthorizationException('You can\'t dismiss announcements while impersonating someone.');
        }

        if (! $announcement->isDismissible() || ! Announcement::query()->live()->visibleTo($user)->whereKey($announcement->getKey())->exists()) {
            throw new AuthorizationException('This announcement can\'t be dismissed.');
        }

        AnnouncementDismissal::query()->firstOrCreate(
            ['announcement_id' => $announcement->getKey(), 'user_id' => $user->getKey()],
            ['dismissed_at' => Carbon::now()],
        );
    }
}
