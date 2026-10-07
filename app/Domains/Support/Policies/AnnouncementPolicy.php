<?php

declare(strict_types=1);

namespace App\Domains\Support\Policies;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Support\Models\Announcement;
use App\Domains\User\Models\User;

/**
 * Managing announcements in Administration, all of it behind `ManageAnnouncements`. Reading
 * them is for their audience, through {@see Announcement::visibleTo()}, not this policy.
 */
class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(SystemPermission::ManageAnnouncements);
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $user->can(SystemPermission::ManageAnnouncements);
    }

    public function create(User $user): bool
    {
        return $user->can(SystemPermission::ManageAnnouncements);
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $user->can(SystemPermission::ManageAnnouncements);
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->can(SystemPermission::ManageAnnouncements);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(SystemPermission::ManageAnnouncements);
    }
}
