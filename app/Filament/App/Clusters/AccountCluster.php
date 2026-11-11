<?php

declare(strict_types=1);

namespace App\Filament\App\Clusters;

use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;

/**
 * The signed-in person's own area: profile, preferences,
 * personal access tokens and connected applications.
 *
 * People reach it from the user menu and the dashboard's Your Account
 * widget, since the sidebar is for the application's own features.
 */
class AccountCluster extends Cluster
{
    protected static ?string $title = 'Account';

    protected static ?string $slug = 'account';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
}
