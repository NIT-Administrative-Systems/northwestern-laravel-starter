<?php

declare(strict_types=1);

namespace App\Console\Commands\DatabaseSnapshots;

use App\Domains\Core\Database\ValueObjects\SnapshotListItem;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Spatie\DbSnapshots\Helpers\Format;

use function Laravel\Prompts\table;

abstract class DatabaseSnapshotCommand extends Command
{
    use ConfirmableTrait;

    /**
     * Get the full path to a snapshot file.
     */
    public static function snapshotPath(string $snapshotName): string
    {
        return database_path("snapshots/{$snapshotName}.sql");
    }

    /**
     * Display information about a single snapshot file.
     */
    protected function displaySnapshotInfo(string $snapshotPath, ?string $checksum = null): void
    {
        if (! File::exists($snapshotPath)) {
            $this->components->error("Snapshot file not found: {$snapshotPath}");

            return;
        }

        $fileSize = Format::humanReadableSize(File::size($snapshotPath));
        $fileTimestamp = Carbon::createFromTimestamp(File::lastModified($snapshotPath))
            ->setTimezone(config('app.schedule_timezone', 'UTC'))
            ->format('M jS Y g:i A');

        $bulletPoints = [
            "📄 File: <fg=blue>{$snapshotPath}</>",
            "📏 Size: <fg=yellow>{$fileSize}</>",
            "📆 Created: <fg=green>{$fileTimestamp}</>",
        ];

        if ($checksum !== null) {
            $bulletPoints[] = "🔐 Schema Checksum: <fg=green>{$checksum}</>";
        }

        $this->components->bulletList($bulletPoints);
    }

    /**
     * Display a table of all snapshot files.
     *
     * @param  Collection<int, SnapshotListItem>  $snapshots
     */
    protected function displayAllSnapshotsTable(Collection $snapshots): void
    {
        table([
            'Name',
            'Size',
            'Created',
        ], $snapshots->map(fn (SnapshotListItem $snapshot): array => [
            $snapshot->name,
            Format::humanReadableSize($snapshot->size),
            $snapshot->createdAt
                ->timezone(config('app.schedule_timezone'))
                ->format('M jS Y g:i A'),
        ])->all());
    }

    /**
     * Build select options for snapshot selection with detailed labels.
     *
     * @param  Collection<int, SnapshotListItem>  $snapshots
     * @return array<string, string>
     */
    protected function buildSnapshotSelectOptions(Collection $snapshots): array
    {
        return $snapshots->mapWithKeys(function (SnapshotListItem $snapshot): array {
            $label = sprintf(
                '%s (%s, %s)',
                $snapshot->name,
                Format::humanReadableSize($snapshot->size),
                $snapshot->createdAt->diffForHumans(),
            );

            return [$snapshot->name => $label];
        })->all();
    }

    /**
     * Get a normalized snapshot name from user input (strips file extension if present).
     */
    protected function normalizeSnapshotName(string $inputName): string
    {
        return pathinfo($inputName, PATHINFO_FILENAME);
    }
}
