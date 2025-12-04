<?php

declare(strict_types=1);

namespace App\Domains\User\Listeners;

use App\Domains\User\Enums\RoleModificationOriginEnum;
use App\Domains\User\Enums\SystemRoleEnum;
use App\Domains\User\Events\NetIdUpdated;
use App\Domains\User\Models\Role;
use App\Domains\User\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

/**
 * Processes **NetID Update** events received from Northwestern's Identity system.
 *
 * When a NetID is deactivated, deprovisioned, or put on security hold, this listener:
 * - Removes all role assignments except the default {@see SystemRoleEnum::NORTHWESTERN_USER} role
 * - Marks the user's NetID as inactive
 */
class ProcessNetIdUpdate implements ShouldQueue
{
    public function handle(NetIdUpdated $event): void
    {
        $user = User::query()
            ->sso()
            ->with('roles')
            ->firstWhere('username', $event->netId);

        if (! $user) {
            return;
        }

        DB::transaction(static function () use ($user, $event) {
            $user->roles
                ->reject(fn (Role $role) => $role->name === SystemRoleEnum::NORTHWESTERN_USER->value)
                ->whenNotEmpty(fn ($roles) => $user->removeRoleWithAudit(
                    roles: $roles->all(),
                    origin: RoleModificationOriginEnum::NETID_STATUS_CHANGE,
                    context: ['netid_action' => $event->action->value]
                ));

            $user->update(['netid_inactive' => true]);

            // Include any additional business logic here, if needed
        });
    }
}
