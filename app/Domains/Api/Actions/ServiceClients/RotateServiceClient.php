<?php

declare(strict_types=1);

namespace App\Domains\Api\Actions\ServiceClients;

use App\Domains\Api\CredentialAccess;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Api\Models\OAuthClient;
use App\Domains\User\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * Replaces a service client with a new one for the same API user. The old client keeps
 * working, so the integration can switch secrets without downtime; revoke it once the
 * integration uses the new one.
 */
readonly class RotateServiceClient
{
    public function __construct(
        private CreateServiceClient $createServiceClient,
        private CredentialAccess $credentials,
    ) {
    }

    /**
     * @param  non-empty-string  $name
     * @param  list<non-empty-string>|null  $allowedIps
     * @return array{0: non-empty-string, 1: OAuthClient} The replacement's plaintext secret and the replacement
     *
     * @throws AuthorizationException
     */
    public function __invoke(
        OAuthClient $previous,
        User $rotatedBy,
        string $name,
        CarbonInterface $secretExpiresAt,
        ?array $allowedIps = null,
    ): array {
        $apiUser = $previous->owner;

        if (! $apiUser instanceof User) {
            throw new InvalidArgumentException('Only a service client owned by an API user can be rotated.');
        }

        $this->credentials->decide($rotatedBy, CredentialOperation::Modify, CredentialKind::ServiceClient, $apiUser)->authorize();

        if ($previous->getAttributes()['revoked']) {
            throw new InvalidArgumentException('A revoked service client cannot be rotated.');
        }

        return ($this->createServiceClient)(
            apiUser: $apiUser,
            name: $name,
            secretExpiresAt: $secretExpiresAt,
            allowedIps: $allowedIps,
            rotatedFrom: $previous,
            createdBy: $rotatedBy,
        );
    }
}
