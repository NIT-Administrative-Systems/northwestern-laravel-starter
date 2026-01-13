<?php

declare(strict_types=1);

namespace App\Domains\Core\Services\ConfigValidation;

use App\Domains\Core\Contracts\ConfigValidator;

/**
 * Validates that the application key is set and properly formatted.
 */
class AppKeyValidator implements ConfigValidator
{
    protected ?string $errorReason = null;

    public function name(): string
    {
        return 'Application Key';
    }

    public function validate(): bool
    {
        $key = config('app.key');

        if (blank($key)) {
            $this->errorReason = 'missing';

            return false;
        }

        if (! str_starts_with((string) $key, 'base64:')) {
            $this->errorReason = 'invalid_format';

            return false;
        }

        $decoded = base64_decode(substr((string) $key, 7), strict: true);

        if ($decoded === false || strlen($decoded) < 32) {
            $this->errorReason = 'invalid_key';

            return false;
        }

        return true;
    }

    public function successMessage(): string
    {
        return 'Application key is configured and valid';
    }

    public function errorMessage(): string
    {
        return match ($this->errorReason) {
            'missing' => 'Application key is not set',
            'invalid_format' => 'Application key has invalid format',
            'invalid_key' => 'Application key is malformed or too short',
            default => 'Application key validation failed',
        };
    }

    public function hints(): array
    {
        return match ($this->errorReason) {
            'missing' => [
                'Run <comment>php artisan key:generate</comment> to create a new key',
                'Or set <comment>APP_KEY</comment> in your .env file',
            ],
            'invalid_format' => [
                'The key should start with <comment>base64:</comment>',
                'Run <comment>php artisan key:generate</comment> to create a valid key',
            ],
            'invalid_key' => [
                'The key appears to be corrupted or truncated',
                'Run <comment>php artisan key:generate</comment> to create a new key',
            ],
            default => [],
        };
    }
}
