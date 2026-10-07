<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Domains\Auth\Models\Role;
use Filament\Actions\Exports\ExportColumn;

class RoleExporter extends BaseExporter
{
    protected static ?string $model = Role::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),

            ExportColumn::make('name')
                ->label('Name'),

            ExportColumn::make('role_type.label')
                ->label('Role Type'),

            ExportColumn::make('assignment_locked')
                ->label('Assignment Locked'),

            ExportColumn::make('permissions_count')
                ->label('Permissions Count')
                ->counts('permissions'),

            ExportColumn::make('permissions.name')
                ->label('Permissions')
                ->listAsJson(),

            ExportColumn::make('users_count')
                ->label('Users Count')
                ->counts('users'),

            ExportColumn::make('guard_name')
                ->label('Guard')
                ->enabledByDefault(false),

            ExportColumn::make('created_at')
                ->label('Created'),

            ExportColumn::make('updated_at')
                ->label('Updated')
                ->enabledByDefault(false),

            ExportColumn::make('deleted_at')
                ->label('Deleted')
                ->enabledByDefault(false),
        ];
    }

    protected static function recordNoun(): string
    {
        return 'role';
    }
}
