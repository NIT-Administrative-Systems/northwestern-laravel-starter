<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Tables;

use App\Domains\Auth\Enums\PermissionEnum;
use App\Domains\Auth\Models\Role;
use App\Filament\Exports\RoleExporter;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

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
            ->recordActions([
                ViewAction::make()
                    ->visible(function ($record) {
                        // System Managed roles are always read-only
                        if ($record->isSystemManagedType()) {
                            return true;
                        }

                        return auth()->user()->hasPermissionTo(PermissionEnum::VIEW_ROLES) &&
                            ! auth()->user()->hasPermissionTo(PermissionEnum::EDIT_ROLES);
                    }),
                EditAction::make()
                    ->visible(function ($record) {
                        if ($record->isSystemManagedType()) {
                            return false;
                        }

                        return auth()->user()->hasPermissionTo(PermissionEnum::EDIT_ROLES);
                    }),
            ])
            ->toolbarActions([
                ExportAction::make()
                    ->label('Export')
                    ->exporter(RoleExporter::class),
            ]);
    }
}
