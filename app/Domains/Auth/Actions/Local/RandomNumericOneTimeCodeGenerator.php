<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Local;

use App\Domains\Auth\Contracts\OneTimeCodeGenerator;
use InvalidArgumentException;

/**
 * Generates a random, numeric, fixed-length one-time verification code for local sign-in challenges.
 *
 * @see IssueLoginChallenge
 */
final class RandomNumericOneTimeCodeGenerator implements OneTimeCodeGenerator
{
    public function __invoke(int $digits): string
    {
        if ($digits < 1) {
            throw new InvalidArgumentException('Digits must be >= 1.');
        }

        $min = 10 ** ($digits - 1);
        $max = (10 ** $digits) - 1;

        return (string) random_int($min, $max);
    }
}
