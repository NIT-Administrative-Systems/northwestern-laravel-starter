<?php

declare(strict_types=1);

namespace App\Domains\Auth\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum AccessTokenStatusEnum: string implements HasColor, HasIcon, HasLabel
{
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case REVOKED = 'revoked';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::EXPIRED => 'gray',
            self::REVOKED => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::ACTIVE => Heroicon::OutlinedBolt,
            self::EXPIRED => Heroicon::OutlinedArchiveBoxXMark,
            self::REVOKED => Heroicon::OutlinedShieldExclamation,
        };
    }
}
