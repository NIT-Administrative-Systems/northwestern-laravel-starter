<?php

declare(strict_types=1);

namespace App\Domains\Auth\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Carbon;

enum TokenExpiration: int implements HasLabel
{
    case OneDay = 1;
    case OneWeek = 7;
    case OneMonth = 30;
    case TwoMonths = 60;
    case ThreeMonths = 90;
    case SixMonths = 180;
    case OneYear = 365;
    case Never = 0;

    public function getLabel(): string
    {
        return match ($this) {
            self::OneDay => '1 Day',
            self::OneWeek => '7 Days',
            self::OneMonth => '30 Days',
            self::TwoMonths => '60 Days',
            self::ThreeMonths => '90 Days',
            self::SixMonths => '180 Days',
            self::OneYear => '1 Year',
            self::Never => 'No Expiration',
        };
    }

    /**
     * Calculate the expiration date from now.
     * Returns null for NEVER.
     *
     * @return ($this is self::Never ? null : Carbon)
     */
    public function expiresAt(?Carbon $from = null): ?Carbon
    {
        if ($this === self::Never) {
            return null;
        }

        return ($from ?? Carbon::now())->addDays($this->value);
    }
}
