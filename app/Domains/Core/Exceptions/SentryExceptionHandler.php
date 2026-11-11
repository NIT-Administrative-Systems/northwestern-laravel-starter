<?php

declare(strict_types=1);

namespace App\Domains\Core\Exceptions;

use Illuminate\Contracts\Auth\Authenticatable;
use Northwestern\SysDev\Chassis\Exceptions\SentryExceptionHandler as BaseSentryExceptionHandler;

/**
 * Handles reporting exceptions to Sentry with enriched user context.
 *
 * {@see userContext()} is the browser SDK's
 * user context too, so PHP and JavaScript
 * reports identify people the same way.
 */
class SentryExceptionHandler extends BaseSentryExceptionHandler
{
    #[\Override]
    public function userContext(Authenticatable $user): array
    {
        /** @var \App\Domains\User\Models\User $user */
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'primary_affiliation' => $user->primary_affiliation,
            'auth_type' => $user->auth_type,
        ];
    }
}
