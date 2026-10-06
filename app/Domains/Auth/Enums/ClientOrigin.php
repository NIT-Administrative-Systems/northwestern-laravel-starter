<?php

declare(strict_types=1);

namespace App\Domains\Auth\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How an OAuth client came to exist. Cleanup rules depend on it: administrator-registered
 * clients are never removed automatically, dynamically registered ones can be.
 */
enum ClientOrigin: string implements HasLabel
{
    /** Created by the application: a service client or an OAuth application in Administration, or Passport's personal access client. */
    case Administrator = 'administrator';

    /** Registered by the client itself, such as an MCP client using dynamic registration. */
    case Dynamic = 'dynamic';

    public function getLabel(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::Dynamic => 'Self-Registered',
        };
    }
}
