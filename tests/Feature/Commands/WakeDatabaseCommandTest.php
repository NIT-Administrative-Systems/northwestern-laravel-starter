<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Console\Commands\WakeDatabaseCommand;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(WakeDatabaseCommand::class)]
class WakeDatabaseCommandTest extends TestCase
{
    public function test_database_command_runs_successfully_with_valid_defaults(): void
    {
        DB::shouldReceive('select')
            ->once()
            ->with('SELECT 1')
            ->andReturn([]);

        $this->artisan('db:wake')
            ->expectsOutputToContain('Waking database connection')
            ->expectsOutputToContain('Configuration: 5 attempts, 5s delay')
            ->expectsOutputToContain('Database connection established successfully')
            ->assertExitCode(0);
    }

    public function test_database_command_uses_custom_options(): void
    {
        DB::shouldReceive('select')
            ->once()
            ->with('SELECT 1')
            ->andReturn([]);

        $this->artisan('db:wake', [
            '--max-attempts' => 3,
            '--delay' => 1,
        ])
            ->expectsOutputToContain('Waking database connection')
            ->expectsOutputToContain('Configuration: 3 attempts, 1s delay')
            ->expectsOutputToContain('Database connection established successfully')
            ->assertExitCode(0);
    }

    public function test_invalid_max_attempts_falls_back_to_default(): void
    {
        DB::shouldReceive('select')
            ->once()
            ->with('SELECT 1')
            ->andReturn([]);

        $this->artisan('db:wake', [
            '--max-attempts' => 100,
        ])
            ->expectsOutputToContain('Max attempts must be between 1 and 20')
            ->expectsOutputToContain('Configuration: 5 attempts, 5s delay')
            ->assertExitCode(0);
    }

    public function test_invalid_delay_falls_back_to_default(): void
    {
        DB::shouldReceive('select')
            ->once()
            ->with('SELECT 1')
            ->andReturn([]);

        $this->artisan('db:wake', [
            '--delay' => 100,
        ])
            ->expectsOutputToContain('Delay must be between 1 and 30')
            ->expectsOutputToContain('Configuration: 5 attempts, 2s delay')
            ->assertExitCode(0);
    }
}
