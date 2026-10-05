<?php

declare(strict_types=1);

namespace App\Domains\Auth\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An OAuth application's redirect URI: an absolute HTTPS URL, or plain HTTP to the loopback
 * address for applications running on the user's own computer. No fragment, as OAuth 2.0
 * requires.
 */
class OAuthRedirectUri implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parts = is_string($value) ? parse_url($value) : false;

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || isset($parts['fragment'])) {
            $fail('Each redirect URI must be an absolute URL without a fragment.');

            return;
        }

        $loopback = in_array(strtolower($parts['host']), ['localhost', '127.0.0.1', '[::1]'], true);

        if ($parts['scheme'] !== 'https' && ($parts['scheme'] !== 'http' || ! $loopback)) {
            $fail('Each redirect URI must use HTTPS, or HTTP to localhost.');
        }
    }
}
