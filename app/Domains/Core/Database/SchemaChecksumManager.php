<?php

declare(strict_types=1);

namespace App\Domains\Core\Database;

use App\Domains\Core\Database\ValueObjects\SchemaFileCollection;
use App\Domains\Core\Database\ValueObjects\SchemaSnapshot;
use App\Domains\Core\Database\ValueObjects\SnapshotListItem;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;
use Symfony\Component\Finder\Finder;

/**
 * Manages database schema checksums and snapshot metadata.
 *
 * This class provides functionality to track and validate database schema changes
 * by maintaining checksums of migration and seeder files.
 */
class SchemaChecksumManager
{
    private const string SCHEMA_METADATA_FILE = 'snapshot-checksums.json';

    private const string HASH_ALGORITHM = 'xxh128';

    /**
     * Calculate the current schema checksum based on migration and seeder files.
     *
     * @throws RuntimeException If unable to read or hash schema files
     */
    public function calculateCurrentCodebaseChecksum(): string
    {
        return $this->calculateFileChecksum($this->collectSchemaFiles());
    }

    /**
     * Get schema files collection for migrations and seeders.
     */
    public function collectSchemaFiles(): SchemaFileCollection
    {
        $migrations = File::glob(database_path('migrations/*.php')) ?: [];

        $seederFinder = new Finder()
            ->files()
            ->in(base_path())
            ->path('/[sS]eeders/')
            ->name('*.php')
            ->notPath('vendor');

        $seeders = array_map(
            fn ($file) => $file->getRealPath(),
            iterator_to_array($seederFinder, false)
        );

        return new SchemaFileCollection(
            migrations: $migrations,
            seeders: $seeders,
        );
    }

    /**
     * Calculate a checksum based the provided migration and seeder files.
     *
     * @throws RuntimeException If unable to read or hash files
     */
    private function calculateFileChecksum(SchemaFileCollection $files): string
    {
        try {
            $checksumData = collect($files->all())
                ->map(function (string $file): array {
                    if (! is_readable($file)) {
                        throw new RuntimeException("Unable to read file for checksum: {$file}");
                    }

                    $hash = hash_file(self::HASH_ALGORITHM, $file);
                    if ($hash === false) {
                        throw new RuntimeException("Failed to calculate hash for file: {$file}");
                    }

                    return [
                        'path' => $file,
                        'content' => $hash,
                    ];
                })
                ->sortBy('path')
                ->toJson();

            return hash(self::HASH_ALGORITHM, $checksumData);
        } catch (Exception $e) {
            Log::error('Failed to calculate schema checksum', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Failed to calculate schema checksum: ' . $e->getMessage(), $e->getCode(), previous: $e);
        }
    }

    /**
     * Update the checksum map with a new snapshot entry.
     *
     * @throws RuntimeException If file operations fail
     */
    public function saveSnapshot(string $snapshotName, SchemaSnapshot $snapshot): void
    {
        $mapPath = $this->getMetadataPath();
        $directory = dirname($mapPath);

        if (! File::exists($directory) && ! File::makeDirectory($directory, recursive: true)) {
            throw new RuntimeException("Unable to create directory: {$directory}");
        }

        try {
            // Load existing map or create new
            $checksumMap = File::exists($mapPath)
                ? json_decode(File::get($mapPath), true, 512, JSON_THROW_ON_ERROR)
                : [];

            // Update map with new snapshot info
            $checksumMap[$snapshotName] = $snapshot->toArray();

            // Attempt to write the updated map
            if (! File::put(
                $mapPath,
                json_encode($checksumMap, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
            )) {
                throw new RuntimeException("Failed to write metadata to: {$mapPath}");
            }
        } catch (JsonException $e) {
            throw new RuntimeException("Failed to process snapshot metadata: {$e->getMessage()}", $e->getCode(), previous: $e);
        }
    }

    /**
     * Get snapshot info from the checksum map.
     *
     * @throws JsonException When JSON decoding fails
     */
    public function getSnapshotInfo(string $snapshotName): ?SchemaSnapshot
    {
        $mapPath = $this->getMetadataPath();

        if (! File::exists($mapPath)) {
            return null;
        }

        try {
            $checksumMap = json_decode(File::get($mapPath), true, 512, JSON_THROW_ON_ERROR);
            $snapshotData = $checksumMap[$snapshotName] ?? null;

            return $snapshotData ? SchemaSnapshot::fromArray($snapshotName, $snapshotData) : null;
        } catch (JsonException $e) {
            Log::error('Failed to read snapshot metadata', [
                'error' => $e->getMessage(),
                'snapshot' => $snapshotName,
            ]);

            throw $e;
        }
    }

    /**
     * Get a list of all available snapshots.
     *
     * @return Collection<int, SnapshotListItem>
     */
    public function getSnapshots(): Collection
    {
        return collect(File::glob(database_path('snapshots/*.sql')))
            ->map(fn (string $path): string => basename($path, '.sql'))
            ->map(function (string $name): SnapshotListItem {
                $path = database_path("snapshots/{$name}.sql");

                return new SnapshotListItem(
                    name: $name,
                    size: File::size($path),
                    createdAt: Carbon::createFromTimestamp(File::lastModified($path)),
                );
            })
            ->sortByDesc(fn (SnapshotListItem $item): int => (int) $item->createdAt->timestamp)
            ->values();
    }

    /**
     * Get the path to the checksum map file.
     */
    private function getMetadataPath(): string
    {
        return database_path('snapshots/' . self::SCHEMA_METADATA_FILE);
    }
}
