<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserLoginRecords\Tables;

use App\Domains\User\Enums\UserSegmentEnum;
use App\Domains\User\Models\UserLoginRecord;
use App\Filament\Resources\Users\RelationManagers\LoginRecordsRelationManager;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\ViewAction;
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
                    ->sortable()
                    ->searchable()
                    ->hiddenOn(LoginRecordsRelationManager::class),
                TextColumn::make('logged_in_at')
                    ->label('Logged In At')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('segment')
                    ->badge()
                    ->searchable()
                    ->sortable(),
            ])
            ->defaultSort('logged_in_at', direction: 'desc')
            ->heading('Login Records')
            ->searchable(! $isRelationManager)
            ->filters([
                SelectFilter::make('segment')
                    ->multiple()
                    ->options(UserSegmentEnum::class)
                    ->hiddenOn(LoginRecordsRelationManager::class),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('View User')
                    ->url(fn (UserLoginRecord $record) => UserResource::getUrl('view', ['record' => $record->user]))
                    ->hidden($isRelationManager),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
