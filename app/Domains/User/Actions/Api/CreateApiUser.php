<?php

declare(strict_types=1);

namespace App\Domains\User\Actions\Api;

use App\Domains\Auth\Enums\AuthTypeEnum;
use App\Domains\Auth\Models\AccessToken;
use App\Domains\User\Enums\AffiliationEnum;
use App\Domains\User\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a new API user account with an initial Bearer token.
 *
 * API users are service accounts used for programmatic access to the application.
 * They authenticate using Bearer tokens instead of SSO or verification codes.
 */
readonly class CreateApiUser
{
    /**
     * Create a new API user with an initial token.
     *
     * @param  string  $username  The username for the API user (should be prefixed with 'api-')
     * @param  string  $firstName  The display label for this API user (will be suffixed with 'API')
     * @param  string  $tokenName  Descriptive name for the initial access token
     * @param  string|null  $description  Optional description of the API user's purpose
     * @param  string|null  $email  Optional contact email for expiration notifications
     * @param  CarbonInterface|null  $expiresAt  When the token expires (null for no expiration)
     * @param  array<int, string>|null  $allowedIps  Optional list of allowed IP addresses or CIDR ranges
     * @return array{0: User, 1: string} The created user and the raw Bearer token
     */
    public function __invoke(
        string $username,
        string $firstName,
        string $tokenName,
        ?string $description = null,
        ?string $email = null,
        ?CarbonInterface $expiresAt = null,
        ?array $allowedIps = null,
    ): array {
        $rawToken = Str::random(length: 64);

        $user = DB::transaction(static function () use (
            $username,
            $firstName,
            $tokenName,
            $description,
            $email,
            $expiresAt,
            $allowedIps,
            $rawToken
        ) {
            $user = User::create([
                'username' => strtolower($username),
                'primary_affiliation' => AffiliationEnum::OTHER,
                'auth_type' => AuthTypeEnum::API,
                'email' => filled($email) ? strtolower($email) : null,
                'first_name' => $firstName,
                'last_name' => 'API',
                'description' => $description,
            ]);

            $user->access_tokens()->create([
                'name' => $tokenName,
                'token_prefix' => mb_substr($rawToken, 0, 5),
                'token_hash' => AccessToken::hashFromPlain($rawToken),
                'expires_at' => $expiresAt,
                'allowed_ips' => $allowedIps,
            ]);

            return $user->fresh('access_tokens');
        });

        return [$user, $rawToken];
    }
}
