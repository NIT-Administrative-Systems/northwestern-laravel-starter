<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Applications;

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
    ) {
    }

    /**
     * @param  User  $disconnectedBy  The person, or an administrator; an administrator's disconnect is audited on the person
     *
     * @throws AuthorizationException
     */
    public function __invoke(OAuthConnection $connection, User $disconnectedBy): void
    {
        if ($disconnectedBy->isImpersonated()) {
            throw new AuthorizationException('Applications cannot be disconnected while impersonating.');
        }

        $connection->loadMissing(['user', 'oauth_client']);
        $user = $connection->user;
        $application = $connection->oauth_client?->name;

        DB::transaction(function () use ($connection, $user): void {
            $this->accessRevoker->revokeClient($user, $connection->oauth_client_id);
            $connection->delete();
        });

        if ($user->isNot($disconnectedBy)) {
            $user->recordCustomAudit('application_disconnected', [
                'oauth_client_id' => $connection->oauth_client_id,
                'application' => $application,
            ]);
        }
    }
}
