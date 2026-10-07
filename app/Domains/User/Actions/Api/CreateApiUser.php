<?php

declare(strict_types=1);

namespace App\Domains\User\Actions\Api;

use App\Domains\Api\Actions\ServiceClients\CreateServiceClient;
use App\Domains\Api\Models\OAuthClient;
use App\Domains\Auth\Enums\AuthType;
use App\Domains\User\Enums\Affiliation;
use App\Domains\User\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates an API user, a service account for an integration, with its first service client.
 *
 * API users never sign in. Their clients exchange a client ID and secret at `/oauth/token`
 * for access tokens that act as the API user, with its roles.
 */
readonly class CreateApiUser
{
    public function __construct(
        private CreateServiceClient $createServiceClient,
    ) {
    }

    /**
     * @param  non-empty-string  $username  The username for the API user (should be prefixed with 'api-')
     * @param  non-empty-string  $firstName  The display label for this API user (will be suffixed with 'API')
     * @param  non-empty-string  $clientName  What the first service client is for
     * @param  string|null  $description  Optional description of the API user's purpose
     * @param  string|null  $email  Optional contact email for secret expiration notifications
     * @param  list<non-empty-string>|null  $allowedIps  Optional IP addresses or CIDR ranges the client may call from
     * @param  User|null  $createdBy  The administrator; null for a seeder
     * @return array{0: User, 1: non-empty-string, 2: OAuthClient} The user, the client's plaintext secret, and the client
     */
    public function __invoke(
        string $username,
        string $firstName,
        string $clientName,
        CarbonInterface $secretExpiresAt,
        ?string $description = null,
        ?string $email = null,
        ?array $allowedIps = null,
        ?User $createdBy = null,
    ): array {
        return DB::transaction(function () use ($username, $firstName, $clientName, $secretExpiresAt, $description, $email, $allowedIps, $createdBy): array {
            $user = User::create([
                'username' => strtolower($username),
                'primary_affiliation' => Affiliation::Other,
                'auth_type' => AuthType::API,
                'email' => filled($email) ? strtolower($email) : null,
                'first_name' => $firstName,
                'last_name' => 'API',
                'description' => $description,
            ]);

            // Refused here, after the user exists, the transaction undoes the user too.
            [$secret, $client] = ($this->createServiceClient)($user, $clientName, $secretExpiresAt, $allowedIps, createdBy: $createdBy);

            return [$user, $secret, $client];
        });
    }
}
