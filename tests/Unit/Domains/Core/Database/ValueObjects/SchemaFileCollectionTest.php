<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Core\Database\ValueObjects;

use App\Domains\Core\Database\ValueObjects\SchemaFileCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaFileCollection::class)]
class SchemaFileCollectionTest extends TestCase
{
    public function test_all_combines_migrations_and_seeders(): void
    {
        $collection = new SchemaFileCollection(
            migrations: ['/path/migration_1.php', '/path/migration_2.php'],
            seeders: ['/path/seeder_1.php'],
        );

        $this->assertSame([
            '/path/migration_1.php',
            '/path/migration_2.php',
            '/path/seeder_1.php',
        ], $collection->all());
    }

    public function test_all_returns_empty_array_when_no_files(): void
    {
        $collection = new SchemaFileCollection(migrations: [], seeders: []);

        $this->assertSame([], $collection->all());
    }

    public function test_counts_returns_correct_totals(): void
    {
        $collection = new SchemaFileCollection(
            migrations: ['m1.php', 'm2.php', 'm3.php'],
            seeders: ['s1.php', 's2.php'],
        );

        $this->assertSame([
            'migrations' => 3,
            'seeders' => 2,
            'total' => 5,
        ], $collection->counts());
    }

    public function test_counts_returns_zeros_when_empty(): void
    {
        $collection = new SchemaFileCollection(migrations: [], seeders: []);

        $this->assertSame([
            'migrations' => 0,
            'seeders' => 0,
            'total' => 0,
        ], $collection->counts());
    }

    public function test_to_array_includes_all_keys(): void
    {
        $collection = new SchemaFileCollection(
            migrations: ['m1.php'],
            seeders: ['s1.php'],
        );

        $result = $collection->toArray();

        $this->assertArrayHasKey('migrations', $result);
        $this->assertArrayHasKey('seeders', $result);
        $this->assertArrayHasKey('all', $result);
        $this->assertSame(['m1.php'], $result['migrations']);
        $this->assertSame(['s1.php'], $result['seeders']);
        $this->assertSame(['m1.php', 's1.php'], $result['all']);
    }
}
