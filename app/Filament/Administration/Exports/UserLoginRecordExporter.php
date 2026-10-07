<?php

declare(strict_types=1);

namespace App\Filament\Administration\Exports;

use App\Domains\User\Models\UserLoginRecord;
use App\Filament\Support\BaseExporter;
use Filament\Actions\Exports\ExportColumn;

class UserLoginRecordExporter extends BaseExporter
{
    protected static ?string $model = UserLoginRecord::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),

            ExportColumn::make('user_id')
                ->label('User ID')
                ->enabledByDefault(false),

            ExportColumn::make('user.username')
                ->label('Username'),

            ExportColumn::make('user.full_name')
                ->label('Name'),

            ExportColumn::make('user.email')
                ->label('Email')
                ->enabledByDefault(false),

            ExportColumn::make('segment')
                ->label('Segment')
                ->formatStateUsing(fn ($state) => $state?->getLabel()),

            ExportColumn::make('logged_in_at')
                ->label('Signed In'),

            ExportColumn::make('ip_address')
                ->label('IP Address')
                ->enabledByDefault(false),

            ExportColumn::make('user_agent')
                ->label('User Agent')
                ->enabledByDefault(false),

            ExportColumn::make('created_at')
                ->label('Created')
                ->enabledByDefault(false),
        ];
    }

    protected static function recordNoun(): string
    {
        return 'login record';
    }
}
