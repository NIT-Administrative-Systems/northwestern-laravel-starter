<?php

declare(strict_types=1);

namespace App\Filament\Clusters;

use App\Filament\Clusters\ApiCluster\Pages\Overview;
use App\Filament\Clusters\ApiCluster\Resources\ApiRequestLogs\ApiRequestLogResource;
use App\Filament\Clusters\ApiCluster\Resources\McpClients\McpClientResource;
use App\Filament\Clusters\ApiCluster\Resources\OAuthApplications\OAuthApplicationResource;
use App\Filament\Navigation\AdministrationNavGroup;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ApiCluster extends Cluster
{
    protected static ?string $navigationLabel = 'API';

    protected static ?string $title = 'API';

    protected static ?string $slug = 'api';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|null|UnitEnum $navigationGroup = AdministrationNavGroup::Platform;

    protected static ?int $navigationSort = 2;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    /**
     * Open when any of its pages is. Filament applies this only to navigation, so each page
     * checks its own access too.
     */
    public static function canAccess(): bool
    {
        return Overview::canAccess()
            || OAuthApplicationResource::canAccess()
            || McpClientResource::canAccess()
            || ApiRequestLogResource::canAccess();
    }
}
