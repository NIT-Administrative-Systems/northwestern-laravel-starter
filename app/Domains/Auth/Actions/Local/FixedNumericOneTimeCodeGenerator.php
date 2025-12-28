<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Local;

use App\Domains\Auth\Contracts\OneTimeCodeGenerator;
use Illuminate\Support\Facades\App;
use InvalidArgumentException;
use LogicException;

/**
 * Generates a deterministic, fixed-pattern numeric code.
 *
 * This generator is intended for use in CI environments and testing suites,
 * where predictable output is needed for assertions and login challenges.
 * It repeats the sequence '1234567890' to fill the required length.
 */
final class FixedNumericOneTimeCodeGenerator implements OneTimeCodeGenerator
{
    private const string SEED = '1234567890';

    /**
     * Generate a predictable code based on the SEED pattern.
     *
     * @param  int  $digits  The desired length of the code.
     * @return string A string starting with "123..." truncated to the requested length.
     */
    public function __invoke(int $digits): string
    {
        if (App::isProduction()) {
            throw new LogicException(
                'The FixedNumericOneTimeCodeGenerator must not be used in production environments.'
            );
        }

        if ($digits < 1) {
            throw new InvalidArgumentException('Digits must be >= 1.');
        }

        $repeat = (int) ceil($digits / strlen(self::SEED));

        return substr(str_repeat(self::SEED, $repeat), 0, $digits);
    }
}
