<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Users\Pages;

use App\Filament\Administration\Resources\Users\RelationManagers\ApiRequestLogsRelationManager;
use App\Filament\Administration\Resources\Users\RelationManagers\ConnectedApplicationsRelationManager;
use App\Filament\Administration\Resources\Users\RelationManagers\PersonalAccessTokensRelationManager;
use App\Filament\Administration\Resources\Users\RelationManagers\ServiceClientsRelationManager;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * How a person, or an API user's integration, calls the application: their tokens, connected
 * applications, service clients, and the requests those made.
 */
class ManageUserApiCredentials extends UserRelationsPage
{
    protected static string $relationship = 'tokens';

    protected static ?string $navigationLabel = 'API Credentials';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    public static function managers(): array
    {
        return [
            ServiceClientsRelationManager::class,
            PersonalAccessTokensRelationManager::class,
            ConnectedApplicationsRelationManager::class,
            ApiRequestLogsRelationManager::class,
        ];
    }
}
