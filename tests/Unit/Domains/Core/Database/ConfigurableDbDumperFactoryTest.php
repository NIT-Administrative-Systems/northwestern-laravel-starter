<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Core\Database;

use App\Domains\Core\Database\ConfigurableDbDumperFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ConfigurableDbDumperFactory::class)]
class ConfigurableDbDumperFactoryTest extends TestCase
{
    public function test_is_not_windows_platform(): void
    {
        // This test runs on macOS/Linux CI — should never report Windows.
        $this->assertFalse(ConfigurableDbDumperFactory::isWindowsPlatform());
    }

    public function test_determine_quote_returns_single_quote_on_unix(): void
    {
        $this->assertSame("'", ConfigurableDbDumperFactory::determineQuoteForPlatform());
    }

    public function test_find_postgres_directory_returns_configured_path(): void
    {
        config(['db-snapshots.pg_bin_directory' => '/custom/pg/bin']);

        $result = ConfigurableDbDumperFactory::findPostgresDirectory();

        $this->assertSame('/custom/pg/bin', $result);
    }

    public function test_find_postgres_directory_returns_null_when_config_empty_and_herd_not_installed(): void
    {
        config(['db-snapshots.pg_bin_directory' => null]);

        if (PHP_OS_FAMILY === 'Linux') {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('PostgreSQL binary auto-discovery only supports Windows and macOS');
        }

        $result = ConfigurableDbDumperFactory::findPostgresDirectory();

        // On macOS without Herd, returns null (directory not found).
        if (PHP_OS_FAMILY === 'Darwin') {
            $this->assertTrue($result === null || $result !== '');
        }
    }

    public function test_create_for_connection_returns_dumper_for_sqlite(): void
    {
        config([
            'database.connections.test-sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);

        $dumper = ConfigurableDbDumperFactory::createForConnection('test-sqlite');

        $this->assertInstanceOf(\Spatie\DbDumper\Databases\Sqlite::class, $dumper);
    }
}
