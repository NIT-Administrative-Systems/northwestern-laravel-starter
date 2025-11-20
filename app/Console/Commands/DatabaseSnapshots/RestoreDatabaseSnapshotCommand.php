<?php

declare(strict_types=1);

namespace App\Console\Commands\DatabaseSnapshots;

use App\Domains\Core\Database\ConfigurableDbDumperFactory;
use App\Domains\Core\Database\SchemaChecksumManager;
use App\Domains\Core\Database\ValueObjects\SchemaSnapshot;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Restores a database snapshot with schema validation.
 *
 * This command provides a safe way to restore database snapshots by validating
 * schema compatibility before proceeding with the restore operation.
 *
 * Requirements:
 * - PostgreSQL: `psql` CLI utility must be in your `$PATH`
 */
class RestoreDatabaseSnapshotCommand extends DatabaseSnapshotCommand
{
    protected $signature = 'db:snapshot:restore
                            {filename? : The snapshot file name (without extension) to restore}
                            {--force : Skip confirmations}
                            {--skip-schema-validation : Skip schema validation checks}
                            {--backup : Create a backup before restoring}';

    protected $description = 'Safely restores a database snapshot with schema validation';

    public function __construct(
        private readonly ExecutableFinder $executableFinder,
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

        if (! File::exists($snapshotPath)) {
            $this->components->error("Database snapshot file not found: {$snapshotPath}");

            return self::FAILURE;
        }

        $this->components->info('Restoring database snapshot...');
        $this->displaySnapshotInfo($snapshotPath);
        $this->newLine();

        try {
            // Create backup if requested
            if ($this->option('backup')) {
                $backupName = $snapshotName . '-pre-restore-' . now()->format('Y-m-d-His');
                if (! $this->createBackup($backupName)) {
                    return self::FAILURE;
                }
            }

            // Validate schema unless skipped
            if (! $this->option('skip-schema-validation') && ! $this->validateSnapshotSchema($snapshotName)) {
                return self::FAILURE;
            }

            // Perform the restore
            $this->dropAllTables();
            $this->loadSnapshot($snapshotPath);

            $this->components->success('Database snapshot restored successfully.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            Log::error('Failed to restore database snapshot', [
                'snapshot' => $snapshotName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->components->error('Error restoring database snapshot: ' . $e->getMessage());
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }
    }

    /**
     * Validate the schema compatibility of the given snapshot. An "invalid" schema does not necessarily mean
     * that the snapshot cannot be restored, but it may indicate that certain features or data types may not
     * function as expected.
     *
     * For example, a user creates a database snapshot. Then, they update their branch from the remote repository
     * which contains new migrations or seeder changes. If they try to restore their old snapshot, it may cause
     * unexpected behaviour or exceptions if the updated codebase relies on the new schema changes.
     */
    private function validateSnapshotSchema(string $snapshotName): bool
    {
        try {
            $snapshot = $this->schemaManager->getSnapshotInfo($snapshotName);

            if (! $snapshot instanceof SchemaSnapshot) {
                $this->newLine();
                $this->components->warn('⚠️ No schema information found for this snapshot.');
                $this->components->info('This snapshot was likely created without schema validation or using an older version.');

                return $this->confirm('Continue without schema validation?');
            }

            $files = $this->schemaManager->collectSchemaFiles();
            $currentChecksum = $this->schemaManager->calculateCurrentCodebaseChecksum();
            $fileCounts = $files->counts();

            if ($snapshot->checksum !== $currentChecksum) {
                $this->newLine();
                $this->components->warn('<options=bold>⚠️ SCHEMA MISMATCH DETECTED ⚠️</>');
                $this->components->bulletList([
                    "Snapshot checksum: <fg=yellow>{$snapshot->checksum}</>",
                    "Current schema checksum: <fg=yellow>{$currentChecksum}</>",
                    "Created at: <fg=yellow>{$snapshot->createdAt->format('M jS Y g:i A')}</>",
                    "Current schema files: <fg=yellow>{$fileCounts['migrations']} migrations</> and <fg=yellow>{$fileCounts['seeders']} seeders</>",
                    "Snapshot schema files: <fg=yellow>{$snapshot->migrationCount} migrations</> and <fg=yellow>{$snapshot->seederCount} seeders</>",
                ]);

                return $this->confirm(
                    'The database schema or seeders have been modified since this snapshot was created. This could result in unexpected behavior. Continue anyway?',
                );
            }

            return true;
        } catch (Throwable $e) {
            $this->newLine();
            $this->components->error('Schema validation failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Create a backup of the current database state.
     */
    private function createBackup(string $backupName): bool
    {
        $success = false;
        $this->components->task(
            'Creating backup before restore',
            function () use ($backupName, &$success): void {
                $success = $this->callSilent('db:snapshot:create', ['filename' => $backupName]) === self::SUCCESS;
            }
        );

        return $success;
    }

    /**
     * Drop all tables in the database.
     */
    private function dropAllTables(): void
    {
        $this->components->task('Dropping all tables', function (): void {
            DB::connection(DB::getDefaultConnection())
                ->getSchemaBuilder()
                ->dropAllTables();

            DB::reconnect();
        });
    }

    /**
     * Load the given database snapshot into the current database.
     */
    private function loadSnapshot(string $snapshotPath): void
    {
        $this->components->task('Loading database snapshot', function () use ($snapshotPath): void {
            $processFactory = function (...$arguments): Process {
                $quote = ConfigurableDbDumperFactory::determineQuoteForPlatform();
                $pgBinDir = ConfigurableDbDumperFactory::findPostgresDirectory();

                $originalCommand = (string) Arr::first($arguments);
                $util = Str::before($originalCommand, ' ');
                $commandPath = $this->executableFinder->find($util, extraDirs: array_filter([$pgBinDir]));

                if ($commandPath === null) {
                    throw new RuntimeException("Required utility not found: {$util}");
                }

                $arguments[0] = "{$quote}{$commandPath}{$quote}" . ' ' . Str::after($originalCommand, ' ');

                return Process::fromShellCommandline(...$arguments)->setTimeout(null);
            };

            /** @phpstan-ignore-next-line getSchemaState exists but Larastan's stub doesn't seem to have it */
            DB::connection(DB::getDefaultConnection())
                ->getSchemaState(processFactory: $processFactory)
                ->handleOutputUsing(function ($type, $buffer): void {
                    $this->output->write($buffer);
                })
                ->load($snapshotPath);
        });
    }
}
