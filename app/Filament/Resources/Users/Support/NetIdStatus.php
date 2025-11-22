<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Support;

use App\Domains\User\Models\User;
use Filament\Support\Icons\Heroicon;

/**
 * Utility class for presenting the {@see User::$netid_inactive} value.
 */
readonly class NetIdStatus
{
    public static function getState(User $record): string
    {
        return match ($record->netid_inactive) {
            true => 'inactive',
            false => 'active',
            default => 'unknown',
        };
    }

    public static function getLabel(User $record): string
    {
        return match ($record->netid_inactive) {
            true => 'Inactive',
            false => 'Active',
            default => 'Unknown',
        };
    }

    public static function getIcon(User $record): Heroicon
    {
        return match ($record->netid_inactive) {
            true => Heroicon::OutlinedXCircle,
            false => Heroicon::OutlinedCheckCircle,
            default => Heroicon::OutlinedQuestionMarkCircle,
        };
    }

    public static function getColor(User $record): string
    {
        return match ($record->netid_inactive) {
            true => 'danger',
            false => 'success',
            default => 'warning',
        };
    }
}
