<?php

declare(strict_types=1);

namespace App\Filament\Administration\Exports;

use App\Domains\User\Models\User;
use App\Filament\Support\BaseExporter;
use Filament\Actions\Exports\ExportColumn;

class UserExporter extends BaseExporter
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
                ->label('Authentication')
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
        return 'user';
    }
}
