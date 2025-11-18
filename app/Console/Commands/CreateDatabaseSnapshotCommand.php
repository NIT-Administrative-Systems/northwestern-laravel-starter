<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Core\Database\SchemaChecksumManager;
use App\Domains\Core\Database\ValueObjects\SchemaSnapshot;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

/**
 * Creates a database snapshot with schema validation.
 *
 * This command wraps `spatie/laravel-db-snapshots` and adds schema validation
 * to ensure snapshots are compatible with the current codebase state.
 *
 * Requirements:
 * - PostgreSQL: `pg_dump` and `psql` CLI utilities must be in your `$PATH`
 */
class CreateDatabaseSnapshotCommand extends DatabaseSnapshotCommand
{
    public const DEFAULT_SNAPSHOT_NAME = 'database-dump';

    protected $signature = 'db:snapshot:create
                            {filename? : The name of the snapshot file to generate}
                            {--force : Skip confirmations}
                            {--skip-schema-validation : Skip schema validation checks}';

    protected $description = 'Creates a database snapshot with schema validation.';

    public function __construct(
        private readonly SchemaChecksumManager $schemaManager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $snapshotName = $this->normalizeSnapshotName($this->argument('filename'));
        $snapshotPath = self::snapshotPath($snapshotName);

        $this->components->info('Creating database snapshot...');

        try {
            // Collect schema information and calculate checksum
            $schemaFiles = null;
            $checksum = null;

            if (! $this->option('skip-schema-validation')) {
                $this->components->task('Collecting schema information', function () use (&$schemaFiles): bool {
                    $schemaFiles = $this->schemaManager->collectSchemaFiles();

                    return true;
                });

                $this->components->task('Calculating schema checksum', function () use (&$checksum): bool {
                    $checksum = $this->schemaManager->calculateCurrentCodebaseChecksum();

                    return true;
                });
            }

            // Create the snapshot directory if it doesn't exist
            $snapshotDir = dirname($snapshotPath);
            if (! File::exists($snapshotDir) && ! File::makeDirectory($snapshotDir, recursive: true)) {
                throw new RuntimeException("Unable to create snapshot directory: {$snapshotDir}");
            }

            // Create the database dump
            $this->components->task('Creating database dump', function () use ($snapshotName): bool {
                return $this->callSilent('snapshot:create', ['name' => $snapshotName]) === self::SUCCESS;
            });

            // Save schema metadata if validation is enabled
            if (! $this->option('skip-schema-validation')) {
                $this->components->task('Saving schema metadata', function () use ($snapshotName, $checksum, $schemaFiles): bool {
                    $snapshot = new SchemaSnapshot(
                        name: $snapshotName,
                        checksum: $checksum,
                        createdAt: now(),
                        migrationCount: count($schemaFiles->migrations),
                        seederCount: count($schemaFiles->seeders)
                    );

                    $this->schemaManager->saveSnapshot($snapshotName, $snapshot);

                    return true;
                });
            }

            $this->components->success('Database snapshot created successfully.');
            $this->displaySnapshotInfo($snapshotPath, $checksum);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->components->error('Error generating database snapshot: ' . $e->getMessage());
            $this->error($e->getTraceAsString());

            // Attempt to clean up failed snapshot
            if (File::exists($snapshotPath)) {
                File::delete($snapshotPath);
            }

            return self::FAILURE;
        }
    }
}
