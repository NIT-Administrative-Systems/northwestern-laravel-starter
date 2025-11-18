<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiRequestLogs\Widgets;

use Filament\Widgets\Widget;

class NoProtectedApiRoutesBanner extends Widget
{
    protected string $view = 'filament.resources.api-request-logs.widgets.no-protected-api-routes-banner';

    protected int|string|array $columnSpan = 'full';
}
