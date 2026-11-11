<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Users\Pages;

use App\Filament\Administration\Resources\Users\RelationManagers\RoleActivityRelationManager;
use App\Filament\Administration\Resources\Users\RelationManagers\RolesRelationManager;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * A person's roles, and the history of who gave and removed them.
 */
class ManageUserAccess extends UserRelationsPage
{
    protected static string $relationship = 'roles';

    protected static ?string $navigationLabel = 'Access';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static function managers(): array
    {
        return [
            RolesRelationManager::class,
            RoleActivityRelationManager::class,
        ];
    }
}
