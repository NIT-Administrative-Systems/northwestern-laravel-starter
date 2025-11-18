<?php

declare(strict_types=1);

namespace App\Domains\Core\Database\ValueObjects;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Represents a snapshot of the database schema state at a point in time.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class SchemaSnapshot implements Arrayable
{
    public function __construct(
        public string $name,
        public string $checksum,
        public CarbonInterface $createdAt,
        public int $migrationCount,
        public int $seederCount,
    ) {
    }

    /**
     * Create a snapshot instance from array data.
     *
     * @param array{
     *     checksum: string,
     *     created_at: string,
     *     migrations: int,
     *     seeders: int
     * } $data Raw snapshot data from storage
     */
    public static function fromArray(string $name, array $data): self
    {
        return new self(
            name: $name,
            checksum: $data['checksum'],
            createdAt: now()->parse($data['created_at'])->timezone(config('app.schedule_timezone')),
            migrationCount: $data['migrations'],
            seederCount: $data['seeders']
        );
    }

    /**
     * Convert snapshot to storage format.
     *
     * @return array{
     *     checksum: string,
     *     created_at: string,
     *     migrations: int,
     *     seeders: int
     * }
     */
    public function toArray(): array
    {
        return [
            'checksum' => $this->checksum,
            'created_at' => $this->createdAt->toIso8601String(),
            'migrations' => $this->migrationCount,
            'seeders' => $this->seederCount,
        ];
    }

    /**
     * Get a human-readable description of the snapshot.
     */
    public function getDescription(): string
    {
        return sprintf(
            'Snapshot %s (created %s) with %d migrations and %d seeders',
            $this->name,
            $this->createdAt->diffForHumans(),
            $this->migrationCount,
            $this->seederCount
        );
    }
}
