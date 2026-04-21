<?php

declare(strict_types=1);

namespace App\Domains\User\Support;

use App\Domains\User\Models\User;

/**
 * Builds consistent select-option labels for users across Filament resources.
 */
class UserOptionLabel
{
    public const string FormatFullName = 'full_name';

    public const string FormatClericalName = 'clerical_name';

    public const string FormatUsername = 'username';

    /**
     * Format a user for select options or search result labels.
     */
    public function for(User $user, string $format = self::FormatFullName, bool $withUsername = true): string
    {
        $label = match ($format) {
            self::FormatClericalName => $user->clerical_name,
            self::FormatUsername => $user->username,
            default => $user->full_name,
        };

        if ($format === self::FormatUsername || ! $withUsername) {
            return $label;
        }

        return sprintf('%s (%s)', $label, $user->username);
    }

    /**
     * Format a nullable user, returning null when no record is available.
     */
    public function forNullable(?User $user, string $format = self::FormatFullName, bool $withUsername = true): ?string
    {
        if (! $user instanceof User) {
            return null;
        }

        return $this->for($user, $format, $withUsername);
    }
}
