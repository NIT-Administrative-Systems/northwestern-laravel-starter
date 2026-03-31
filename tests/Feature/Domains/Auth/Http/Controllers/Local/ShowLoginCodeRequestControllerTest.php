<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Controllers\Local;

use App\Domains\Auth\Http\Controllers\Local\ShowLoginCodeRequestController;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ShowLoginCodeRequestController::class)]
class ShowLoginCodeRequestControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Route::has('login-code.request')) {
            $this->markTestSkipped('Local auth routes are not registered.');
        }
    }

    public function test_displays_request_view_when_enabled(): void
    {
        config(['local-auth.enabled' => true]);

        $response = $this->get(route('login-code.request'));

        $response->assertOk();
        $response->assertViewIs('auth.login-code-request');
    }

    public function test_returns_404_when_disabled(): void
    {
        config(['local-auth.enabled' => false]);

        $response = $this->get(route('login-code.request'));

        $response->assertNotFound();
    }
}
