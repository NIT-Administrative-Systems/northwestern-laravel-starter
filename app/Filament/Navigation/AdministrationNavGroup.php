<?php

declare(strict_types=1);

namespace App\Filament\Navigation;

use Filament\Support\Contracts\HasLabel;

enum AdministrationNavGroup implements HasLabel
{
    case UserManagement;

    case Platform;

    case DeveloperTools;

    public function getLabel(): string
    {
        return match ($this) {
            self::UserManagement => 'User Management',
            self::Platform => 'Platform',
            self::DeveloperTools => 'Developer Tools',
        };
    }
}
