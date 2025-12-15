<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreAccessTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'integer', 'min:' . now()->addDay()->timestamp],
            'allowed_ips' => ['nullable', 'array'],
            'allowed_ips.*' => [
                'string',
                fn (string $attribute, mixed $value, Closure $fail) => $this->isValidIpOrCidr($value) || $fail("The {$attribute} must be a valid IP address or CIDR range."),
            ],
        ];
    }

    private function isValidIpOrCidr(string $value): bool
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
