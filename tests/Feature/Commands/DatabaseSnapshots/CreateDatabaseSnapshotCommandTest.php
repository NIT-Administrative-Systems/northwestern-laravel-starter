<?php

declare(strict_types=1);

namespace Tests\Feature\Commands\DatabaseSnapshots;

use App\Console\Commands\DatabaseSnapshots\CreateDatabaseSnapshotCommand;
use App\Domains\Core\Database\SchemaChecksumManager;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CreateDatabaseSnapshotCommand::class)]
class CreateDatabaseSnapshotCommandTest extends DatabaseSnapshotTestCase
{
    public function test_creates_snapshot_file(): void
    {
        $name = $this->uniqueSnapshotName();

        $this->artisan('db:snapshot:create', [
            'filename' => $name,
            '--skip-schema-validation' => true,
        ])->assertSuccessful();

        $this->assertFileExists(database_path("snapshots/{$name}.sql"));
    }

    public function test_creates_snapshot_with_schema_metadata(): void
    {
        $name = $this->uniqueSnapshotName();

        $this->artisan('db:snapshot:create', [
            'filename' => $name,
        ])->assertSuccessful();

        $this->assertFileExists(database_path("snapshots/{$name}.sql"));

        $manager = resolve(SchemaChecksumManager::class);
        $info = $manager->getSnapshotInfo($name);

        $this->assertNotNull($info);
        $this->assertSame($name, $info->name);
        $this->assertNotEmpty($info->checksum);
        $this->assertGreaterThan(0, $info->migrationCount);
    }

    public function test_creates_snapshot_without_metadata_when_schema_validation_skipped(): void
    {
        $name = $this->uniqueSnapshotName();

        $this->artisan('db:snapshot:create', [
            'filename' => $name,
            '--skip-schema-validation' => true,
        ])->assertSuccessful();

        $manager = resolve(SchemaChecksumManager::class);
        $this->assertNull($manager->getSnapshotInfo($name));
    }

    public function test_normalizes_snapshot_name_with_sql_extension(): void
    {
        $name = $this->uniqueSnapshotName();
        $this->testSnapshotNames[] = $name; // Already tracked by uniqueSnapshotName

        $this->artisan('db:snapshot:create', [
            'filename' => $name . '.sql',
            '--skip-schema-validation' => true,
        ])->assertSuccessful();

        $this->assertFileExists(database_path("snapshots/{$name}.sql"));
    }

    public function test_blocked_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('db:snapshot:create', [
            'filename' => 'should-not-exist',
            '--skip-schema-validation' => true,
        ])->assertFailed();

        $this->assertFileDoesNotExist(database_path('snapshots/should-not-exist.sql'));
    }
}
