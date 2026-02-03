<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Domains\Auth\Models\ApiRequestLog;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class ApiRequestLogExporter extends Exporter
{
    protected static ?string $model = ApiRequestLog::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),

            ExportColumn::make('trace_id')
                ->label('Trace ID')
                ->enabledByDefault(false),

            ExportColumn::make('user.username')
                ->label('Username'),

            ExportColumn::make('access_token.name')
                ->label('Token Name'),

            ExportColumn::make('method')
                ->label('Method'),

            ExportColumn::make('path')
                ->label('Path'),

            ExportColumn::make('route_name')
                ->label('Route Name')
                ->enabledByDefault(false),

            ExportColumn::make('status_code')
                ->label('Status Code'),

            ExportColumn::make('failure_reason')
                ->label('Failure Reason')
                ->formatStateUsing(fn ($state) => $state?->getLabel()),

            ExportColumn::make('duration_ms')
                ->label('Duration (ms)'),

            ExportColumn::make('response_bytes')
                ->label('Response Size')
                ->enabledByDefault(false),

            ExportColumn::make('ip_address')
                ->label('IP Address'),

            ExportColumn::make('user_agent')
                ->label('User Agent')
                ->enabledByDefault(false),

            ExportColumn::make('created_at')
                ->label('Recorded At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = number_format($export->successful_rows);
        $body = sprintf('Exported %s API %s.', $count, Str::plural('request', $export->successful_rows));

        if (($failedRowsCount = $export->getFailedRowsCount()) !== 0) {
            $body .= sprintf(' %s %s failed.', number_format($failedRowsCount), Str::plural('row', $failedRowsCount));
        }

        return $body;
    }
}
