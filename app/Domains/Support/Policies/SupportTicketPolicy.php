<?php

declare(strict_types=1);

namespace App\Domains\Support\Policies;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\User\Models\User;

/**
 * Reading the support tickets people submitted, in Administration.
 *
 * The log is read-only, so there is nothing else to allow.
 */
class SupportTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(SystemPermission::ViewSupportTickets);
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        return $this->viewAny($user);
    }
}
