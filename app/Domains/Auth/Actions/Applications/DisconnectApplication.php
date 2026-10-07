<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Applications;

use App\Domains\Api\CredentialAccess;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Auth\Models\OAuthConnection;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Northwestern\SysDev\Chassis\Passport\AccessRevoker;

/**
 * Disconnects an application from a person's account: revokes the access tokens, refresh
 * tokens and authorization codes it holds for them, and removes the connection. Other
 * people's connections to the same application are untouched.
 */
readonly class DisconnectApplication
{
    public function __construct(
        private AccessRevoker $accessRevoker,
        private CredentialAccess $credentials,
    ) {
    }

    /**
     * @param  User|null  $disconnectedBy  The person or an administrator, audited on the person when it's an administrator; null when the system disconnects it, also audited
     *
     * @throws AuthorizationException
     */
    public function __invoke(OAuthConnection $connection, ?User $disconnectedBy): void
    {
        // The sweep disconnects deleted accounts too.
        $user = $connection->user()->withTrashed()->first();
        $client = $connection->loadMissing('oauth_client')->oauth_client;

        $this->credentials->decide($disconnectedBy, CredentialOperation::Revoke, CredentialKind::of($client), $user)->authorize();

        DB::transaction(function () use ($connection, $user): void {
            if ($user instanceof User) {
                $this->accessRevoker->revokeClient($user, $connection->oauth_client_id);
            }

            $connection->delete();
        });

        if ($user instanceof User && ! $user->is($disconnectedBy)) {
            $user->recordCustomAudit('application_disconnected', [
                'oauth_client_id' => $connection->oauth_client_id,
                'application' => $client->name,
            ]);
        }
    }
}
