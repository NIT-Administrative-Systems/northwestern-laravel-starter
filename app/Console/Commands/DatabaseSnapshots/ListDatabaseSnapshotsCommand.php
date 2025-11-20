<?php

declare(strict_types=1);

namespace App\Console\Commands\DatabaseSnapshots;

use App\Domains\Core\Database\SchemaChecksumManager;
use App\Domains\Core\Database\ValueObjects\SnapshotListItem;
use Illuminate\Support\Collection;
use Spatie\DbSnapshots\Helpers\Format;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

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

        if ($snapshots->isEmpty()) {
            $this->components->error('No database snapshots found.');

            return self::FAILURE;
        }

        $this->displayAllSnapshotsTable($snapshots);

        if (! confirm('Would you like to restore a snapshot?')) {
            return self::SUCCESS;
        }

        return $this->presentAvailableSnapshotsForRestoration($snapshots);
    }

    /**
     * Present the user with an interactive selection of available {@see SnapshotListItem}s for restoration.
     *
     * @param  Collection<int, SnapshotListItem>  $snapshots
     */
    private function presentAvailableSnapshotsForRestoration(Collection $snapshots): int
    {
        $choices = $snapshots->mapWithKeys(function (SnapshotListItem $snapshot): array {
            $label = sprintf(
                '%s (%s, %s)',
                $snapshot->name,
                Format::humanReadableSize($snapshot->size),
                $snapshot->createdAt->diffForHumans(),
            );

            return [$snapshot->name => $label];
        })->toArray();

        $selectedName = select(
            label: 'Select a snapshot to restore',
            options: ['cancel' => 'Cancel'] + $choices,
            default: 'cancel'
        );

        if ($selectedName === 'cancel') {
            return self::SUCCESS;
        }

        if (! confirm("Are you sure you want to restore snapshot '{$selectedName}'?")) {
            return self::SUCCESS;
        }

        $this->call('db:snapshot:restore', [
            'filename' => $selectedName,
            '--force' => true,
        ]);

        return self::SUCCESS;
    }
}
