<?php

declare(strict_types=1);

namespace App\Domains\Core\Database\ValueObjects;

use App\Domains\Core\Contracts\IdempotentSeederInterface;
use Illuminate\Database\Seeder;

/**
 * Value object containing metadata for a discovered seeder.
 *
 * Holds the fully qualified class name and dependency information extracted
 * from the #[AutoSeed] attribute during seeder discovery.
 *
 * @see \App\Domains\Core\Attributes\AutoSeed
 * @see \App\Domains\Core\Services\IdempotentSeederResolver
 */
readonly class SeederInfo
{
    /**
     * @param  string  $className  Fully qualified class name
     * @param  list<class-string<IdempotentSeederInterface>>  $dependsOn  Array of seeder classes that must run first
     */
    public function __construct(
        public string $className,
        public array $dependsOn = [],
    ) {
        //
    }

    /**
     * Get short class name without namespace
     */
    public function getShortName(): string
    {
        return class_basename($this->className);
    }

    /**
     * Check if this seeder has any dependencies
     */
    public function hasDependencies(): bool
    {
        return filled($this->dependsOn);
    }

    /**
     * Get short names of dependencies
     *
     * @return array<string>
     */
    public function getDependencyShortNames(): array
    {
        return array_map(class_basename(...), $this->dependsOn);
    }
}
