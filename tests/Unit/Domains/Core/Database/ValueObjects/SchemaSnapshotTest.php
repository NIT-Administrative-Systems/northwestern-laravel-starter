<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Core\Database\ValueObjects;

use App\Domains\Core\Database\ValueObjects\SchemaSnapshot;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaSnapshot::class)]
class SchemaSnapshotTest extends TestCase
{
    public function test_constructor_sets_readonly_properties(): void
    {
        $createdAt = Carbon::parse('2026-01-15 10:30:00');

        $snapshot = new SchemaSnapshot(
            name: 'test_snapshot',
            checksum: 'abc123',
            createdAt: $createdAt,
            migrationCount: 5,
            seederCount: 3,
        );

        $this->assertSame('test_snapshot', $snapshot->name);
        $this->assertSame('abc123', $snapshot->checksum);
        $this->assertTrue($createdAt->equalTo($snapshot->createdAt));
        $this->assertSame(5, $snapshot->migrationCount);
        $this->assertSame(3, $snapshot->seederCount);
    }

    public function test_to_array_returns_storage_format(): void
    {
        $createdAt = Carbon::parse('2026-01-15T10:30:00+00:00');

        $snapshot = new SchemaSnapshot(
            name: 'test_snapshot',
            checksum: 'abc123',
            createdAt: $createdAt,
            migrationCount: 5,
            seederCount: 3,
        );

        $result = $snapshot->toArray();

        $this->assertSame('abc123', $result['checksum']);
        $this->assertSame($createdAt->toIso8601String(), $result['created_at']);
        $this->assertSame(5, $result['migrations']);
        $this->assertSame(3, $result['seeders']);
        $this->assertArrayNotHasKey('name', $result);
    }

    public function test_get_description_contains_snapshot_info(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-25 12:00:00'));

        $snapshot = new SchemaSnapshot(
            name: 'my_snapshot',
            checksum: 'abc123',
            createdAt: Carbon::parse('2026-02-24 12:00:00'),
            migrationCount: 10,
            seederCount: 4,
        );

        $description = $snapshot->getDescription();

        $this->assertStringContainsString('my_snapshot', $description);
        $this->assertStringContainsString('10 migrations', $description);
        $this->assertStringContainsString('4 seeders', $description);

        Carbon::setTestNow();
    }
}
