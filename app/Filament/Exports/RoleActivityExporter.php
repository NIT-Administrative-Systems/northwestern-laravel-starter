<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Domains\Auth\Enums\RoleModificationOrigin;
use App\Domains\User\Models\Audit;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class RoleActivityExporter extends Exporter
{
    protected static ?string $model = Audit::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('event')
                ->label('Event')
                ->formatStateUsing(fn (string $state) => match ($state) {
                    'role_assigned' => 'Role Assigned',
                    'role_removed' => 'Role Removed',
                    default => Str::of($state)->replace('_', ' ')->title()->toString(),
                }),

            ExportColumn::make('auditable.username')
                ->label('User NetID')
                ->formatStateUsing(fn (?string $state) => self::sanitizeCsvFormula($state)),

            ExportColumn::make('auditable.clerical_name')
                ->label('User Name')
                ->formatStateUsing(fn (?string $state) => self::sanitizeCsvFormula($state)),

            ExportColumn::make('changed_role_names')
                ->label('Role Name')
                ->state(function (Audit $record): ?string {
                    $roles = $record->getChangedRoles();
                    if (empty($roles)) {
                        return null;
                    }

                    $names = implode(', ', array_column($roles, 'name'));

                    return self::sanitizeCsvFormula($names);
                }),

            ExportColumn::make('changed_role_types')
                ->label('Role Type')
                ->state(function (Audit $record): ?string {
                    $roles = $record->getChangedRoles();
                    if (empty($roles)) {
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
                ->label('Performed By NetID')
                ->formatStateUsing(fn (?string $state) => self::sanitizeCsvFormula($state)),

            ExportColumn::make('user.clerical_name')
                ->label('Performed By Name')
                ->formatStateUsing(fn (?string $state) => self::sanitizeCsvFormula($state)),

            ExportColumn::make('impersonator.username')
                ->label('Impersonator')
                ->formatStateUsing(fn (?string $state) => self::sanitizeCsvFormula($state)),

            ExportColumn::make('created_at')
                ->label('Date'),
        ];
    }

    private static function sanitizeCsvFormula(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = number_format($export->successful_rows);
        $body = sprintf('Exported %s role activity %s.', $count, Str::plural('record', $export->successful_rows));

        if (($failedRowsCount = $export->getFailedRowsCount()) !== 0) {
            $body .= sprintf(' %s %s failed.', number_format($failedRowsCount), Str::plural('row', $failedRowsCount));
        }

        return $body;
    }
}
