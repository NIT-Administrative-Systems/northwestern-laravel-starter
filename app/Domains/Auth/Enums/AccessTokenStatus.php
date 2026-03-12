<?php

declare(strict_types=1);

namespace App\Domains\Auth\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum AccessTokenStatus: string implements HasColor, HasIcon, HasLabel
{
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Expired => 'gray',
            self::Revoked => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Active => Heroicon::OutlinedBolt,
            self::Expired => Heroicon::OutlinedArchiveBoxXMark,
            self::Revoked => Heroicon::OutlinedShieldExclamation,
        };
    }
}
