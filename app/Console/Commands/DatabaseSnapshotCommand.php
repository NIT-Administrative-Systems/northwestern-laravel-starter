<?php

declare(strict_types=1);

namespace App\Console\Commands;

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

    public const DEFAULT_SNAPSHOT_NAME = 'database-dump';

    /**
     * Get the full path to a snapshot file.
     */
    public static function snapshotPath(string $snapshotName = self::DEFAULT_SNAPSHOT_NAME): string
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
        ])->toArray());
    }

    /**
     * Get a normalized snapshot name from user input.
     */
    protected function normalizeSnapshotName(?string $inputName): string
    {
        if ($inputName === null) {
            return self::DEFAULT_SNAPSHOT_NAME;
        }

        return pathinfo($inputName, PATHINFO_FILENAME);
    }
}
