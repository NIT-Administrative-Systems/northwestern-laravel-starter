<?php

declare(strict_types=1);

namespace Tests\Feature\Exceptions;

use Illuminate\Support\Facades\Route;
use Illuminate\View\ViewException;
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
}
