<?php

declare(strict_types=1);

namespace App\Domains\User\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum AffiliationEnum: string implements HasColor, HasIcon, HasLabel
{
    case STUDENT = 'student';
    case FACULTY = 'faculty';
    case STAFF = 'staff';
    case AFFILIATE = 'affiliate';
    case OTHER = 'not-matched';

    public function getLabel(): string
    {
        return match ($this) {
            self::OTHER => 'Other',
            default => ucfirst($this->value),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::STUDENT => 'info',
            self::FACULTY => 'primary',
            self::STAFF => 'success',
            self::AFFILIATE => 'warning',
            self::OTHER => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::STUDENT => Heroicon::OutlinedAcademicCap,
            self::FACULTY => Heroicon::OutlinedUserGroup,
            self::STAFF => Heroicon::OutlinedBriefcase,
            self::AFFILIATE => Heroicon::OutlinedLink,
            self::OTHER => Heroicon::OutlinedQuestionMarkCircle,
        };
    }
}
