<?php

declare(strict_types=1);

namespace App\Domains\Core\Services;

use App\Domains\Core\Attributes\StarterValidator;
use App\Domains\Core\Contracts\ConfigValidator;
use App\Domains\Core\ValueObjects\ResolvedValidator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use SplFileInfo;

/**
 * Discovers config validators decorated with #[StarterValidator].
 *
 * Scans directories for classes implementing {@see ConfigValidator} with the
 * {@see StarterValidator} attribute, and returns them as {@see ResolvedValidator} instances.
 *
 * This follows the same auto-discovery pattern as {@see IdempotentSeederResolver}
 * for seeders with #[AutoSeed].
 */
class ConfigValidatorResolver
{
    /**
     * Discover validators from the given path(s).
     *
     * Supports glob patterns like 'app/Domains/*\/Services/ConfigValidation' to scan multiple directories.
     *
     * @param  string|array<string>|null  $paths  Directory path(s) or glob pattern(s) to scan
     * @return list<ResolvedValidator>
     */
    public function discover(string|array|null $paths = null): array
    {
        $paths ??= app_path('Domains/**/Services/ConfigValidation');
        $paths = is_array($paths) ? $paths : [$paths];

        $discoveredPaths = $this->expandGlobPatterns($paths);

        if ($discoveredPaths->isEmpty()) {
            return [];
        }

        return $discoveredPaths
            ->flatMap(fn (string $path): Collection => $this->scanDirectory($path))
            ->unique(fn (array $item): string => $item['class'])
            ->sortBy('description')
            ->map(fn (array $item): ResolvedValidator => new ResolvedValidator(
                validator: resolve($item['class']),
                description: $item['description'],
            ))
            ->values()
            ->all();
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
     * Scan a directory for validator classes with the StarterValidator attribute.
     *
     * @return Collection<int, array{class: class-string<ConfigValidator>, description: string}>
     */
    private function scanDirectory(string $path): Collection
    {
        return collect(File::allFiles($path))
            ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
            ->map(fn (SplFileInfo $file): ?array => $this->extractValidatorMetadata($file))
            ->filter();
    }

    /**
     * Extract validator metadata from a PHP file if it has the StarterValidator attribute.
     *
     * @return array{class: class-string<ConfigValidator>, description: string}|null
     */
    private function extractValidatorMetadata(SplFileInfo $file): ?array
    {
        $className = $this->extractFullyQualifiedClassName($file);

        if ($className === null || ! class_exists($className)) {
            return null;
        }

        $reflection = new ReflectionClass($className);

        if ($reflection->isAbstract() || ! $reflection->implementsInterface(ConfigValidator::class)) {
            return null;
        }

        $attributes = $reflection->getAttributes(StarterValidator::class);

        if ($attributes === []) {
            return null;
        }

        /** @var StarterValidator $attribute */
        $attribute = $attributes[0]->newInstance();

        return [
            'class' => $className,
            'description' => $attribute->description,
        ];
    }

    /**
     * Extract the fully qualified class name from a PHP file.
     *
     * @return class-string|null
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
        $handle = @fopen($filePath, 'rb');

        if ($handle === false) {
            return null; // @codeCoverageIgnore
        }

        $namespace = null;

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);

            if (str_starts_with($line, 'namespace ')) {
                $namespace = trim(substr($line, 10), '; ');
                break;
            }

            if (str_starts_with($line, 'class ') || str_starts_with($line, 'final class ')) {
                break;
            }
        }

        fclose($handle);

        return $namespace;
    }
}
