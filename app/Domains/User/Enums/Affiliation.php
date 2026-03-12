<?php

declare(strict_types=1);

namespace App\Domains\User\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum Affiliation: string implements HasColor, HasIcon, HasLabel
{
    case Student = 'student';
    case Faculty = 'faculty';
    case Staff = 'staff';
    case Affiliate = 'affiliate';
    case Other = 'not-matched';

    public function getLabel(): string
    {
        return match ($this) {
            self::Other => 'Other',
            default => ucfirst($this->value),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Student => 'info',
            self::Faculty => 'primary',
            self::Staff => 'success',
            self::Affiliate => 'warning',
            self::Other => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Student => Heroicon::OutlinedAcademicCap,
            self::Faculty => Heroicon::OutlinedUserGroup,
            self::Staff => Heroicon::OutlinedBriefcase,
            self::Affiliate => Heroicon::OutlinedLink,
            self::Other => Heroicon::OutlinedQuestionMarkCircle,
        };
    }
}
