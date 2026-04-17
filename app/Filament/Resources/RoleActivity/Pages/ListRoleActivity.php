<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleActivity\Pages;

use App\Filament\Resources\RoleActivity\RoleActivityResource;
use App\Filament\Resources\RoleActivity\Widgets\RoleActivityStatsWidget;
use Filament\Resources\Pages\ListRecords;

class ListRoleActivity extends ListRecords
{
    protected static string $resource = RoleActivityResource::class;

    protected ?string $heading = 'Role Activity';

    protected ?string $subheading = 'Assignment and removal history for all roles';

    protected static ?int $defaultPaginationPageOption = 25;

    /** @return list<int> */
    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 25, 50];
    }

    /** @return array<class-string> */
    protected function getHeaderWidgets(): array
    {
        return [
            RoleActivityStatsWidget::class,
        ];
    }

    /** @return array<string> */
    public function getBreadcrumbs(): array
    {
        return [
            route('filament.administration.resources.roles.index') => 'Roles',
            '' => 'Role Activity',
        ];
    }
}
