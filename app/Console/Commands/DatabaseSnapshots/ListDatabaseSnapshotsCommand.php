<?php

declare(strict_types=1);

namespace App\Console\Commands\DatabaseSnapshots;

use App\Domains\Core\Database\SchemaChecksumManager;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

/**
 * Lists all available database snapshots and optionally restores one.
 */
class ListDatabaseSnapshotsCommand extends DatabaseSnapshotCommand
{
    protected $signature = 'db:snapshot:list';

    protected $description = 'List and restore database snapshots';

    public function __construct(
        private readonly SchemaChecksumManager $schemaManager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $snapshots = $this->schemaManager->getSnapshots();

        $this->newLine();
        $this->components->info('Available Database Snapshots');

        if ($snapshots->isEmpty()) {
            $this->newLine();
            $this->components->warn('No database snapshots found.');
            $this->newLine();
            $this->line('  <fg=gray>→</> Create a snapshot with: <comment>php artisan db:snapshot:create</comment>');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->newLine();
        $this->displayAllSnapshotsTable($snapshots);
        $this->newLine();

        if (! confirm('Would you like to restore a snapshot?', default: false)) {
            return self::SUCCESS;
        }

        return $this->presentAvailableSnapshotsForRestoration();
    }

    /**
     * Present the user with an interactive selection of available snapshots for restoration.
     */
    private function presentAvailableSnapshotsForRestoration(): int
    {
        $selectedName = select(
            label: 'Select a snapshot to restore',
            options: ['cancel' => 'Cancel'] + $this->buildSnapshotSelectOptions($this->schemaManager->getSnapshots()),
            default: 'cancel'
        );

        if ($selectedName === 'cancel') {
            return self::SUCCESS;
        }

        $this->call('db:snapshot:restore', [
            'filename' => $selectedName,
            '--skip-schema-validation' => true,
        ]);

        return self::SUCCESS;
    }
}
