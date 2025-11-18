<?php

declare(strict_types=1);

namespace App\Domains\Core\Services;

use App\Domains\Core\Attributes\AutoSeed;
use App\Domains\Core\Contracts\IdempotentSeederInterface;
use App\Domains\Core\Database\ValueObjects\SeederInfo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use RuntimeException;
use SplFileInfo;

/**
 * Discovers and resolves idempotent seeders in dependency order.
 *
 * Automatically scans directories for seeders decorated with #[AutoSeed], validates
 * their dependencies, and returns them in topologically sorted execution order using
 * depth-first search. Detects circular dependencies and missing references.
 */
final class IdempotentSeederResolver
{
    /**
     * @var array<class-string<IdempotentSeederInterface>, SeederInfo>
     */
    private array $seeders = [];

    /**
     * @var array<class-string<IdempotentSeederInterface>>
     */
    private array $resolved = [];

    /**
     * @var array<class-string<IdempotentSeederInterface>>
     */
    private array $resolving = [];

    /**
     * Discover and resolve seeders from the given path(s).
     *
     * Supports glob patterns like 'app/Domains/*\/Seeders' to scan multiple directories.
     *
     * @param  string|array<string>|null  $paths  Directory path(s) or glob pattern(s) to scan
     * @return array<SeederInfo> Seeders in dependency-resolved order
     */
    public function discover(string|array|null $paths = null): array
    {
        $paths ??= app_path('Domains/**/Seeders');
        $paths = is_array($paths) ? $paths : [$paths];

        $this->seeders = [];
        $this->resolved = [];
        $this->resolving = [];

        $discoveredPaths = $this->expandGlobPatterns($paths);

        if ($discoveredPaths->isEmpty()) {
            return [];
        }

        $seederClasses = $discoveredPaths
            ->flatMap(fn (string $path): Collection => $this->scanDirectory($path))
            ->unique()
            ->values();

        $this->buildSeederRegistry($seederClasses);

        return $this->topologicalSort();
    }

    /**
     * Validate all discovered seeders for circular dependencies and missing references.
     *
     * @return array<string> Array of validation error messages (empty if valid)
     */
    public function validate(): array
    {
        $errors = [];

        try {
            $this->topologicalSort();
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }

        foreach ($this->seeders as $seederInfo) {
            foreach ($seederInfo->dependsOn as $dependency) {
                if (! isset($this->seeders[$dependency]) && ! class_exists($dependency)) {
                    $errors[] = sprintf(
                        "Seeder '%s' depends on '%s' which does not exist or is missing #[AutoSeed] attribute",
                        $seederInfo->className,
                        $dependency
                    );
                }
            }
        }

        return $errors;
    }

    /**
     * Expand glob patterns and filter to existing directories.
     *
     * @param  array<string>  $patterns
     * @return Collection<int, string>
     */
    private function expandGlobPatterns(array $patterns): Collection
    {
        return collect($patterns)
            ->flatMap(function (string $pattern): array {
                if (str_contains($pattern, '*')) {
                    return glob($pattern, GLOB_ONLYDIR) ?: [];
                }

                return [$pattern];
            })
            ->filter(fn (string $path): bool => is_dir($path))
            ->unique()
            ->values();
    }

    /**
     * Scan a directory for seeder class files.
     *
     * @return Collection<int, class-string<IdempotentSeederInterface>>
     */
    private function scanDirectory(string $path): Collection
    {
        if (! is_dir($path)) {
            return collect();
        }

        return collect(File::allFiles($path))
            ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
            ->map(fn (SplFileInfo $file): ?string => $this->extractFullyQualifiedClassName($file))
            ->filter(fn (?string $class): bool => $class !== null && $this->isValidSeederClass($class))
            ->values();
    }

    /**
     * Extract the fully qualified class name from a PHP file.
     *
     * @return class-string<IdempotentSeederInterface>|null
     */
    private function extractFullyQualifiedClassName(SplFileInfo $file): ?string
    {
        $namespace = $this->parseNamespaceFromFile($file->getRealPath());

        if ($namespace === null) {
            return null;
        }

        $className = pathinfo($file->getFilename(), PATHINFO_FILENAME);

        return sprintf('%s\\%s', $namespace, $className);
    }

    /**
     * Parse the namespace declaration from a PHP file.
     */
    private function parseNamespaceFromFile(string $filePath): ?string
    {
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            return null;
        }

