<?php

declare(strict_types=1);

namespace Tests\Feature\Commands\DatabaseSnapshots;

use App\Domains\Core\Database\SchemaChecksumManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Base test case for database snapshot command tests.
 *
 * Uses an isolated SQLite database to avoid interfering with other tests
 * that use RefreshDatabase on the main phpunit connection. A minimal schema
 * is created directly (not via migrate:fresh) to keep tests fast and avoid
 * cross-connection migration issues.
 */
abstract class DatabaseSnapshotTestCase extends BaseTestCase
{
    /** @var list<string> Snapshot names created during the test, cleaned up in tearDown. */
    protected array $testSnapshotNames = [];

    private string $sqlitePath;

    private string $originalDefault;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Save the original default connection.
        $this->originalDefault = config('database.default');

        // Create an isolated SQLite database file.
        $this->sqlitePath = database_path('snapshot-test-' . uniqid() . '.sqlite');
        touch($this->sqlitePath);

        // Register and activate the snapshot-testing connection.
        config([
            'database.connections.snapshot-testing' => [
                'driver' => 'sqlite',
                'database' => $this->sqlitePath,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'database.default' => 'snapshot-testing',
        ]);

        // Purge any cached connection so the new default takes effect.
        DB::purge('snapshot-testing');

        // Create a minimal schema for snapshot testing (avoids running full app migrations).
        $this->createMinimalSchema();

        // Ensure the snapshots directory exists for dump output.
        File::ensureDirectoryExists(database_path('snapshots'));
    }

    protected function tearDown(): void
    {
        // Restore original default connection.
        config(['database.default' => $this->originalDefault]);
        DB::purge('snapshot-testing');

        // Clean up snapshot files and metadata created during the test.
        $manager = resolve(SchemaChecksumManager::class);

        foreach ($this->testSnapshotNames as $name) {
            $path = database_path("snapshots/{$name}.sql");

            if (File::exists($path)) {
                File::delete($path);
            }

            $manager->removeSnapshotMetadata($name);
        }

        // Clean up the isolated SQLite file.
        if (file_exists($this->sqlitePath)) {
            @unlink($this->sqlitePath);
        }

        parent::tearDown();
    }

    /**
     * Generate a unique snapshot name and register it for cleanup.
     */
    protected function uniqueSnapshotName(string $prefix = 'test-snapshot'): string
    {
        $name = $prefix . '-' . uniqid();
        $this->testSnapshotNames[] = $name;

        return $name;
    }

    /**
     * Create a snapshot using the artisan command (helper for tests that need a pre-existing snapshot).
     */
    protected function createTestSnapshot(string $name): void
    {
        $this->artisan('db:snapshot:create', [
            'filename' => $name,
            '--skip-schema-validation' => true,
        ])->assertSuccessful();
    }

    /**
     * Create a minimal database schema for snapshot round-trip testing.
     *
     * This avoids running the full application migration set (which can
     * interfere with the main phpunit connection) while still providing
     * real tables for pg_dump/mysqldump/sqlite3 to dump and restore.
     */
    private function createMinimalSchema(): void
    {
        $schema = Schema::connection('snapshot-testing');

        $schema->create('snapshot_test_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        $schema->create('snapshot_test_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('snapshot_test_users')->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamps();
        });

        // Insert seed data so the dump includes INSERT statements.
        DB::connection('snapshot-testing')->table('snapshot_test_users')->insert([
            ['name' => 'Alice', 'email' => 'alice@example.com', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Bob', 'email' => 'bob@example.com', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::connection('snapshot-testing')->table('snapshot_test_posts')->insert([
            ['user_id' => 1, 'title' => 'First Post', 'body' => 'Hello world', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
