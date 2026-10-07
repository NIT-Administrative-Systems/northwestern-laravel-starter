<?php

declare(strict_types=1);

namespace App\Domains\Api\Actions;

use App\Domains\Api\Actions\Applications\DisconnectApplication;
use App\Domains\Api\Actions\PersonalAccessTokens\RevokePersonalAccessToken;
use App\Domains\Api\Actions\ServiceClients\RevokeServiceClient;
use App\Domains\Api\Models\OAuthClient;
use App\Domains\Api\Models\OAuthConnection;
use App\Domains\Api\Models\OAuthToken;
use App\Domains\User\Models\User;
use Northwestern\SysDev\Chassis\Passport\AccessRevoker;

/**
 * Revokes everything a user can call the API with: their personal access tokens, the
 * applications and MCP clients they connected, and, for an API user, the service clients it
 * owns. Each is revoked through its own action, so each is audited. Used when an account is
 * deleted.
 */
readonly class RevokeAllCredentials
{
    public function __construct(
        private AccessRevoker $accessRevoker,
        private RevokePersonalAccessToken $revokePersonalAccessToken,
        private DisconnectApplication $disconnectApplication,
        private RevokeServiceClient $revokeServiceClient,
    ) {
    }

    public function __invoke(User $user): void
    {
        OAuthToken::query()->personal()->where('user_id', $user->getKey())->where('revoked', false)
            ->each(fn (OAuthToken $token) => ($this->revokePersonalAccessToken)($token, null));

        OAuthConnection::query()->where('user_id', $user->getKey())
            ->each(fn (OAuthConnection $connection) => ($this->disconnectApplication)($connection, null));

        OAuthClient::query()
            ->whereMorphedTo('owner', $user)
            ->where('revoked', false)
            ->each(fn (OAuthClient $client) => ($this->revokeServiceClient)($client, null));

        // Anything left without a connection, such as a token issued before connections were recorded.
        $this->accessRevoker->revokeAll($user);
    }
}
