<?php

declare(strict_types=1);

namespace App\Filament\Clusters;

use App\Domains\Auth\Enums\SystemPermission;
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
     * Open when the REST API is on, or when only MCP is on, for its MCP Clients page. Each
     * clustered page checks its own feature too: Filament applies this only to navigation.
     */
    public static function canAccess(): bool
    {
        return self::canAccessApi() || ((bool) config('mcp.enabled') && (bool) auth()->user()?->can(SystemPermission::ManageApiAccess));
    }

    /**
     * The REST API is on, and the person manages it or reads its request logs.
     */
    public static function canAccessApi(): bool
    {
        if (! config('api.enabled')) {
            return false;
        }

        $user = auth()->user();

        return $user !== null
            && ($user->can(SystemPermission::ManageApiAccess) || $user->can(SystemPermission::ViewApiRequestLogs));
    }
}
