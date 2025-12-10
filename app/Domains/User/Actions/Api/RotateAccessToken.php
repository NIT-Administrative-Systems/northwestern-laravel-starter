<?php

declare(strict_types=1);

namespace App\Domains\User\Actions\Api;

use App\Domains\User\Models\AccessToken;
use App\Domains\User\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Rotates an existing Access Token by issuing a new credential and expiring the prior one.
 */
readonly class RotateAccessToken
{
    public function __construct(
        private IssueAccessToken $issueAccessToken,
    ) {
    }

    /**
     * Rotate the provided Access Token.
     *
     * @param  AccessToken  $previousAccessToken  The token being rotated.
     * @param  User|null  $rotatedBy  The user performing the rotation. Defaults to the current authenticated user.
     * @param  CarbonInterface|null  $validFrom  When the replacement token becomes valid.
     * @param  CarbonInterface|null  $validTo  When the replacement token expires (null = indefinite).
     * @param  array<int, string>|null  $allowedIps  Optional IP restrictions for the replacement token.
     * @return string The raw Bearer token string that must be shown to the operator.
     */
    public function __invoke(
        AccessToken $previousAccessToken,
        ?User $rotatedBy = null,
        ?CarbonInterface $validFrom = null,
        ?CarbonInterface $validTo = null,
        ?array $allowedIps = null,
    ): string {
        $rotatedBy ??= Auth::user();

        return DB::transaction(function () use ($previousAccessToken, $validFrom, $validTo, $allowedIps, $rotatedBy) {
            [$rawToken, $newAccessToken] = ($this->issueAccessToken)(
                user: $previousAccessToken->user,
                validFrom: $validFrom ?: Carbon::now(),
                validTo: $validTo ?? null,
                allowedIps: $allowedIps ?? null,
            );

            $newAccessToken->update([
                'rotated_from_token_id' => $previousAccessToken->getKey(),
                'rotated_by_user_id' => $rotatedBy->getKey(),
            ]);

            $previousAccessToken->update([
                'revoked_at' => Carbon::now(),
            ]);

            return $rawToken;
        });
    }
}
