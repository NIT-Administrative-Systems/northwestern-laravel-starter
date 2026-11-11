<?php

declare(strict_types=1);

namespace App\Domains\User\Actions;

use App\Domains\Access\Enums\RoleModificationOrigin;
use App\Domains\Access\Enums\SystemRole;
use App\Domains\Access\Models\Role;
use App\Domains\Auth\Enums\AuthType;
use App\Domains\User\Actions\Directory\FindOrUpdateUserFromDirectory;
use App\Domains\User\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Saves a user. When another request saved the same username first, it returns
 * that user instead, and a new SSO user is given the Northwestern User role.
 *
 * @see FindOrUpdateUserFromDirectory
 */
final class PersistUserWithUniqueUsername
{
    public function __invoke(User $user): User
    {
        try {
            return DB::transaction(function () use ($user) {
                $user->save();

                if ($user->auth_type === AuthType::SSO && ! $user->hasRole(SystemRole::NorthwesternUser)) {
                    $role = Role::query()->where('name', SystemRole::NorthwesternUser->value)->firstOrFail();
                    $user->assignRoleWithAudit($role, RoleModificationOrigin::SsoProvisioning);
                }

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            return User::withTrashed()
                ->where('auth_type', $user->auth_type)
                ->where('username', $user->username)
                ->firstOrFail();
        }
    }
}
