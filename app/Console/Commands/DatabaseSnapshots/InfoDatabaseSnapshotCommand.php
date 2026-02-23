<?php

declare(strict_types=1);

namespace App\Console\Commands\DatabaseSnapshots;

use App\Domains\Core\Database\SchemaChecksumManager;
use App\Domains\Core\Database\ValueObjects\SchemaSnapshot;
use Illuminate\Support\Facades\File;
use Spatie\DbSnapshots\Helpers\Format;

use function Laravel\Prompts\select;

/**
 * Displays detailed information about a database snapshot.
 *
 * This command shows metadata about a specific snapshot including file size,
 * creation date, and schema validation information.
 */
class InfoDatabaseSnapshotCommand extends DatabaseSnapshotCommand
{
    protected $signature = 'db:snapshot:info
                            {filename? : The snapshot file name (without extension) to inspect}';

    protected $description = 'Displays detailed information about a database snapshot';

    public function __construct(
        private readonly SchemaChecksumManager $schemaManager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $snapshots = $this->schemaManager->getSnapshots();

        if ($snapshots->isEmpty()) {
            $this->components->error('No snapshots found.');
            $this->newLine();
            $this->line('  <fg=gray>→</> Create a snapshot with: <comment>php artisan db:snapshot:create</comment>');
            $this->newLine();

            return self::FAILURE;
        }

        $snapshotName = $this->argument('filename');

        if ($snapshotName === null) {
            $snapshotName = (string) select(
                label: 'Select a snapshot to inspect',
                options: $this->buildSnapshotSelectOptions($snapshots),
            );
        } else {
            $snapshotName = $this->normalizeSnapshotName($snapshotName);
        }

        $snapshotPath = self::snapshotPath($snapshotName);

        if (! File::exists($snapshotPath)) {
            $this->components->error("Snapshot not found: {$snapshotName}");

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info("Snapshot: {$snapshotName}");

        $this->displayFileInfo($snapshotPath);
        $this->displaySchemaInfo($snapshotName);
        $this->displayCurrentSchemaComparison($snapshotName);

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Display file information for the snapshot.
     */
    private function displayFileInfo(string $snapshotPath): void
    {
        $fileSize = Format::humanReadableSize(File::size($snapshotPath));
        $lastModified = now()->createFromTimestamp(File::lastModified($snapshotPath))
            ->setTimezone(config('app.schedule_timezone', 'UTC'));

        $this->newLine();
        $this->line('  <fg=white;options=bold>File Information</>');
        $this->components->bulletList([
            "📄 Path: <fg=blue>{$snapshotPath}</>",
            "📏 Size: <fg=yellow>{$fileSize}</>",
            "📆 Modified: <fg=green>{$lastModified->format('M jS Y g:i A')}</> ({$lastModified->diffForHumans()})",
        ]);
    }

    /**
     * Display schema metadata for the snapshot.
     */
    private function displaySchemaInfo(string $snapshotName): void
    {
        $snapshot = $this->schemaManager->getSnapshotInfo($snapshotName);

        $this->newLine();
        $this->line('  <fg=white;options=bold>Schema Information</>');

        if (! $snapshot instanceof SchemaSnapshot) {
            $this->components->bulletList([
                '<fg=yellow>No schema metadata available</>',
                '<fg=gray>This snapshot was created without schema validation</>',
            ]);

            return;
        }

        $createdAt = $snapshot->createdAt
            ->setTimezone(config('app.schedule_timezone', 'UTC'));

        $this->components->bulletList([
            "🔐 Checksum: <fg=cyan>{$snapshot->checksum}</>",
            "📆 Created: <fg=green>{$createdAt->format('M jS Y g:i A')}</> ({$createdAt->diffForHumans()})",
            "🗃️ Migrations: <fg=yellow>{$snapshot->migrationCount}</>",
            "🌱 Seeders: <fg=yellow>{$snapshot->seederCount}</>",
        ]);
    }

    /**
     * Display comparison between snapshot schema and current codebase.
     */
    private function displayCurrentSchemaComparison(string $snapshotName): void
    {
        $snapshot = $this->schemaManager->getSnapshotInfo($snapshotName);

        if (! $snapshot instanceof SchemaSnapshot) {
            return;
        }

        $files = $this->schemaManager->collectSchemaFiles();
        $currentChecksum = $this->schemaManager->calculateCurrentCodebaseChecksum();
        $fileCounts = $files->counts();

        $this->newLine();
        $this->line('  <fg=white;options=bold>Schema Comparison</>');

        if ($snapshot->checksum === $currentChecksum) {
            $this->components->bulletList([
                '<fg=green>✓ Schema matches current codebase</>',
            ]);
        } else {
            $this->components->bulletList([
                '<fg=yellow>⚠ Schema differs from current codebase</>',
                "Current checksum: <fg=cyan>{$currentChecksum}</>",
                "Current migrations: <fg=yellow>{$fileCounts['migrations']}</> (snapshot: {$snapshot->migrationCount})",
                "Current seeders: <fg=yellow>{$fileCounts['seeders']}</> (snapshot: {$snapshot->seederCount})",
            ]);
        }
    }
}
