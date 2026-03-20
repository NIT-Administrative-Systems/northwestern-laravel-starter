<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserLoginRecords\Tables;

use App\Domains\User\Enums\UserSegment;
use App\Domains\User\Models\UserLoginRecord;
use App\Filament\Exports\UserLoginRecordExporter;
use App\Filament\Resources\Users\RelationManagers\LoginRecordsRelationManager;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserLoginRecordsTable
{
    public static function configure(Table $table): Table
    {
        $isRelationManager = $table->getLivewire() instanceof LoginRecordsRelationManager;

        return $table
            ->columns([
                TextColumn::make('user.clerical_name')
                    ->label('Name')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        /** @phpstan-ignore-next-line  */
                        return $query->orWhereHas('user', fn (Builder $q) => $q->searchByName($search));
                    })
                    ->hiddenOn(LoginRecordsRelationManager::class),
                TextColumn::make('user.username')
                    ->label('Username')
                    ->fontFamily(FontFamily::Mono)
                    ->sortable()
                    ->searchable()
                    ->hiddenOn(LoginRecordsRelationManager::class),
                TextColumn::make('logged_in_at')
                    ->label('Logged In At')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                TextColumn::make('segment')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user_agent')
                    ->label('User Agent')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('logged_in_at', direction: 'desc')
            ->heading('Login Records')
            ->searchable(! $isRelationManager)
            ->filters([
                SelectFilter::make('segment')
                    ->multiple()
                    ->options(UserSegment::class)
                    ->hiddenOn(LoginRecordsRelationManager::class),
            ])
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->label('Filters'),
            )
            ->recordActions([
                ViewAction::make()
                    ->label('View User')
                    ->url(fn (UserLoginRecord $record) => UserResource::getUrl('view', ['record' => $record->user]))
                    ->hidden($isRelationManager),
            ])
            ->toolbarActions([
                ExportAction::make()
                    ->label('Export')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->exporter(UserLoginRecordExporter::class)
                    ->hidden(fn () => $table->getLivewire() instanceof LoginRecordsRelationManager),
            ])
            ->emptyStateHeading('No login activity')
            ->emptyStateDescription('Login records will appear here as users authenticate.')
            ->emptyStateIcon('heroicon-o-arrow-right-on-rectangle');
    }
}
