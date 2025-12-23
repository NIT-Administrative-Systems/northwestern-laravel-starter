<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Local;

use App\Domains\Auth\Models\LoginChallenge;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Verifies a provided OTP code against a {@see LoginChallenge}.
 *
 * This checks if the challenge is active, validates the code hash,
 * handles attempt increments/locking on failure, and marks the
 * challenge as consumed upon a successful match.
 */
final class VerifyLoginChallengeCode
{
    public function __invoke(LoginChallenge $challenge, string $code, ?string $ip, ?string $userAgent): bool
    {
        $now = new CarbonImmutable();

        if (! $challenge->isActive($now)) {
            return false;
        }

        if (! Hash::check($code, $challenge->code_hash)) {
            $challenge->increment('attempts');

            $maxAttempts = (int) config('auth.local.code.max_attempts', 8);
            if ($challenge->attempts >= $maxAttempts) {
                $lockMinutes = (int) config('auth.local.code.lock_minutes', 15);
                $challenge->update(['locked_until' => $now->addMinutes($lockMinutes)]);
            }

            return false;
        }

        $challenge->update([
            'consumed_at' => $now,
            'consumed_ip' => $ip,
            'consumed_user_agent' => $userAgent ? Str::limit($userAgent, 512, '') : null,
        ]);

        return true;
    }
}
