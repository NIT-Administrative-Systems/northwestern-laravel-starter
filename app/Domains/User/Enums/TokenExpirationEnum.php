<?php

declare(strict_types=1);

namespace App\Domains\User\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Carbon;

enum TokenExpirationEnum: int implements HasLabel
{
    case ONE_DAY = 1;
    case ONE_WEEK = 7;
    case ONE_MONTH = 30;
    case TWO_MONTHS = 60;
    case THREE_MONTHS = 90;
    case SIX_MONTHS = 180;
    case ONE_YEAR = 365;
    case NEVER = 0;

    public function getLabel(): string
    {
        return match ($this) {
            self::ONE_DAY => '1 Day',
            self::ONE_WEEK => '7 Days',
            self::ONE_MONTH => '30 Days',
            self::TWO_MONTHS => '60 Days',
            self::THREE_MONTHS => '90 Days',
            self::SIX_MONTHS => '180 Days',
            self::ONE_YEAR => '1 Year',
            self::NEVER => 'No Expiration',
        };
    }

    /**
     * Calculate the expiration date from now.
     * Returns null for NEVER.
     */
    public function expiresAt(?Carbon $from = null): ?Carbon
    {
        if ($this === self::NEVER) {
            return null;
        }

        return ($from ?? Carbon::now())->addDays($this->value);
    }
}
