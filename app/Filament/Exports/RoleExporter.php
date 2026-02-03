<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Domains\Auth\Models\Role;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class RoleExporter extends Exporter
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
                ->label('Created At'),

            ExportColumn::make('updated_at')
                ->label('Updated At')
                ->enabledByDefault(false),

            ExportColumn::make('deleted_at')
                ->label('Deleted At')
                ->enabledByDefault(false),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = number_format($export->successful_rows);
        $body = sprintf('Exported %s %s.', $count, Str::plural('role', $export->successful_rows));

        if (($failedRowsCount = $export->getFailedRowsCount()) !== 0) {
            $body .= sprintf(' %s %s failed.', number_format($failedRowsCount), Str::plural('row', $failedRowsCount));
        }

        return $body;
    }
}
