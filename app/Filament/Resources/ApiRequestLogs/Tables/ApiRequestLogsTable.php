<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiRequestLogs\Tables;

use App\Domains\Core\Enums\ApiRequestFailureEnum;
use App\Domains\User\Models\ApiRequestLog;
use App\Filament\Resources\Users\RelationManagers\ApiRequestLogsRelationManager;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class ApiRequestLogsTable
{
    public static function configure(Table $table): Table
    {
        $isRelationManager = $table->getLivewire() instanceof ApiRequestLogsRelationManager;

        return $table
            ->poll()
            ->recordClasses(fn (ApiRequestLog $record): ?string => $record->duration_ms > (int) config('auth.api.request_logging.slow_request_threshold_ms')
                ? 'bg-red-50/50 dark:bg-red-900/10'
                : null)
            ->columns([
                TextColumn::make('trace_id')
                    ->label('Trace ID')
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('user.username')
                    ->label('User')
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->hiddenOn(ApiRequestLogsRelationManager::class),

                TextColumn::make('api_token.id')
                    ->label('Token ID')
                    ->fontFamily(FontFamily::Mono)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'GET' => 'success',
                        'POST' => 'warning',
                        'PUT', 'PATCH' => 'info',
                        'DELETE' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('path')
                    ->label('Endpoint')
                    ->limit(40)
                    ->wrap()
                    ->tooltip(fn ($record) => $record->path)
                    ->fontFamily(FontFamily::Mono)
                    ->searchable(),

                TextColumn::make('route_name')
                    ->label('Route Name')
                    ->placeholder('N/A')
                    ->tooltip('The named route for this request')
                    ->wrap()
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status_code')
                    ->label('Status')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 200 && $state < 300 => 'success',
                        $state >= 300 && $state < 400 => 'info',
                        $state >= 400 && $state < 500 => 'warning',
                        $state >= 500 => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('failure_reason')
                    ->label('Failure Reason')
                    ->placeholder('N/A')
                    ->badge()
                    ->tooltip(fn (ApiRequestLog $record) => $record->failure_reason?->getDescription())
                    ->sortable()
                    ->searchable(),

                TextColumn::make('duration_ms')
                    ->label('Duration')
                    ->suffix(' ms')
                    ->color(fn (int $state): string => $state > config('auth.api.request_logging.slow_request_threshold_ms') ? 'danger' : 'gray')
                    ->weight(fn (int $state) => $state > config('auth.api.request_logging.slow_request_threshold_ms') ? FontWeight::Bold : FontWeight::Normal)
                    ->numeric()
                    ->sortable(),

                TextColumn::make('response_bytes')
                    ->label('Response Size')
                    ->placeholder('N/A')
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? Number::fileSize($state) : 'N/A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Recorded At')
                    ->dateTime()
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('api_request_logs.created_at', $direction)),
            ])
            ->defaultSort('created_at', 'desc')
            ->heading('API Request Logs')
            ->searchable(! $isRelationManager)
            ->defaultPaginationPageOption(10)
            ->filters([
                Filter::make('slow_requests')
                    ->label('Slow Requests (> ' . config('auth.api.request_logging.slow_request_threshold_ms') . ' ms)')
                    ->query(fn (Builder $query) => $query->where('duration_ms', '>', (int) config('auth.api.request_logging.slow_request_threshold_ms')))
                    ->toggle(),

                SelectFilter::make('status_range')
                    ->label('Status Range')
                    ->options([
                        '2xx' => '2xx Success',
                        '4xx' => '4xx Client Error',
                        '5xx' => '5xx Server Error',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        '2xx' => $query->whereBetween('status_code', [200, 299]),
                        '4xx' => $query->whereBetween('status_code', [400, 499]),
                        '5xx' => $query->whereBetween('status_code', [500, 599]),
                        default => $query,
                    }),

                SelectFilter::make('method')
                    ->options([
                        'GET' => 'GET',
                        'POST' => 'POST',
                        'PUT' => 'PUT',
                        'PATCH' => 'PATCH',
                        'DELETE' => 'DELETE',
                    ])
                    ->multiple(),

                SelectFilter::make('failure_reason')
                    ->label('Failure Reason')
                    ->options(ApiRequestFailureEnum::class)
                    ->multiple()
                    ->preload(),
            ])
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->label('Filters'),
            )
            ->recordActions([
                ViewAction::make()
                    ->label('View User')
                    ->url(fn (ApiRequestLog $record) => UserResource::getUrl('view', ['record' => $record->user]))
                    ->hidden($isRelationManager),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
