<?php

declare(strict_types=1);

namespace App\Domains\User\Actions;

use App\Domains\User\Models\LoginLink;
use App\Domains\User\Models\User;
use SensitiveParameter;

/**
 * Validates a login link token and returns the associated user.
 *
 * Checks:
 * - Token exists and matches
 * - Link has not been expired
 * - Link has not been used
 */
readonly class ValidateLoginLink
{
    public function __invoke(
        #[SensitiveParameter]
        string $rawToken
    ): ?User {
        $hashedToken = LoginLink::hashFromPlain($rawToken);

        return LoginLink::query()
            ->where('token', $hashedToken)
            ->unused()
            ->notExpired()
            ->first()
            ?->user;
    }
}
