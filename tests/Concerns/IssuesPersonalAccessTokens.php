<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Api\Actions\PersonalAccessTokens\CreatePersonalAccessToken;
use App\Domains\Api\Enums\TokenExpiration;
use App\Domains\Api\Models\OAuthToken;
use App\Domains\User\Models\User;

/**
 * Gives a person the permission to hold personal access tokens and creates one, as the
 * Account page would.
 */
trait IssuesPersonalAccessTokens
{
    /**
     * @param  list<string>  $scopes
     * @return array{0: string, 1: OAuthToken} The bearer token and its record
     */
    protected function personalAccessToken(User $user, array $scopes = []): array
    {
        if (! $user->hasPermissionTo(SystemPermission::CreatePersonalAccessTokens)) {
            $user->givePermissionTo(SystemPermission::CreatePersonalAccessTokens);
        }

        return resolve(CreatePersonalAccessToken::class)($user, 'Test token', $scopes, TokenExpiration::OneMonth);
    }
}
