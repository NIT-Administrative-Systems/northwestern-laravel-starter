<?php

declare(strict_types=1);

namespace App\Domains\Core\Database\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Represents a collection of database schema files including migrations and seeders.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class SchemaFileCollection implements Arrayable
{
    /**
     * @param  array<int, string>  $migrations  Absolute paths to migration files
     * @param  array<int, string>  $seeders  Absolute paths to seeder files
     */
    public function __construct(
        public array $migrations,
        public array $seeders,
    ) {
    }

    /**
     * Get all schema files combined.
     *
     * @return array<int, string>
     */
    public function all(): array
    {
        return [...$this->migrations, ...$this->seeders];
    }

    /**
     * Get counts of schema files.
     *
     * @return array{migrations: int, seeders: int, total: int}
     */
    public function counts(): array
    {
        return [
            'migrations' => count($this->migrations),
            'seeders' => count($this->seeders),
            'total' => count($this->all()),
        ];
    }

    /**
     * Convert to array format.
     *
     * @return array{
     *     migrations: array<int, string>,
     *     seeders: array<int, string>,
     *     all: array<int, string>
     * }
     */
    public function toArray(): array
    {
        return [
            'migrations' => $this->migrations,
            'seeders' => $this->seeders,
            'all' => $this->all(),
        ];
    }
}
