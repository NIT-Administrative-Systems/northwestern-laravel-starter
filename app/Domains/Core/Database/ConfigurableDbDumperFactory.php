<?php

declare(strict_types=1);

namespace App\Domains\Core\Database;

use Illuminate\Support\Arr;
use RuntimeException;
use Spatie\DbDumper\DbDumper;
use Spatie\DbSnapshots\DbDumperFactory;
use Spatie\DbSnapshots\Exceptions\CannotCreateDbDumper;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;

class ConfigurableDbDumperFactory extends DbDumperFactory
{
    /**
     * Default PostgreSQL binary paths by platform.
     *
     * @var array<string, string>
     */
    private const array DEFAULT_PATHS = [
        'win' => '\.config\herd\bin\services\postgresql',
        'darwin' => '/Users/Shared/Herd/services/postgresql',
    ];

    /**
     * Create a database dumper instance for the given connection.
     *
     * @throws RuntimeException When the database connection is not supported
     * @throws CannotCreateDbDumper When the database connection is not supported
     */
    public static function createForConnection(string $connectionName): DbDumper
    {
        $dbDumper = parent::createForConnection($connectionName);

        $driver = config("database.connections.{$connectionName}.driver");

        if ($driver === 'pgsql') {
            $pgBinDir = self::findPostgresDirectory();

            if (filled($pgBinDir)) {
                $dbDumper->setDumpBinaryPath($pgBinDir);
            }
        }

        return $dbDumper;
    }

    /**
     * Find the PostgreSQL binary directory.
     *
     * @throws RuntimeException When the platform is not supported
     */
    public static function findPostgresDirectory(): ?string
    {
        if ($configuredDir = config('db-snapshots.pg_bin_directory')) {
            return $configuredDir;
        }

        $platform = self::getPlatform();
        $seekDir = match ($platform) {
            'win' => self::windowsHomeDir() . self::DEFAULT_PATHS['win'],
            'darwin' => self::DEFAULT_PATHS['darwin'],
            default => throw new RuntimeException('PostgreSQL binary auto-discovery only supports Windows and macOS (Laravel Herd). Set PG_BIN_DIRECTORY in .env instead.'),
        };

        try {
            return self::seek($seekDir);
        } catch (DirectoryNotFoundException) {
            return null;
        }
    }

    /**
     * Search for PostgreSQL binary directory in the given path.
     *
     * @throws DirectoryNotFoundException When the directory does not exist
     */
    private static function seek(string $seekDir): ?string
    {
        $pgSeeker = new Finder()
            ->in($seekDir)
            ->directories()
            ->depth(1)
            ->name('bin')
            ->sortByName();

        return Arr::first(
            iterator_to_array($pgSeeker->getIterator())
        )?->getRealPath() ?: null;
    }

    /**
     * Get the Windows user home directory.
     *
     * @throws RuntimeException When unable to determine the home directory
     */
    private static function windowsHomeDir(): string
    {
        $profileDir = getenv('USERPROFILE');

        if (in_array($profileDir, ['', '0', [], false], true)) {
            throw new RuntimeException('Unable to determine home directory. Set PG_BIN_DIRECTORY in .env instead.');
        }

        return $profileDir;
    }

    /**
     * Get the current platform identifier (win, darwin).
     *
     * @phpstan-pure
     */
    private static function getPlatform(): string
    {
        $os = strtolower(PHP_OS);

        if (str_starts_with($os, 'win')) {
            return 'win';
        }

        if (str_starts_with($os, 'darwin')) {
            return 'darwin';
        }

        return $os;
    }

    /** @phpstan-pure */
    public static function isWindowsPlatform(): bool
    {
        return self::getPlatform() === 'win';
    }

    /** @phpstan-pure */
    public static function determineQuoteForPlatform(): string
    {
        return self::isWindowsPlatform() ? '"' : "'";
    }
}
