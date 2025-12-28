<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Controllers\Local;

use App\Domains\Auth\Http\Controllers\Local\ShowLoginCodeRequestController;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ShowLoginCodeRequestController::class)]
class ShowLoginCodeRequestControllerTest extends TestCase
{
    public function test_displays_request_view_when_enabled(): void
    {
        config(['auth.local.enabled' => true]);

        $response = $this->get(route('login-code.request'));

        $response->assertOk();
        $response->assertViewIs('auth.login-code-request');
    }

    public function test_returns_404_when_disabled(): void
    {
        config(['auth.local.enabled' => false]);

        $response = $this->get(route('login-code.request'));

        $response->assertNotFound();
    }
}
