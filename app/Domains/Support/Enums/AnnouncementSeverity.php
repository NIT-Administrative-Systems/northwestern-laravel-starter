<?php

declare(strict_types=1);

namespace App\Domains\Support\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * How much an announcement matters. It sets the banner's colour and icon, which announcement
 * the banner shows first, and whether people can dismiss it.
 */
enum AnnouncementSeverity: string implements HasColor, HasIcon, HasLabel
{
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Critical = 'critical';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Info => 'info',
            self::Success => 'success',
            self::Warning => 'warning',
            self::Critical => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Info => Heroicon::OutlinedInformationCircle,
            self::Success => Heroicon::OutlinedCheckCircle,
            self::Warning => Heroicon::OutlinedExclamationTriangle,
            self::Critical => Heroicon::OutlinedExclamationCircle,
        };
    }

    /**
     * Higher shows first in the banner.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Info => 1,
            self::Success => 2,
            self::Warning => 3,
            self::Critical => 4,
        };
    }

    /**
     * A critical announcement stays in the banner until it ends.
     */
    public function isDismissible(): bool
    {
        return $this !== self::Critical;
    }
}
