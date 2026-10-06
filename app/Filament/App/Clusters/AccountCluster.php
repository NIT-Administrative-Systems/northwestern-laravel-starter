<?php

declare(strict_types=1);

namespace App\Filament\App\Clusters;

use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;

/**
 * The signed-in user's own area: their profile, preferences, personal access tokens and
 * connected applications. Reached from the user menu and the dashboard's Your Account
 * widget, not the sidebar, which is for the application's features.
 */
class AccountCluster extends Cluster
{
    protected static ?string $title = 'Account';

    protected static ?string $slug = 'account';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
}
