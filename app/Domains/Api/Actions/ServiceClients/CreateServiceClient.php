<?php

declare(strict_types=1);

namespace App\Domains\Api\Actions\ServiceClients;

use App\Domains\Api\CredentialAccess;
use App\Domains\Api\Enums\ClientOrigin;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Api\Models\OAuthClient;
use App\Domains\Auth\Enums\AuthType;
use App\Domains\Core\Enums\AuditEvent;
use App\Domains\User\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Passport\ClientRepository;

/**
 * Creates a client credentials client for an API user. The integration exchanges the
 * client's ID and secret at `/oauth/token` for short-lived access tokens, and acts as the
 * API user, with its roles.
 */
readonly class CreateServiceClient
{
    public function __construct(
        private ClientRepository $clients,
        private AuditServiceClientChange $auditChange,
        private CredentialAccess $credentials,
    ) {
    }

    /**
     * @param  non-empty-string  $name  What the client is for, e.g. "Production sync"
     * @param  list<non-empty-string>|null  $allowedIps  IP addresses or CIDR ranges the client may call from
     * @param  User|null  $createdBy  The administrator, recorded as the rotator when it replaces a client; null for a seeder
     * @return array{0: non-empty-string, 1: OAuthClient} The plaintext secret, shown once, and the client
     *
     * @throws AuthorizationException
     */
    public function __invoke(
        User $apiUser,
        string $name,
        CarbonInterface $secretExpiresAt,
        ?array $allowedIps = null,
        ?OAuthClient $rotatedFrom = null,
        ?User $createdBy = null,
    ): array {
        if ($apiUser->auth_type !== AuthType::API) {
            throw new InvalidArgumentException('Service clients can only belong to API users.');
        }

        $this->credentials->decide($createdBy, CredentialOperation::Issue, CredentialKind::ServiceClient, $apiUser)->authorize();

        if ($secretExpiresAt->isPast() || $secretExpiresAt->isAfter(now()->addYear()->addDay())) {
            throw new InvalidArgumentException('A client secret must expire within one year.');
        }

        return DB::transaction(function () use ($apiUser, $name, $secretExpiresAt, $allowedIps, $rotatedFrom, $createdBy): array {
            /** @var OAuthClient $client */
            $client = $this->clients->createClientCredentialsGrantClient($name);

            /** @var non-empty-string $secret */
            $secret = $client->plainSecret;

            $client->owner()->associate($apiUser);
            $client->forceFill([
                'origin' => ClientOrigin::Administrator,
                'secret_expires_at' => $secretExpiresAt,
                'allowed_ips' => $allowedIps === [] ? null : $allowedIps,
                'rotated_from_client_id' => $rotatedFrom?->getKey(),
                'rotated_by_user_id' => $rotatedFrom instanceof OAuthClient ? $createdBy?->getKey() : null,
            ])->save();

            ($this->auditChange)($client, AuditEvent::ServiceClientCreated);

            return [$secret, $client];
        });
    }
}
