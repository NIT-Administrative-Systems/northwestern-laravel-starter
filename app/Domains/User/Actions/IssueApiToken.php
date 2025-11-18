<?php

declare(strict_types=1);

namespace App\Domains\User\Actions;

use App\Domains\User\Enums\AuthTypeEnum;
use App\Domains\User\Models\ApiToken;
use App\Domains\User\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Issues a new Bearer token for an existing API user.
 *
 * This allows rotating credentials or creating additional tokens with different
 * validity periods or IP restrictions for the same API user.
 */
readonly class IssueApiToken
{
    /**
     * Issue a new token for the given API user.
     *
     * @param  User  $user  The API user to issue a token for
     * @param  CarbonInterface|null  $validFrom  When the token becomes valid (defaults to now)
     * @param  CarbonInterface|null  $validTo  When the token expires (null for indefinite)
     * @param  array<int, string>|null  $allowedIps  Optional list of allowed IP addresses or CIDR ranges
     * @return array{0: string, 1: ApiToken} Tuple of plaintext token and the created {@see ApiToken}
     */
    public function __invoke(
        User $user,
        ?CarbonInterface $validFrom = null,
        ?CarbonInterface $validTo = null,
        ?array $allowedIps = null,
    ): array {
        if ($user->auth_type !== AuthTypeEnum::API) {
            throw new InvalidArgumentException('Tokens can only be issued for API users.');
        }

        $rawToken = Str::random(length: 64);

        $apiToken = $user->api_tokens()->create([
            'token_prefix' => mb_substr($rawToken, 0, 5),
            'token_hash' => ApiToken::hashFromPlain($rawToken),
            'valid_from' => $validFrom ?? Carbon::now(),
            'valid_to' => $validTo,
            'allowed_ips' => $allowedIps,
        ]);

        return [$rawToken, $apiToken];
    }
}
