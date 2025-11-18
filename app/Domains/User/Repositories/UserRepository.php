<?php

declare(strict_types=1);

namespace App\Domains\User\Repositories;

use App\Domains\User\Enums\AuthTypeEnum;
use App\Domains\User\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UserRepository
{
    public function findOrNewByNetId(string $netId): ?User
    {
        $user = User::query()
            ->withTrashed()
            ->where('auth_type', AuthTypeEnum::SSO)
            ->where('username', strtolower($netId))
            ->first();

        if (isset($user) && $user->trashed()) {
            $user->restore();
        }

        return $user ?: new User([
            'username' => strtolower($netId),
            'auth_type' => AuthTypeEnum::SSO,
        ]);
    }

    public function findByEmail(string $email): ?User
    {
        return User::whereAuthType(AuthTypeEnum::SSO)
            ->where('email', 'ilike', $email)
            ->first();
    }

    public function findLocalUserByEmail(string $email): ?User
    {
        return User::whereAuthType(AuthTypeEnum::LOCAL)
            ->where('email', 'ilike', $email)
            ->first();
    }

    public function save(User $user): User
    {
        return DB::transaction(function () use ($user) {
            try {
                $user->save();
            } catch (UniqueConstraintViolationException) {
                return User::query()
                    ->where('auth_type', $user->auth_type)
                    ->where('username', $user->username)
                    ->first();
            }

            return $user;
        });
    }

    public function updateWildcardPhoto(User $user, ?string $photoS3Key): User
    {
        $user->update([
            'wildcard_photo_s3_key' => $photoS3Key,
            'wildcard_photo_last_synced_at' => Carbon::now(),
        ]);

        return $user;
    }
}
