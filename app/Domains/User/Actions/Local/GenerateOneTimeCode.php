<?php

declare(strict_types=1);

namespace App\Domains\User\Actions\Local;

/**
 * Generates a numeric, fixed-length one-time verification code for local sign-in challenges.
 *
 * @see IssueLoginChallenge
 */
final class GenerateOneTimeCode
{
    public function __invoke(int $digits): string
    {
        $min = 10 ** ($digits - 1);
        $max = (10 ** $digits) - 1;

        return (string) random_int($min, $max);
    }
}
