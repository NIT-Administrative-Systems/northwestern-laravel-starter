<?php

declare(strict_types=1);

namespace App\Domains\Auth\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum AuthType: string implements HasColor, HasIcon, HasLabel
{
    case SSO = 'sso';
    case Local = 'local';
    case API = 'api';

    public function getLabel(): string
    {
        return match ($this) {
            self::SSO => 'NetID',
            self::Local => 'Verification Code',
            self::API => 'API',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::SSO => Heroicon::OutlinedShieldCheck,
            self::Local => Heroicon::OutlinedKey,
            self::API => Heroicon::OutlinedCodeBracket,
        };
    }

    /** @return 'primary'|'gray'|'info' */
    public function getColor(): string
    {
        return match ($this) {
            self::SSO => 'primary',
            self::Local => 'gray',
            self::API => 'info',
        };
    }
}
