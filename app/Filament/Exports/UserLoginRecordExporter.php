<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Domains\User\Models\UserLoginRecord;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class UserLoginRecordExporter extends Exporter
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
                ->label('Logged In At'),

            ExportColumn::make('created_at')
                ->label('Created At')
                ->enabledByDefault(false),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = number_format($export->successful_rows);
        $body = sprintf('Exported %s login %s.', $count, Str::plural('record', $export->successful_rows));

        if (($failedRowsCount = $export->getFailedRowsCount()) !== 0) {
            $body .= sprintf(' %s %s failed.', number_format($failedRowsCount), Str::plural('row', $failedRowsCount));
        }

        return $body;
    }
}
