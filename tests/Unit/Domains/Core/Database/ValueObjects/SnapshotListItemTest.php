<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Core\Database\ValueObjects;

use App\Domains\Core\Database\ValueObjects\SnapshotListItem;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SnapshotListItem::class)]
class SnapshotListItemTest extends TestCase
{
    public function test_to_array_returns_expected_structure(): void
    {
        $createdAt = Carbon::parse('2026-01-15 10:30:00');

        $item = new SnapshotListItem(
            name: 'snapshot_2026_01_15.sql',
            size: 1024,
            createdAt: $createdAt,
        );

        $result = $item->toArray();

        $this->assertSame('snapshot_2026_01_15.sql', $result['name']);
        $this->assertSame(1024, $result['size']);
        $this->assertSame((int) $createdAt->timestamp, $result['modified']);
    }

    public function test_to_array_modified_is_integer_timestamp(): void
    {
        $item = new SnapshotListItem(
            name: 'test.sql',
            size: 0,
            createdAt: Carbon::parse('2026-06-15 00:00:00'),
        );

        $this->assertIsInt($item->toArray()['modified']);
    }
}
