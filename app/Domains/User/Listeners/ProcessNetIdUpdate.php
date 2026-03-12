<?php

declare(strict_types=1);

namespace App\Domains\User\Listeners;

use App\Domains\Auth\Enums\RoleModificationOrigin;
use App\Domains\Auth\Enums\SystemRole;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Events\NetIdUpdated;
use App\Domains\User\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

/**
 * Processes **NetID Update** events received from Northwestern's Identity system.
 *
 * When a NetID is deactivated, deprovisioned, or put on security hold, this listener:
 * - Removes all role assignments except the default {@see SystemRole::NorthwesternUser} role
 * - Marks the user's NetID as inactive
 */
class ProcessNetIdUpdate implements ShouldQueue
{
    public function handle(NetIdUpdated $event): void
    {
        DB::transaction(static function () use ($event) {
            $user = User::query()
                ->sso()
                ->lockForUpdate()
                ->with('roles')
                ->firstWhere('username', $event->netId);

            if (! $user) {
                return;
            }

            $user->roles
                ->reject(fn (Role $role) => $role->name === SystemRole::NorthwesternUser->value)
                ->whenNotEmpty(fn ($roles) => $user->removeRoleWithAudit(
                    roles: $roles->all(),
                    origin: RoleModificationOrigin::NetIdStatusChange,
                    context: ['netid_action' => $event->action->value]
                ));

            $user->update(['netid_inactive' => true]);

            // Add custom deprovisioning logic here, if needed
        });
    }
}
