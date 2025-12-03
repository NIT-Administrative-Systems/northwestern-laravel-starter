<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Tables;

use App\Domains\User\Enums\PermissionEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
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
                //
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
                //
            ]);
    }
}
