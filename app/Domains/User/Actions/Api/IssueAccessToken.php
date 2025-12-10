<?php

declare(strict_types=1);

namespace App\Domains\User\Actions\Api;

use App\Domains\User\Enums\AuthTypeEnum;
use App\Domains\User\Models\AccessToken;
use App\Domains\User\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Issues a new Bearer token for an existing API user.
 *
 * This allows rotating credentials or creating additional tokens with different
 * validity periods or IP restrictions for the same API user.
 */
readonly class IssueAccessToken
{
    /**
     * Issue a new token for the given API user.
     *
     * @param  User  $user  The API user to issue a token for
     * @param  string  $name  Descriptive name for the token (e.g., "Production Server")
     * @param  CarbonInterface|null  $expiresAt  When the token expires (null for no expiration)
     * @param  array<int, string>|null  $allowedIps  Optional list of allowed IP addresses or CIDR ranges
     * @return array{0: string, 1: AccessToken} Tuple of plaintext token and the created {@see AccessToken}
     */
    public function __invoke(
        User $user,
        string $name,
        ?CarbonInterface $expiresAt = null,
        ?array $allowedIps = null,
    ): array {
        if ($user->auth_type !== AuthTypeEnum::API) {
            throw new InvalidArgumentException('Tokens can only be issued for API users.');
        }

        $rawToken = Str::random(length: 64);

        $accessToken = $user->access_tokens()->create([
            'name' => $name,
            'token_prefix' => mb_substr($rawToken, 0, 5),
            'token_hash' => AccessToken::hashFromPlain($rawToken),
            'expires_at' => $expiresAt,
            'allowed_ips' => $allowedIps,
        ]);

        return [$rawToken, $accessToken];
    }
}
