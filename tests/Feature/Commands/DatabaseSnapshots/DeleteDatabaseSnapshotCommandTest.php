<?php

declare(strict_types=1);

namespace Tests\Feature\Commands\DatabaseSnapshots;

use App\Console\Commands\DatabaseSnapshots\DeleteDatabaseSnapshotCommand;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DeleteDatabaseSnapshotCommand::class)]
class DeleteDatabaseSnapshotCommandTest extends DatabaseSnapshotTestCase
{
    public function test_deletes_snapshot_file_and_metadata(): void
    {
        $name = $this->uniqueSnapshotName();
        $this->createTestSnapshot($name);

        $this->assertFileExists(database_path("snapshots/{$name}.sql"));

        $this->artisan('db:snapshot:delete', [
            'filename' => $name,
        ])
            ->expectsConfirmation("Delete snapshot '{$name}'?", 'yes')
            ->assertSuccessful();

        $this->assertFileDoesNotExist(database_path("snapshots/{$name}.sql"));
    }

    public function test_delete_cancelled_when_not_confirmed(): void
    {
        $name = $this->uniqueSnapshotName();
        $this->createTestSnapshot($name);

        $this->artisan('db:snapshot:delete', [
            'filename' => $name,
        ])
            ->expectsConfirmation("Delete snapshot '{$name}'?", 'no')
            ->assertSuccessful();

        // File should still exist since the user cancelled.
        $this->assertFileExists(database_path("snapshots/{$name}.sql"));
    }

    public function test_fails_when_no_snapshots_exist(): void
    {
        $this->artisan('db:snapshot:delete', [
            'filename' => 'nonexistent',
        ])->assertFailed();
    }

    public function test_blocked_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('db:snapshot:delete', [
            'filename' => 'any-snapshot',
        ])->assertFailed();
    }
}
