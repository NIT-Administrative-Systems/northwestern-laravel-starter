<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Api;

use App\Domains\Auth\Enums\AuthType;
use App\Domains\Auth\Enums\ClientOrigin;
use App\Domains\Auth\Models\OAuthClient;
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
    ) {
    }

    /**
     * @param  non-empty-string  $name  What the client is for, e.g. "Production sync"
     * @param  list<non-empty-string>|null  $allowedIps  IP addresses or CIDR ranges the client may call from
     * @return array{0: non-empty-string, 1: OAuthClient} The plaintext secret, shown once, and the client
     */
    public function __invoke(
        User $apiUser,
        string $name,
        CarbonInterface $secretExpiresAt,
        ?array $allowedIps = null,
        ?OAuthClient $rotatedFrom = null,
        ?User $rotatedBy = null,
    ): array {
        if ($apiUser->auth_type !== AuthType::API) {
            throw new InvalidArgumentException('Service clients can only belong to API users.');
        }

        // A secret outlives the session, so it is never issued while impersonating, as with personal access tokens. This covers rotation and new API users too.
        if (resolve('impersonate')->isImpersonating()) {
            throw new AuthorizationException('Service clients cannot be created or rotated while impersonating.');
        }

        if ($secretExpiresAt->isPast() || $secretExpiresAt->isAfter(now()->addYear()->addDay())) {
            throw new InvalidArgumentException('A client secret must expire within one year.');
        }

        return DB::transaction(function () use ($apiUser, $name, $secretExpiresAt, $allowedIps, $rotatedFrom, $rotatedBy): array {
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
                'rotated_by_user_id' => $rotatedBy?->getKey(),
            ])->save();

            ($this->auditChange)($client, 'service_client_created');

            return [$secret, $client];
        });
    }
}
