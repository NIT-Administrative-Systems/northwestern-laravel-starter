<?php

declare(strict_types=1);

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\EloquentServiceProvider::class,
    App\Providers\TelescopeServiceProvider::class,
    App\Providers\IntegrationServiceProvider::class,
    App\Providers\ViewServiceProvider::class,
    App\Providers\HealthServiceProvider::class,
    App\Providers\FilamentServiceProvider::class,
    App\Providers\Filament\AdministrationPanelProvider::class,
];
