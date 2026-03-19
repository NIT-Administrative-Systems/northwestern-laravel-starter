<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Tables;

use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\Role;
use App\Filament\Exports\RoleExporter;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Role')
                    ->searchable(),
                TextColumn::make('role_type.slug')
                    ->label('Role Type')
                    ->badge()
                    ->searchable(),
                IconColumn::make('assignment_locked')
                    ->label('Assignment Locked')
                    ->boolean()
                    ->trueIcon(Heroicon::LockClosed)
                    ->falseIcon(Heroicon::LockOpen)
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(fn (Role $record) => $record->isAssignmentLocked() ? 'Assigned programmatically - cannot be changed in the UI' : 'Assignment is open'),
                TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'gray' : 'success')
                    ->tooltip(function (Role $record): string {
                        if ($record->permissions->isEmpty()) {
                            return 'No permissions assigned';
                        }

                        $permissionLabels = $record->permissions
                            ->take(5)
                            ->pluck('label')
                            ->toArray();

                        $remaining = $record->permissions->count() - 5;
                        $tooltip = implode(', ', $permissionLabels);

                        if ($remaining > 0) {
                            $tooltip .= ", +{$remaining} more";
                        }

                        return $tooltip;
                    })
                    ->weight(FontWeight::Medium)
                    ->sortable(),
                TextColumn::make('users_count')
                    ->label('Assigned Users')
                    ->counts('users')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->label('Filters'),
            )
            ->recordActions([
                ViewAction::make()
                    ->visible(function ($record) {
                        // System Managed roles are always read-only
                        if ($record->isSystemManagedType()) {
                            return true;
                        }

                        return auth()->user()->hasPermissionTo(SystemPermission::ViewRoles) &&
                            ! auth()->user()->hasPermissionTo(SystemPermission::EditRoles);
                    }),
                EditAction::make()
                    ->authorize(fn (Role $record) => ! $record->isSystemManagedType() && Gate::allows('update', $record)),
            ])
            ->toolbarActions([
                ExportAction::make()
                    ->label('Export')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->exporter(RoleExporter::class),
            ])
            ->emptyStateHeading('No roles defined')
            ->emptyStateDescription('Create roles to organize user permissions and access levels.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }
}
