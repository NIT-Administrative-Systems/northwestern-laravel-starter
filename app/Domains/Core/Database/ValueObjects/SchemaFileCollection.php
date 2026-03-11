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
     * @param  list<non-empty-string>  $migrations  Absolute paths to migration files
     * @param  list<non-empty-string>  $seeders  Absolute paths to seeder files
     */
    public function __construct(
        public array $migrations,
        public array $seeders,
    ) {
    }

    /**
     * Get all schema files combined.
     *
     * @return list<non-empty-string>
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
     *     migrations: list<non-empty-string>,
     *     seeders: list<non-empty-string>,
     *     all: list<non-empty-string>
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
