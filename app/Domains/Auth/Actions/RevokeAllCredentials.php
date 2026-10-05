<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\Actions\Api\RevokeServiceClient;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\User\Models\User;
use Northwestern\SysDev\Chassis\Passport\AccessRevoker;

/**
 * Revokes everything a user can call the API with: their personal access tokens, the
 * applications they connected, and, for an API user, the service clients it owns. Used when
 * an account is deleted or deactivated.
 */
readonly class RevokeAllCredentials
{
    public function __construct(
        private AccessRevoker $accessRevoker,
        private RevokeServiceClient $revokeServiceClient,
    ) {
    }

    public function __invoke(User $user): void
    {
        $this->accessRevoker->revokeAll($user);

        OAuthClient::query()
            ->whereMorphedTo('owner', $user)
            ->where('revoked', false)
            ->each(fn (OAuthClient $client) => ($this->revokeServiceClient)($client));
    }
}
