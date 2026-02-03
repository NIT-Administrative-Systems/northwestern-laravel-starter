<?php

declare(strict_types=1);

namespace App\Console\Commands\DatabaseSnapshots;

use App\Console\Commands\Concerns\RunsSteps;
use App\Domains\Core\Database\ConfigurableDbDumperFactory;
use App\Domains\Core\Database\SchemaChecksumManager;
use App\Domains\Core\Database\ValueObjects\SchemaSnapshot;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;

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
    use RunsSteps;

    protected $signature = 'db:snapshot:restore
                            {filename? : The snapshot file name (without extension) to restore}
                            {--skip-schema-validation : Skip schema validation checks}
                            {--backup : Create a backup before restoring}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Safely restores a database snapshot with schema validation';

    public function __construct(
        private readonly ExecutableFinder $executableFinder,
        private readonly SchemaChecksumManager $schemaManager,
    ) {
        parent::__construct();
    }

    protected function successMessage(): string
    {
        return 'Snapshot restored successfully';
    }

    public function handle(): int
    {
        if (App::isProduction()) {
            $this->components->error('This command cannot be run in production.');

            return self::FAILURE;
        }

        $snapshotName = $this->normalizeSnapshotName($this->argument('filename'));
        $snapshotPath = self::snapshotPath($snapshotName);

        if (! File::exists($snapshotPath)) {
            $this->components->error("Database snapshot file not found: {$snapshotPath}");

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Restoring Database Snapshot');
        $this->displaySnapshotInfo($snapshotPath);
        $this->newLine();

        // Validate schema unless skipped
        if (! $this->option('skip-schema-validation') && ! $this->validateSnapshotSchema($snapshotName)) {
            return self::FAILURE;
        }

        // Confirm before destructive operation (skip if --force is passed)
        if (! $this->option('force') && ! confirm('This will replace your current database. Continue?', default: false)) {
            $this->components->warn('Restore cancelled.');

            return self::SUCCESS;
        }

        // Create backup if requested
        if ($this->option('backup')) {
            $backupName = $snapshotName . '-pre-restore-' . now()->format('Y-m-d-His');
            if (! $this->runStep('Creating backup before restore', fn () => $this->createBackup($backupName))) {
                $this->displaySummary();

                return self::FAILURE;
            }
        }

        // Perform the restore steps
        if (! $this->runStep('Dropping all tables', fn () => $this->dropAllTables())) {
            $this->displaySummary();

            return self::FAILURE;
        }

        if (! $this->runStep('Loading database snapshot', fn () => $this->loadSnapshot($snapshotPath))) {
            $this->displaySummary();

            return self::FAILURE;
        }

        if (! $this->runStep('Clearing permission cache', fn () => $this->clearPermissionCache())) {
            $this->displaySummary();

            return self::FAILURE;
        }

        $this->displaySummary();

        return self::SUCCESS;
    }

    /**
     * Validate the schema compatibility of the given snapshot.
     *
     * An "invalid" schema does not necessarily mean that the snapshot cannot be restored,
     * but it may indicate that certain features or data types may not function as expected.
     */
    private function validateSnapshotSchema(string $snapshotName): bool
    {
        $snapshot = $this->schemaManager->getSnapshotInfo($snapshotName);

        if (! $snapshot instanceof SchemaSnapshot) {
            $this->newLine();
            $this->components->warn('No schema information found for this snapshot.');
            $this->components->info('This snapshot was likely created without schema validation or using an older version.');

            return confirm('Continue without schema validation?');
        }

        $files = $this->schemaManager->collectSchemaFiles();
        $currentChecksum = $this->schemaManager->calculateCurrentCodebaseChecksum();
        $fileCounts = $files->counts();

        if ($snapshot->checksum !== $currentChecksum) {
            $this->newLine();
            $this->components->warn('<options=bold>SCHEMA MISMATCH DETECTED</>');
            $this->components->bulletList([
                "Snapshot checksum: <fg=yellow>{$snapshot->checksum}</>",
                "Current schema checksum: <fg=yellow>{$currentChecksum}</>",
                "Created at: <fg=yellow>{$snapshot->createdAt->format('M jS Y g:i A')}</>",
                "Current schema files: <fg=yellow>{$fileCounts['migrations']} migrations</> and <fg=yellow>{$fileCounts['seeders']} seeders</>",
                "Snapshot schema files: <fg=yellow>{$snapshot->migrationCount} migrations</> and <fg=yellow>{$snapshot->seederCount} seeders</>",
            ]);

            return confirm(
                'The database schema or seeders have been modified since this snapshot was created. This could result in unexpected behavior. Continue anyway?',
                default: false,
            );
        }

        return true;
    }

    /**
     * Create a backup of the current database state.
     */
    private function createBackup(string $backupName): void
    {
        $result = $this->callSilently('db:snapshot:create', [
            'filename' => $backupName,
            '--skip-schema-validation' => true,
        ]);

        if ($result !== self::SUCCESS) {
            throw new RuntimeException('Failed to create backup snapshot');
        }
    }

    /**
     * Drop all tables in the database.
     */
    private function dropAllTables(): void
    {
        DB::connection(DB::getDefaultConnection())
            ->getSchemaBuilder()
            ->dropAllTables();

        DB::reconnect();
    }

    /**
     * Load the given database snapshot into the current database.
     */
    private function loadSnapshot(string $snapshotPath): void
    {
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
    }

    /**
     * Clear the permission cache after restoring the database.
     */
    private function clearPermissionCache(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
