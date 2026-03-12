<?php

declare(strict_types=1);

namespace App\Domains\Auth\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum AuthType: string implements HasColor, HasIcon, HasLabel
{
    case Sso = 'sso';
    case Local = 'local';
    case Api = 'api';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sso => 'NetID',
            self::Local => 'Verification Code',
            self::Api => 'API',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Sso => Heroicon::OutlinedShieldCheck,
            self::Local => Heroicon::OutlinedKey,
            self::Api => Heroicon::OutlinedCodeBracket,
        };
    }

    /** @return 'primary'|'gray'|'info' */
    public function getColor(): string
    {
        return match ($this) {
            self::Sso => 'primary',
            self::Local => 'gray',
            self::Api => 'info',
        };
    }
}
