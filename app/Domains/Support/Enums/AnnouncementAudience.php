<?php

declare(strict_types=1);

namespace App\Domains\Support\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Who sees an announcement. Super Administrators see every announcement, whatever its audience.
 */
enum AnnouncementAudience: string implements HasDescription, HasLabel
{
    case Everyone = 'everyone';
    case Targeted = 'targeted';
    case Public = 'public';

    public function getLabel(): string
    {
        return match ($this) {
            self::Everyone => 'Everyone signed in',
            self::Targeted => 'Specific roles and affiliations',
            self::Public => 'Everyone, including signed-out visitors',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Everyone => 'Everyone who can use the application.',
            self::Targeted => 'People who hold any of the roles or have any of the affiliations you choose.',
            self::Public => 'Also shown on public pages and the sign-in page, so write it for anyone.',
        };
    }
}
