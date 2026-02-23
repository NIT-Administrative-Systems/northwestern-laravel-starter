<?php

declare(strict_types=1);

namespace App\Domains\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidIpOrCidrRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail('The :attribute must be a valid IP address or CIDR range.');
        }
    }

    /** @phpstan-pure */
    public static function isValid(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return true;
        }

        if (! str_contains($value, '/')) {
            return false;
        }

        [$ip, $mask] = explode('/', $value, 2);

        return ctype_digit($mask) && match (true) {
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false => $mask <= 32,
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false => $mask <= 128,
            default => false,
        };
    }
}
