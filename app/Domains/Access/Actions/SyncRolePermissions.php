<?php

declare(strict_types=1);

namespace App\Domains\Access\Actions;

use App\Domains\Access\Enums\RoleTypeEnum;
use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Access\Models\Permission;
use App\Domains\Access\Models\Role;
use App\Domains\User\Models\User;

/**
 * Sets a role's permissions to the ones an editor chose, keeping only those the role's type
 * allows and the editor may grant:
 *
 * - an API Integration role holds API permissions only;
 * - any other role holds interface permissions, and system-managed ones only from an editor with
 *   Manage All. An editor without it leaves the role's system-managed permissions as they were.
 *
 * Anything else the editor sent is dropped, so a list the form hid, or a value sent by hand,
 * grants nothing. The change is audited on the role.
 */
final readonly class SyncRolePermissions
{
    /**
     * @param  list<string>  $chosen  Permission names
     */
    public function __invoke(Role $role, array $chosen, User $editor): void
    {
        // The role's type may have changed in the same save.
        $role->load('role_type', 'permissions');

        $isApiRole = $role->role_type?->slug === RoleTypeEnum::ApiIntegration;
        $grantsSystemPermissions = ! $isApiRole && $editor->hasPermissionTo(SystemPermission::ManageAll);

        $permissions = Permission::query()->get();

        $grantable = $permissions
            ->filter(fn (Permission $permission): bool => $permission->system_managed
                ? $grantsSystemPermissions
                : $permission->api_relevant === $isApiRole)
            ->pluck('name')
            ->all();

        $kept = $isApiRole || $grantsSystemPermissions
            ? []
            : array_intersect($role->permissions->pluck('name')->all(), $permissions->where('system_managed', true)->pluck('name')->all());

        $role->syncPermissionsWithAudit(array_values(array_unique([...array_intersect($chosen, $grantable), ...$kept])));
    }
}
