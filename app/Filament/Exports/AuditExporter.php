<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Domains\User\Models\Audit;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class AuditExporter extends Exporter
{
    protected static ?string $model = Audit::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),

            ExportColumn::make('trace_id')
                ->label('Trace ID')
                ->enabledByDefault(false),

            ExportColumn::make('event')
                ->label('Event')
                ->formatStateUsing(fn (string $state) => Str::of($state)->replace('_', ' ')->title()->toString()),

            ExportColumn::make('auditable_type')
                ->label('Record Type')
                ->formatStateUsing(function (string $state) {
                    $className = Relation::getMorphedModel($state) ?? $state;

                    return Str::afterLast($className, '\\') ?: $className;
                }),

            ExportColumn::make('auditable_id')
                ->label('Record ID'),

            ExportColumn::make('user.username')
                ->label('Username'),

            ExportColumn::make('user.full_name')
                ->label('User Name'),

            ExportColumn::make('impersonator.username')
                ->label('Impersonator'),

            ExportColumn::make('old_values')
                ->label('Old Values')
                ->formatStateUsing(fn ($state) => $state ? json_encode($state) : null)
                ->enabledByDefault(false),

            ExportColumn::make('new_values')
                ->label('New Values')
                ->formatStateUsing(fn ($state) => $state ? json_encode($state) : null)
                ->enabledByDefault(false),

            ExportColumn::make('url')
                ->label('URL')
                ->enabledByDefault(false),

            ExportColumn::make('ip_address')
                ->label('IP Address'),

            ExportColumn::make('user_agent')
                ->label('User Agent')
                ->enabledByDefault(false),

            ExportColumn::make('tags')
                ->label('Tags')
                ->enabledByDefault(false),

            ExportColumn::make('created_at')
                ->label('Created At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = number_format($export->successful_rows);
        $body = sprintf('Exported %s audit %s.', $count, Str::plural('record', $export->successful_rows));

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= sprintf(' %s %s failed.', number_format($failedRowsCount), Str::plural('row', $failedRowsCount));
        }

        return $body;
    }
}
