<?php

declare(strict_types=1);

namespace App\Filament\Navigation;

use Filament\Support\Contracts\HasLabel;

enum AdministrationNavGroup implements HasLabel
{
    case USER_MANAGEMENT;

    case PLATFORM;

    case DEBUG;

    public function getLabel(): string
    {
        return match ($this) {
            self::USER_MANAGEMENT => 'User Management',
            self::PLATFORM => 'Platform',
            self::DEBUG => 'Debug',
        };
    }
}
