<?php

declare(strict_types=1);

namespace App\Filament\Clusters;

use App\Domains\Auth\Enums\PermissionEnum;
use App\Filament\Navigation\AdministrationNavGroup;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ApiCluster extends Cluster
{
    protected static ?string $navigationLabel = 'API';

    protected static ?string $title = 'API';

    protected static ?string $slug = 'api';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|null|UnitEnum $navigationGroup = AdministrationNavGroup::PLATFORM;

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        if (! config('auth.api.enabled')) {
            return false;
        }

        return auth()->user()?->hasPermissionTo(PermissionEnum::MANAGE_ALL) ?? false;
    }
}
