<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Applications;

use App\Domains\Api\CredentialAccess;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\Auth\Models\OAuthConnection;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;

/**
 * Revokes an application for everyone: the client, every access and refresh token issued to
 * it, its unredeemed authorization codes, and everyone's connection to it.
 *
 * An administrator's revoke is audited on them, with how many connections it removed. The
 * daily pruning of unused MCP clients revokes with no one to audit it on.
 */
readonly class RevokeOAuthApplication
{
    public function __construct(
        private CredentialAccess $credentials,
    ) {
    }

    /**
     * @param  User|null  $revokedBy  The administrator; null when the system prunes it
     *
     * @throws AuthorizationException
     */
    public function __invoke(OAuthClient $client, ?User $revokedBy = null): void
    {
        if ($revokedBy instanceof User) {
            $this->credentials->decide($revokedBy, CredentialOperation::Revoke, CredentialKind::of($client), null)->authorize();
        }

        DB::transaction(function () use ($client, $revokedBy): void {
            $tokens = Passport::token()->newQuery()->where('client_id', $client->getKey());

            Passport::refreshToken()->newQuery()
                ->whereIn('access_token_id', (clone $tokens)->select('id'))
                ->update(['revoked' => true]);

            $tokens->update(['revoked' => true]);

            Passport::authCode()->newQuery()->where('client_id', $client->getKey())->update(['revoked' => true]);

            $connectionsRemoved = OAuthConnection::query()->where('oauth_client_id', $client->getKey())->delete();

            $client->forceFill(['revoked' => true])->save();

            $revokedBy?->recordCustomAudit($client->isMcpClient() ? 'mcp_client_revoked' : 'application_revoked', [
                'client_id' => $client->getKey(),
                'name' => $client->name,
                'connections_removed' => $connectionsRemoved,
            ]);
        });
    }
}