        $namespace = null;

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);

            if (str_starts_with($line, 'namespace ')) {
                $namespace = trim(substr($line, 10), '; ');
                break;
            }

            // Stop parsing after class declaration to avoid reading an entire file
            if (str_starts_with($line, 'class ') || str_starts_with($line, 'final class ')) {
                break;
            }
        }

        fclose($handle);

        return $namespace;
    }

    /**
     * Check if a class is a valid, instantiable seeder.
     *
     * @param  class-string  $className
     */
    private function isValidSeederClass(string $className): bool
    {
        if (! class_exists($className)) {
            return false;
        }

        $reflection = new ReflectionClass($className);

        return $reflection->isSubclassOf(IdempotentSeederInterface::class)
            && ! $reflection->isAbstract();
    }

    /**
     * Build the internal registry of seeders with their metadata.
     *
     * @param  Collection<int, class-string<IdempotentSeederInterface>>  $seederClasses
     */
    private function buildSeederRegistry(Collection $seederClasses): void
    {
        foreach ($seederClasses as $className) {
            $seederInfo = $this->extractSeederMetadata($className);

            if ($seederInfo !== null) {
                $this->seeders[$className] = $seederInfo;
            }
        }
    }

    /**
     * Extract seeder metadata from the AutoSeed attribute.
     *
     * @param  class-string<IdempotentSeederInterface>  $className
     */
    private function extractSeederMetadata(string $className): ?SeederInfo
    {
        $reflection = new ReflectionClass($className);

        if ($reflection->isAbstract()) {
            return null;
        }

        $attributes = $reflection->getAttributes(AutoSeed::class);

        if (blank($attributes)) {
            return null;
        }

        /** @var AutoSeed $attribute */
        $attribute = $attributes[0]->newInstance();

        return new SeederInfo(
            className: $className,
            dependsOn: $attribute->dependsOn,
        );
    }

    /**
     * Sorts seeders using topological sorting to ensure dependencies run before dependents.
     *
     * **Why This is Needed:**
     * Seeders often have dependencies on each other. For example:
     * - PermissionSeeder creates permissions
     * - RoleSeeder depends on PermissionSeeder (needs permissions to assign to roles)
     * - UserSeeder depends on RoleSeeder (needs roles to assign to users)
     *
     * Without topological sorting, seeders might run in the wrong order and fail because
     * their dependencies haven't been seeded yet. For example, if UserSeeder runs before
     * RoleSeeder, it would fail trying to assign non-existent roles.
     *
     * **Circular Dependency Detection:**
     * If seeders have circular dependencies (A → B → C → A), no valid ordering exists.
     * The algorithm detects this during traversal and throws an exception with a
     * helpful message showing the circular chain.
     *
     * @return array<SeederInfo> Seeders ordered such that all dependencies run first
     *
     * @throws RuntimeException If circular dependencies are detected
     *
     * @see visitNode() for the DFS implementation
     */
    private function topologicalSort(): array
    {
        /** @var array<SeederInfo> $ordered */
        $ordered = [];

        foreach (array_keys($this->seeders) as $seederClass) {
            $this->visitNode($seederClass, $ordered);
        }

        return $ordered;
    }

    /**
     * Recursively visits a seeder node using depth-first search for topological sorting.
     *
     * **State Tracking:**
     * The algorithm maintains two state arrays:
     * - `$this->resolved`: Seeders that have been fully processed (node and all dependencies)
     * - `$this->resolving`: Seeders currently being processed (on the call stack)
     *
     * **Dependency-First Ordering:**
     * By recursively visiting all dependencies before adding the current seeder to `$ordered`,
     * we ensure dependencies always appear earlier in the final list. This is the key insight
     * of topological sorting via DFS.
     *
     * @param  class-string<IdempotentSeederInterface>  $seederClass  The seeder to visit
     * @param  array<SeederInfo>  $ordered  The output array being built (passed by reference)
     *
     * @throws RuntimeException If circular dependency or missing seeder is detected
     *
     * @see topologicalSort() for the main sorting algorithm
     */
    private function visitNode(string $seederClass, array &$ordered): void
    {
        // Already processed - skip
        if (in_array($seederClass, $this->resolved, true)) {
            return;
        }

        // Currently processing - circular dependency detected
        if (in_array($seederClass, $this->resolving, true)) {
            throw new RuntimeException(sprintf(
                'Circular dependency detected: %s',
                implode(' → ', [...$this->resolving, $seederClass])
            ));
        }

        if (! isset($this->seeders[$seederClass])) {
            throw new RuntimeException(sprintf(
                "Seeder '%s' is declared as a dependency but was not discovered or is missing the #[AutoSeed] attribute",
                $seederClass
            ));
        }

        $this->resolving[] = $seederClass;
        $seederInfo = $this->seeders[$seederClass];

        // Recursively visit all dependencies first
        foreach ($seederInfo->dependsOn as $dependency) {
            $this->visitNode($dependency, $ordered);
        }

        array_pop($this->resolving);
        $this->resolved[] = $seederClass;
        $ordered[] = $seederInfo;
    }
}
