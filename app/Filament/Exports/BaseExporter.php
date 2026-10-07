<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The base for the application's exporters: declare the columns and what a row is, and every
 * export is safe to open in a spreadsheet.
 *
 * A cell that starts with `=`, `+`, `-`, `@`, a tab or a carriage return would run as a formula
 * in Excel or Google Sheets, so a name like `=HYPERLINK(...)` could act on whoever opens the
 * file (CSV injection). Every text cell that starts with one gets a leading apostrophe, whatever
 * the column, so a new exporter is safe without remembering to.
 *
 * ```php
 * class ProjectExporter extends BaseExporter
 * {
 *     protected static ?string $model = Project::class;
 *
 *     public static function getColumns(): array { return [ExportColumn::make('name')]; }
 *
 *     protected static function recordNoun(): string { return 'project'; }
 * }
 * ```
 */
abstract class BaseExporter extends Exporter
{
    /**
     * What one row is, singular, for the completion message: "audit record", "user".
     */
    abstract protected static function recordNoun(): string;

    /**
     * @return array<mixed>
     */
    public function __invoke(Model $record): array
    {
        return array_map(self::neutralize(...), parent::__invoke($record));
    }

    /**
     * A text cell a spreadsheet would run as a formula, with an apostrophe in front; anything else unchanged.
     */
    public static function neutralize(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'" . $value : $value;
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $exported = $export->successful_rows;
        $body = sprintf('Exported %s %s.', number_format($exported), Str::plural(static::recordNoun(), $exported));

        if (($failed = $export->getFailedRowsCount()) !== 0) {
            $body .= sprintf(' %s %s failed.', number_format($failed), Str::plural('row', $failed));
        }

        return $body;
    }
}
