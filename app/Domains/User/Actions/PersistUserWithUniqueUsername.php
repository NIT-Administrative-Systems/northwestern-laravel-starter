<?php

declare(strict_types=1);

namespace App\Domains\User\Actions;

use App\Domains\User\Actions\Directory\FindOrUpdateUserFromDirectory;
use App\Domains\User\Enums\AuthTypeEnum;
use App\Domains\User\Enums\SystemRoleEnum;
use App\Domains\User\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Persists a user with a unique username by handling potential uniqueness constraint violations
 * and retrieving the existing user if a conflict occurs.
 *
 * For SSO users, the default "Northwestern User" system role is assigned.
 *
 * {@see FindOrUpdateUserFromDirectory}
 */
final class PersistUserWithUniqueUsername
{
    public function __invoke(User $user): User
    {
        return DB::transaction(function () use ($user) {
            try {
                $user->save();

                if ($user->auth_type === AuthTypeEnum::SSO) {
                    $user->assignRole(SystemRoleEnum::NORTHWESTERN_USER);
                }

                return $user;
            } catch (UniqueConstraintViolationException) {
                return User::query()
                    ->where('auth_type', $user->auth_type)
                    ->where('username', $user->username)
                    ->firstOrFail();
            }
        });
    }
}
