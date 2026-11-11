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
 * For local development, CI and the tests, where a predictable code lets
 * a script sign in. It repeats '1234567890' to the length asked for.
 *
 * Only {@see self::ENVIRONMENTS} may use it: the application checks when it boots
 * and for each code, so that no deployed environment issues a guessable code.
 */
final class FixedNumericOneTimeCodeGenerator implements OneTimeCodeGenerator
{
    /** The environments allowed to use a fixed code. */
    public const array ENVIRONMENTS = ['local', 'testing', 'ci'];

    private const string SEED = '1234567890';

    /**
     * @throws LogicException Outside {@see self::ENVIRONMENTS}
     */
    public static function ensureAllowed(): void
    {
        if (! App::environment(self::ENVIRONMENTS)) {
            throw new LogicException(sprintf(
                'The fixed sign-in code can only be used in the local, testing and ci environments, not [%s]. Turn off LOCAL_AUTH_USE_FIXED_CODE.',
                App::environment(),
            ));
        }
    }

    /**
     * Generate a predictable code based on the SEED pattern.
     *
     * @param  positive-int  $digits  The desired length of the code.
     * @return non-empty-string A string starting with "123..." truncated to the requested length.
     */
    public function __invoke(int $digits): string
    {
        self::ensureAllowed();

        if ($digits < 1) {
            throw new InvalidArgumentException('Digits must be >= 1.');
        }

        $repeat = (int) ceil($digits / strlen(self::SEED));

        return substr(str_repeat(self::SEED, $repeat), 0, $digits);
    }
}
