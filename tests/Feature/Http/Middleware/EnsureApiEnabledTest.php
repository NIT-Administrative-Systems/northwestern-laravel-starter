<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\EnsureApiEnabled;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(EnsureApiEnabled::class)]
class EnsureApiEnabledTest extends TestCase
{
    private string $endpoint = '/api/enabled-test';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(EnsureApiEnabled::class)->get($this->endpoint, function () {
            return response()->json(['ok' => true]);
        });
    }

    public function test_request_is_blocked_with_503_when_api_is_disabled(): void
    {
        config(['auth.api.enabled' => false]);

        $this->getJson($this->endpoint)
            ->assertStatus(503)
            ->assertJson([
                'type' => 'about:blank',
                'title' => 'Service Unavailable',
                'status' => 503,
                'instance' => $this->endpoint,
            ]);
    }

    public function test_request_passes_through_when_api_is_enabled(): void
    {
        config(['auth.api.enabled' => true]);

        $this->getJson($this->endpoint)
            ->assertOk()
            ->assertJson(['ok' => true]);
    }
}
