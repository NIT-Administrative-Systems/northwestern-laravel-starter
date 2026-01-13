<?php

declare(strict_types=1);

namespace App\Console\Commands\DatabaseSnapshots;

use App\Console\Commands\Concerns\RunsSteps;
use App\Domains\Core\Database\SchemaChecksumManager;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use RuntimeException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

/**
 * Deletes a database snapshot and its associated metadata.
 *
 * This command provides a safe way to remove database snapshots, including
 * the SQL file and any schema validation metadata.
 */
class DeleteDatabaseSnapshotCommand extends DatabaseSnapshotCommand
{
    use RunsSteps;

    protected $signature = 'db:snapshot:delete
                            {filename? : The snapshot file name (without extension) to delete}
                            {--all : Delete all snapshots}';

    protected $description = 'Deletes a database snapshot and its metadata';

    public function __construct(
        private readonly SchemaChecksumManager $schemaManager,
    ) {
        parent::__construct();
    }

    protected function successMessage(): string
    {
        return 'Snapshot deleted successfully';
    }

    public function handle(): int
    {
        if (App::isProduction()) {
            $this->components->error('This command cannot be run in production.');

            return self::FAILURE;
        }

        $snapshots = $this->schemaManager->getSnapshots();

        if ($snapshots->isEmpty()) {
            $this->components->error('No snapshots found.');
            $this->newLine();
            $this->line('  <fg=gray>→</> Create a snapshot with: <comment>php artisan db:snapshot:create</comment>');
            $this->newLine();

            return self::FAILURE;
        }

        if ($this->option('all')) {
            return $this->deleteAllSnapshots();
        }

        $snapshotName = $this->argument('filename');

        if ($snapshotName === null) {
            $snapshotName = select(
                label: 'Select a snapshot to delete',
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
        $this->components->info('Deleting Database Snapshot');
        $this->displaySnapshotInfo($snapshotPath);
        $this->newLine();

        if (! confirm("Delete snapshot '{$snapshotName}'?", default: false)) {
            $this->components->warn('Deletion cancelled.');

            return self::SUCCESS;
        }

        if (! $this->runStep('Deleting snapshot file', fn () => $this->deleteSnapshotFile($snapshotPath))) {
            $this->displaySummary();

            return self::FAILURE;
        }

        if (! $this->runStep('Removing metadata', fn () => $this->schemaManager->removeSnapshotMetadata($snapshotName))) {
            $this->displaySummary();

            return self::FAILURE;
        }

        $this->displaySummary();

        return self::SUCCESS;
    }

    /**
     * Delete all snapshots after confirmation.
     */
    private function deleteAllSnapshots(): int
    {
        $snapshots = $this->schemaManager->getSnapshots();
        $count = $snapshots->count();

        $this->newLine();
        $this->components->warn("This will delete {$count} snapshot(s).");
        $this->newLine();

        $this->displayAllSnapshotsTable($snapshots);
        $this->newLine();

        if (! confirm("Delete all {$count} snapshots?", default: false)) {
            $this->components->warn('Deletion cancelled.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->info('Deleting All Snapshots');

        foreach ($snapshots as $snapshot) {
            $snapshotPath = self::snapshotPath($snapshot->name);

            if (! $this->runStep("Deleting {$snapshot->name}", function () use ($snapshotPath, $snapshot): void {
                $this->deleteSnapshotFile($snapshotPath);
                $this->schemaManager->removeSnapshotMetadata($snapshot->name);
            })) {
                $this->displaySummary();

                return self::FAILURE;
            }
        }

        $this->displaySummary();

        return self::SUCCESS;
    }

    /**
     * Delete the snapshot SQL file.
     */
    private function deleteSnapshotFile(string $snapshotPath): void
    {
        if (! File::delete($snapshotPath)) {
            throw new RuntimeException("Failed to delete snapshot file: {$snapshotPath}");
        }
    }
}
