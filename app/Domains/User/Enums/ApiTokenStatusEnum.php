<?php

declare(strict_types=1);

namespace App\Domains\User\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ApiTokenStatusEnum: string implements HasColor, HasIcon, HasLabel
{
    case PENDING = 'pending';
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
            self::PENDING => 'warning',
            self::ACTIVE => 'success',
            self::EXPIRED => 'gray',
            self::REVOKED => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::PENDING => Heroicon::OutlinedClock,
            self::ACTIVE => Heroicon::OutlinedBolt,
            self::EXPIRED => Heroicon::OutlinedArchiveBoxXMark,
            self::REVOKED => Heroicon::OutlinedShieldExclamation,
        };
    }
}
