<?php

declare(strict_types=1);

namespace App\Filament\Administration\Exports;

use App\Domains\Access\Enums\RoleModificationOrigin;
use App\Domains\Core\Enums\AuditEvent;
use App\Domains\Core\Models\Audit;
use App\Filament\Support\BaseExporter;
use Filament\Actions\Exports\ExportColumn;

class RoleActivityExporter extends BaseExporter
{
    protected static ?string $model = Audit::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('event')
                ->label('Event')
                ->formatStateUsing(fn (string $state): string => AuditEvent::labelFor($state)),

            ExportColumn::make('auditable.username')
                ->label('NetID'),

            ExportColumn::make('auditable.clerical_name')
                ->label('Name'),

            ExportColumn::make('changed_role_names')
                ->label('Role Name')
                ->state(function (Audit $record): ?string {
                    $roles = $record->getChangedRoles();
                    if ($roles === []) {
                        return null;
                    }

                    return implode(', ', array_column($roles, 'name'));
                }),

            ExportColumn::make('changed_role_types')
                ->label('Role Type')
                ->state(function (Audit $record): ?string {
                    $roles = $record->getChangedRoles();
                    if ($roles === []) {
                        return null;
                    }

                    return implode(', ', array_column($roles, 'role_type'));
                }),

            ExportColumn::make('tags')
                ->label('Origin')
                ->formatStateUsing(function (?string $state): ?string {
                    if (! $state) {
                        return null;
                    }

                    $originValue = explode(',', $state)[0] ?? $state;
                    $origin = RoleModificationOrigin::tryFrom(trim($originValue));

                    return $origin?->getLabel() ?? $originValue;
                }),

            ExportColumn::make('user.username')
                ->label('Performed by NetID'),

            ExportColumn::make('user.clerical_name')
                ->label('Performed by Name'),

            ExportColumn::make('impersonator.username')
                ->label('Impersonator'),

            ExportColumn::make('created_at')
                ->label('Date'),
        ];
    }

    protected static function recordNoun(): string
    {
        return 'role activity record';
    }
}
