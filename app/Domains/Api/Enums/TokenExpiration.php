<?php

declare(strict_types=1);

namespace App\Domains\Api\Enums;

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
        };
    }

    /**
     * The lifetimes a personal access token may choose, up to `$maxDays`.
     *
     * @return list<self>
     */
    public static function forPersonalAccessTokens(int $maxDays): array
    {
        return array_values(array_filter(
            [self::OneMonth, self::ThreeMonths, self::SixMonths, self::OneYear],
            fn (self $lifetime): bool => $lifetime->value <= $maxDays,
        ));
    }

    /**
     * The expiration date, counted from now or from `$from`.
     */
    public function expiresAt(?Carbon $from = null): Carbon
    {
        return ($from ?? Carbon::now())->addDays($this->value);
    }
}
