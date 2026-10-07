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
use InvalidArgumentException;

/**
 * Replaces a service client's IP allowlist. An empty list allows every IP address.
 */
readonly class UpdateServiceClientIpRestrictions
{
    public function __construct(
        private AuditServiceClientChange $auditChange,
        private CredentialAccess $credentials,
    ) {
    }

    /**
     * @param  list<non-empty-string>|null  $allowedIps
     *
     * @throws AuthorizationException
     */
    public function __invoke(OAuthClient $client, ?array $allowedIps, User $updatedBy): void
    {
        $this->credentials->decide($updatedBy, CredentialOperation::Modify, CredentialKind::ServiceClient, $this->apiUser($client))->authorize();

        if ($client->getAttributes()['revoked']) {
            throw new InvalidArgumentException('A revoked service client cannot be changed.');
        }

        $previous = $client->allowed_ips;

        $client->forceFill(['allowed_ips' => filled($allowedIps) ? array_values($allowedIps) : null])->save();

        ($this->auditChange)($client, AuditEvent::ServiceClientIpRestrictionsUpdated, ['allowed_ips' => $previous]);
    }

    private function apiUser(OAuthClient $client): User
    {
        $owner = $client->owner;

        if (! $owner instanceof User) {
            throw new InvalidArgumentException('Only a service client owned by an API user has IP restrictions.');
        }

        return $owner;
    }
}
