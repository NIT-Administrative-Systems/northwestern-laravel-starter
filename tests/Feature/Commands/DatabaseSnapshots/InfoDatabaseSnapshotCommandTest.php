<?php

declare(strict_types=1);

namespace Tests\Feature\Commands\DatabaseSnapshots;

use App\Console\Commands\DatabaseSnapshots\InfoDatabaseSnapshotCommand;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InfoDatabaseSnapshotCommand::class)]
class InfoDatabaseSnapshotCommandTest extends DatabaseSnapshotTestCase
{
    public function test_shows_snapshot_details(): void
    {
        $name = $this->uniqueSnapshotName();
        $this->createTestSnapshot($name);

        $this->artisan('db:snapshot:info', [
            'filename' => $name,
        ])->assertSuccessful();
    }

    public function test_fails_when_no_snapshots_exist(): void
    {
        $this->artisan('db:snapshot:info', [
            'filename' => 'nonexistent',
        ])->assertFailed();
    }
}
