<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Northwestern\SysDev\Chassis\Formatting\CountInWords;

/**
 * The base for the application's exporters: declare the columns and what
 * a row is, and every export is then safe to open in any spreadsheet.
 *
 * A cell that starts with =, +, -, @, a tab or a carriage return
 * runs as a formula in Excel or Google Sheets. A name such as
 * =HYPERLINK(...) could then act on whoever opens the file.
 *
 * Every text cell that starts with one gets a
 * leading apostrophe, whatever its column,
 * so a new exporter is safe without its
 * author remembering to make it so.
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
     * Put an apostrophe before a text cell a spreadsheet would
     * run as a formula, and leave anything else unchanged.
     */
    public static function neutralize(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'" . $value : $value;
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Exported ' . CountInWords::of($export->successful_rows, static::recordNoun()) . '.';

        if (($failed = $export->getFailedRowsCount()) !== 0) {
            $body .= ' ' . Str::ucfirst(CountInWords::of($failed, 'row')) . ' failed.';
        }

        return $body;
    }
}
