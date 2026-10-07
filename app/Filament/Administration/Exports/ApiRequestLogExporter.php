<?php

declare(strict_types=1);

namespace App\Filament\Administration\Exports;

use App\Domains\Api\Models\ApiRequestLog;
use App\Filament\Support\BaseExporter;
use Filament\Actions\Exports\ExportColumn;

class ApiRequestLogExporter extends BaseExporter
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

            ExportColumn::make('oauth_client.name')
                ->label('Client'),

            ExportColumn::make('grant_type')
                ->label('Grant')
                ->formatStateUsing(fn ($state) => $state?->getLabel()),

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

            ExportColumn::make('request_bytes')
                ->label('Request Size')
                ->enabledByDefault(false),

            ExportColumn::make('response_bytes')
                ->label('Response Size')
                ->enabledByDefault(false),

            ExportColumn::make('ip_address')
                ->label('IP Address'),

            ExportColumn::make('user_agent')
                ->label('User Agent')
                ->enabledByDefault(false),

            ExportColumn::make('created_at')
                ->label('Recorded'),
        ];
    }

    protected static function recordNoun(): string
    {
        return 'API request';
    }
}
