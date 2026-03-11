<?php

declare(strict_types=1);

namespace Tests\Feature\Commands\DatabaseSnapshots;

use App\Console\Commands\DatabaseSnapshots\RestoreDatabaseSnapshotCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RestoreDatabaseSnapshotCommand::class)]
class RestoreDatabaseSnapshotCommandTest extends DatabaseSnapshotTestCase
{
    public function test_restores_snapshot_successfully(): void
    {
        $name = $this->uniqueSnapshotName();

        // Verify seed data exists before snapshot.
        $this->assertSame(2, DB::connection('snapshot-testing')->table('snapshot_test_users')->count());

        // Create a snapshot of the current state (2 users, 1 post).
        $this->createTestSnapshot($name);

        // Insert an extra row that should NOT exist after restore.
        DB::connection('snapshot-testing')->table('snapshot_test_users')->insert([
            'name' => 'Eve', 'email' => 'eve@example.com', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertSame(3, DB::connection('snapshot-testing')->table('snapshot_test_users')->count());

        // Restore the snapshot.
        $this->artisan('db:snapshot:restore', [
            'filename' => $name,
            '--force' => true,
            '--skip-schema-validation' => true,
        ])->assertSuccessful();

        // Tables should be back.
        $this->assertTrue(Schema::connection('snapshot-testing')->hasTable('snapshot_test_users'));
        $this->assertTrue(Schema::connection('snapshot-testing')->hasTable('snapshot_test_posts'));

        // The extra row should be gone — data is from the snapshot (2 users).
        $this->assertSame(2, DB::connection('snapshot-testing')->table('snapshot_test_users')->count());
    }

    public function test_restore_fails_when_file_not_found(): void
    {
        $this->artisan('db:snapshot:restore', [
            'filename' => 'nonexistent-snapshot',
            '--force' => true,
            '--skip-schema-validation' => true,
        ])->assertFailed();
    }

    public function test_blocked_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('db:snapshot:restore', [
            'filename' => 'any-snapshot',
            '--force' => true,
            '--skip-schema-validation' => true,
        ])->assertFailed();
    }

    public function test_restore_with_backup_creates_backup_file(): void
    {
        $name = $this->uniqueSnapshotName();
        $this->createTestSnapshot($name);

        // Restore with --backup flag.
        $this->artisan('db:snapshot:restore', [
            'filename' => $name,
            '--force' => true,
            '--skip-schema-validation' => true,
            '--backup' => true,
        ])->assertSuccessful();

        // Find and track the backup file for cleanup.
        $backupFiles = glob(database_path("snapshots/{$name}-pre-restore-*.sql"));
        $this->assertNotEmpty($backupFiles, 'A backup snapshot file should have been created');

        foreach ($backupFiles as $backupFile) {
            $this->testSnapshotNames[] = pathinfo($backupFile, PATHINFO_FILENAME);
        }
    }
}
