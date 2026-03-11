<?php

declare(strict_types=1);

namespace Tests\Feature\Commands\DatabaseSnapshots;

use App\Console\Commands\DatabaseSnapshots\ListDatabaseSnapshotsCommand;
use App\Domains\Core\Database\SchemaChecksumManager;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ListDatabaseSnapshotsCommand::class)]
class ListDatabaseSnapshotsCommandTest extends DatabaseSnapshotTestCase
{
    public function test_shows_no_snapshots_message_when_empty(): void
    {
        // Mock the manager to return an empty collection, since the real
        // database/snapshots/ directory may contain files from other tests.
        $manager = $this->mock(SchemaChecksumManager::class);
        $manager->shouldReceive('getSnapshots')->once()->andReturn(new Collection());

        $this->artisan('db:snapshot:list')
            ->expectsOutputToContain('No database snapshots found')
            ->assertSuccessful();
    }

    public function test_lists_available_snapshots(): void
    {
        $name = $this->uniqueSnapshotName();
        $this->createTestSnapshot($name);

        $this->artisan('db:snapshot:list')
            ->expectsConfirmation('Would you like to restore a snapshot?', 'no')
            ->assertSuccessful();
    }
}
