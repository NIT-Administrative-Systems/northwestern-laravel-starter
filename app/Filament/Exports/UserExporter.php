<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Domains\User\Models\User;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class UserExporter extends Exporter
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),

            ExportColumn::make('username')
                ->label('Username'),

            ExportColumn::make('first_name')
                ->label('First Name'),

            ExportColumn::make('last_name')
                ->label('Last Name'),

            ExportColumn::make('email')
                ->label('Email'),

            ExportColumn::make('employee_id')
                ->label('Employee ID'),

            ExportColumn::make('hr_employee_id')
                ->label('myHR Employee ID')
                ->enabledByDefault(false),

            ExportColumn::make('phone')
                ->label('Phone')
                ->enabledByDefault(false),

            ExportColumn::make('primary_affiliation')
                ->label('Primary Affiliation')
                ->formatStateUsing(fn ($state) => $state?->getLabel()),

            ExportColumn::make('auth_type')
                ->label('Auth Type')
                ->formatStateUsing(fn ($state) => $state?->getLabel()),

            ExportColumn::make('roles.name')
                ->label('Roles')
                ->listAsJson(),

            ExportColumn::make('netid_inactive')
                ->label('NetID Inactive')
                ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),

            ExportColumn::make('description')
                ->label('Description')
                ->enabledByDefault(false),

            ExportColumn::make('job_titles')
                ->label('Job Titles')
                ->listAsJson(),

            ExportColumn::make('departments')
                ->label('Departments')
                ->listAsJson(),

            ExportColumn::make('timezone')
                ->label('Timezone')
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
        $body = sprintf('Exported %s %s.', $count, Str::plural('user', $export->successful_rows));

        if (($failedRowsCount = $export->getFailedRowsCount()) !== 0) {
            $body .= sprintf(' %s %s failed.', number_format($failedRowsCount), Str::plural('row', $failedRowsCount));
        }

        return $body;
    }
}
