<?php

declare(strict_types=1);

namespace Tests\Feature\Exceptions;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use Illuminate\View\ViewException;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class DatabasePausedRenderingTest extends TestCase
{
    public function test_renders_database_paused_page_for_blade_wrapped_aurora_wake_timeouts(): void
    {
        Route::get('/__test/database-paused', function (): never {
            throw new ViewException(
                'SQLSTATE[08006] [7] connection to server at "database.example.test", port 5432 failed: timeout expired (Connection: pgsql) (View: /resources/views/layout.blade.php)',
                0,
                1,
                __FILE__,
                __LINE__,
            );
        });

        $this->get('/__test/database-paused')->assertInternalServerError()
            ->assertSee('Database Paused');
    }

    /**
     * @return array<string, array{string, array<string, string>}>
     */
    public static function clientsThatDontReadHtml(): array
    {
        return [
            'API' => ['/api/__test/database-paused', []],
            'OAuth' => ['/oauth/__test/database-paused', []],
            'MCP server' => ['/mcp', []],
            'MCP path' => ['/mcp/__test/database-paused', []],
            'expects JSON' => ['/__test/database-paused', ['Accept' => 'application/json']],
        ];
    }

    /**
     * @param  array<string, string>  $headers
     */
    #[DataProvider('clientsThatDontReadHtml')]
    public function test_clients_that_dont_read_html_get_problem_details_with_retry_after(string $path, array $headers): void
    {
        Route::get($path, $this->pausedDatabaseQuery(...));

        $this->get($path, $headers)
            ->assertServiceUnavailable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertHeader('Retry-After', '30')
            ->assertJsonPath('status', 503);
    }

    public function test_livewire_requests_get_the_database_paused_page(): void
    {
        Route::get('/__test/database-paused', $this->pausedDatabaseQuery(...));

        $this->get('/__test/database-paused', ['X-Livewire' => '1', 'Accept' => 'application/json'])
            ->assertInternalServerError()
            ->assertSee('Database Paused');
    }

    private function pausedDatabaseQuery(): never
    {
        $pdo = new PDOException('SQLSTATE[08006] [7] connection to server at "database.example.test", port 5432 failed: timeout expired');

        throw new QueryException('pgsql', 'select 1', [], $pdo);
    }
}
