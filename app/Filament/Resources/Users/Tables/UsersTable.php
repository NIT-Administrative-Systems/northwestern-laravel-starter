<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Tables;

use App\Domains\Auth\Enums\AuthTypeEnum;
use App\Domains\User\Enums\AffiliationEnum;
use App\Domains\User\Models\User;
use App\Filament\Exports\UserExporter;
use App\Filament\Resources\Users\Support\NetIdStatus;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('clerical_name')
                    ->sortable(query: function (Builder $query, $direction) {
                        return $query->orderBy('last_name', $direction)->orderBy('first_name', $direction);
                    })
                    ->label('Name'),
                TextColumn::make('first_name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('username')
                    ->label('Username')
                    ->fontFamily(FontFamily::Mono)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('employee_id')
                    ->label('Employee ID')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('hr_employee_id')
                    ->label('myHR Employee ID')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')
                    ->label('Email')
                    ->copyable()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('timezone')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('primary_affiliation')
                    ->label('Primary Affiliation')
                    ->searchable()
                    ->badge(),
                TextColumn::make('auth_type')
                    ->label('Authentication')
                    ->badge()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('roles.name')
                    ->badge()
                    ->searchable(),
                IconColumn::make('netid_inactive')
                    ->label('NetID Status')
                    ->getStateUsing(fn (User $record) => NetIdStatus::getState($record))
                    ->icon(fn (User $record) => NetIdStatus::getIcon($record))
                    ->color(fn (User $record) => NetIdStatus::getColor($record))
                    ->tooltip(fn (User $record) => NetIdStatus::getLabel($record))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_directory_sync_at')
                    ->label('Last Directory Sync At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('directory_sync_last_failed_at')
                    ->label('Last Directory Sync Failed At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Deleted At')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roles'))
            ->defaultSort(function (Builder $query) {
                return $query->orderBy('last_name')->orderBy('first_name');
            })
            ->filters([
                SelectFilter::make('primary_affiliation')
                    ->label('Primary Affiliation')
                    ->options(AffiliationEnum::class)
                    ->multiple(),
                SelectFilter::make('auth_type')
                    ->label('Authentication')
                    ->options(AuthTypeEnum::class)
                    ->multiple(),
                SelectFilter::make('role')
                    ->label('Role')
                    ->relationship('roles', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),
                TrashedFilter::make(),
            ])
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->label('Filters'),
            )
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                ExportAction::make()
                    ->label('Export')
                    ->exporter(UserExporter::class),
            ])
            ->emptyStateHeading('No users found')
            ->emptyStateIcon('heroicon-o-users');
    }
}
