<?php

declare(strict_types=1);

namespace App\Domains\Auth\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum AuthTypeEnum: string implements HasColor, HasIcon, HasLabel
{
    case SSO = 'sso';
    case LOCAL = 'local';
    case API = 'api';

    public function getLabel(): string
    {
        return match ($this) {
            self::SSO => 'NetID',
            self::LOCAL => 'Verification Code',
            self::API => 'API',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::SSO => Heroicon::OutlinedShieldCheck,
            self::LOCAL => Heroicon::OutlinedKey,
            self::API => Heroicon::OutlinedCodeBracket,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SSO => 'primary',
            self::LOCAL => 'gray',
            self::API => 'info',
        };
    }
}
