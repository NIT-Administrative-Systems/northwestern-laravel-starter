<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Users\Pages;

use App\Filament\Administration\Resources\Users\RelationManagers\AuditsRelationManager;
use App\Filament\Administration\Resources\Users\RelationManagers\LoginRecordsRelationManager;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * What a person changed, and when they signed in.
 */
class ManageUserActivity extends UserRelationsPage
{
    protected static string $relationship = 'audits';

    protected static ?string $navigationLabel = 'Activity';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static function managers(): array
    {
        return [
            AuditsRelationManager::class,
            LoginRecordsRelationManager::class,
        ];
    }
}
