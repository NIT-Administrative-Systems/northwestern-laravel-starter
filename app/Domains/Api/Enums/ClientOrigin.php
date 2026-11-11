<?php

declare(strict_types=1);

namespace App\Domains\Api\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How an OAuth client came to exist. Cleanup depends on it: an administrator's
 * clients are never removed automatically, but self-registered ones can be.
 */
enum ClientOrigin: string implements HasLabel
{
    /**
     * Created by the application: a service client or an OAuth application
     * an administrator registered, or Passport's personal access client.
     */
    case Administrator = 'administrator';

    /** Registered by the client itself, as MCP clients do through dynamic registration. */
    case Dynamic = 'dynamic';

    public function getLabel(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::Dynamic => 'Self-Registered',
        };
    }
}
