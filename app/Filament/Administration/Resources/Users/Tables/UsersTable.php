<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Users\Tables;

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Auth\Enums\AuthType;
use App\Domains\User\Enums\Affiliation;
use App\Domains\User\Models\User;
use App\Filament\Administration\Exports\UserExporter;
use App\Filament\Administration\Resources\Users\Support\NetIdStatus;
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
                    ->sortable(query: fn (Builder $query, $direction) => $query->orderBy('last_name', $direction)->orderBy('first_name', $direction))
                    ->label('Name')
                    ->wrap(),
                TextColumn::make('first_name')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_name')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('username')
                    ->label('Username')
                    ->fontFamily(FontFamily::Mono)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('employee_id')
                    ->placeholder('—')
                    ->label('Employee ID')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('hr_employee_id')
                    ->placeholder('—')
                    ->label('myHR Employee ID')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')
                    ->placeholder('—')
                    ->label('Email')
                    ->copyable()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('timezone')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('primary_affiliation')
                    ->placeholder('—')
                    ->label('Primary Affiliation')
                    ->searchable()
                    ->badge()
                    ->visibleFrom('xl'),
                TextColumn::make('auth_type')
                    ->label('Authentication')
                    ->badge()
                    ->searchable()
                    ->toggleable()
                    ->visibleFrom('2xl'),
                self::rolesColumn(),
                IconColumn::make('netid_inactive')
                    ->label('NetID Status')
                    ->getStateUsing(fn (User $record) => NetIdStatus::getState($record))
                    ->icon(fn (User $record) => NetIdStatus::getIcon($record))
                    ->color(fn (User $record) => NetIdStatus::getColor($record))
                    ->tooltip(fn (User $record) => NetIdStatus::getLabel($record))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_directory_sync_at')
                    ->placeholder('Never')
                    ->label('Last Directory Sync')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('directory_sync_last_failed_at')
                    ->placeholder('Never')
                    ->label('Last Directory Sync Failed')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->placeholder('—')
                    ->label('Deleted')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // Roles aren't searchable: each search term would add a subquery
            // on roles, and the Role filter already matches roles exactly.
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roles'))
            ->defaultSort(fn (Builder $query) => $query->orderBy('last_name')->orderBy('first_name'))
            ->filters([
                SelectFilter::make('primary_affiliation')
                    ->label('Primary Affiliation')
                    ->options(Affiliation::class)
                    ->multiple(),
                SelectFilter::make('auth_type')
                    ->label('Authentication')
                    ->options(AuthType::class)
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
                    ->exporter(UserExporter::class),
            ])
            ->emptyStateHeading('No Users Found')
            ->emptyStateIcon('heroicon-o-users');
    }

    /**
     * A person's roles, leaving out the default role every NetID user has,
     * which would push the roles that tell people apart off the row. The
     * Assigned Users tab of a role uses it too, for the same reason.
     */
    public static function rolesColumn(): TextColumn
    {
        return TextColumn::make('roles.name')
            ->label('Roles')
            ->badge()
            ->state(fn (User $record): array => $record->roles
                ->pluck('name')
                ->reject(fn (string $name): bool => $name === SystemRole::NorthwesternUser->value)
                ->values()
                ->all())
            ->limitList(2)
            ->expandableLimitedList()
            ->listWithLineBreaks()
            ->wrap()
            // Long role names wrap inside the badge instead of widening the table.
            ->extraAttributes(['class' => 'max-w-48'])
            // Rows link to the user, so an empty cell would be a link with no text.
            ->placeholder(fn (User $record): string => $record->roles->isEmpty() ? 'No roles' : 'No other roles');
    }
}
