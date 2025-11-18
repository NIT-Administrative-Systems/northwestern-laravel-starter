<?php

declare(strict_types=1);

namespace App\Domains\User\Enums;

use App\Domains\User\Models\User;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * Represents the segment of a {@see User} at the time of login.
 */
enum UserSegmentEnum: string implements HasColor, HasIcon, HasLabel
{
    case SUPER_ADMIN = 'super-admin';
    case EXTERNAL_USER = 'external-user';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            // Auto-converts the string to a title. You can override one by adding a specific case.
            default => Str::of($this->value)->replace('-', ' ')->title()->toString(),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'danger',
            self::EXTERNAL_USER => 'warning',
            self::OTHER => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::SUPER_ADMIN => Heroicon::OutlinedShieldCheck,
            self::EXTERNAL_USER => Heroicon::OutlinedBuildingOffice2,
            self::OTHER => Heroicon::OutlinedUserCircle,
        };
    }
}
