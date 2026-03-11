<?php

declare(strict_types=1);

namespace App\Domains\Auth\Contracts;

/**
 * Contract for generating one-time verification codes.
 */
interface OneTimeCodeGenerator
{
    /**
     * Generate a one-time verification code of a specific length.
     *
     * @param  positive-int  $digits  The length of the string to generate.
     * @return non-empty-string The generated one-time code.
     */
    public function __invoke(int $digits): string;
}
