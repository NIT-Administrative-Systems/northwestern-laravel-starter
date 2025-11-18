<?php

declare(strict_types=1);

namespace App\Domains\Core\Database\ValueObjects;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class SnapshotListItem implements Arrayable
{
    public function __construct(
        public string $name,
        public int $size,
        public CarbonInterface $createdAt,
    ) {
    }

    /**
     * @return array{
     *     name: string,
     *     size: int,
     *     modified: int
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'size' => $this->size,
            'modified' => (int) $this->createdAt->timestamp,
        ];
    }
}
