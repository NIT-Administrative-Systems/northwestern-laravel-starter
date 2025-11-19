<?php

declare(strict_types=1);

namespace App\Domains\User\Actions;

use App\Domains\User\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class PersistUserWithUniqueUsername
{
    public function __invoke(User $user): User
    {
        return DB::transaction(function () use ($user) {
            try {
                $user->save();

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
