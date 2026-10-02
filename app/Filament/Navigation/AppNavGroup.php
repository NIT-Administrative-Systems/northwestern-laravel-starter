<?php

declare(strict_types=1);

namespace App\Filament\Navigation;

use Filament\Support\Contracts\HasLabel;

enum AppNavGroup implements HasLabel
{
    case Help;

    public function getLabel(): string
    {
        return match ($this) {
            self::Help => 'Help',
        };
    }
}
