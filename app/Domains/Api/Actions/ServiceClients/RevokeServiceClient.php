<?php

declare(strict_types=1);

namespace App\Domains\Api\Actions\ServiceClients;

use App\Domains\Api\CredentialAccess;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Api\Models\OAuthClient;
use App\Domains\Core\Enums\AuditEvent;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;

/**
 * Revokes a service client and every access token it holds, so the integration loses
 * access at once rather than when its current token expires.
 */
readonly class RevokeServiceClient
{
    public function __construct(
        private AuditServiceClientChange $auditChange,
        private CredentialAccess $credentials,
    ) {
    }

    /**
     * @param  User|null  $revokedBy  The administrator; null when the system revokes it
     *
     * @throws AuthorizationException
     */
    public function __invoke(OAuthClient $client, ?User $revokedBy = null): void
    {
        $owner = $client->owner;
        $this->credentials->decide($revokedBy, CredentialOperation::Revoke, CredentialKind::ServiceClient, $owner instanceof User ? $owner : null)->authorize();

        DB::transaction(function () use ($client): void {
            Passport::token()->newQuery()
                ->where('client_id', $client->getKey())
                ->where('revoked', false)
                ->update(['revoked' => true]);

            $client->forceFill(['revoked' => true])->save();

            ($this->auditChange)($client, AuditEvent::ServiceClientRevoked);
        });
    }
}
