<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Local;

use App\Domains\Auth\Contracts\OneTimeCodeGenerator;
use InvalidArgumentException;

final class FixedNumericOneTimeCodeGenerator implements OneTimeCodeGenerator
{
    private const string SEED = '1234567890';

    public function __invoke(int $digits): string
    {
        if ($digits < 1) {
            throw new InvalidArgumentException('Digits must be >= 1.');
        }

        $repeat = (int) ceil($digits / strlen(self::SEED));

        return substr(str_repeat(self::SEED, $repeat), 0, $digits);
    }
}
