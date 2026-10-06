<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Core\Exceptions;

use App\Domains\Core\Enums\ExternalService;
use App\Domains\Core\Exceptions\ServiceDownError;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
final class ServiceDownErrorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false]);

        $throw = fn (): never => throw new ServiceDownError(ExternalService::DirectorySearch, 'Connection refused by directory.example.test', 3);
        Route::get('/__test/service-down', $throw);
        Route::get('/api/__test/service-down', $throw);
    }

    // The outage is in another service: people get the 503 page, never the upstream error.
    public function test_a_browser_gets_the_service_unavailable_page(): void
    {
        $this->get('/__test/service-down')
            ->assertServiceUnavailable()
            ->assertSee('Service Unavailable')
            ->assertDontSee('directory.example.test');
    }

    public function test_an_api_request_gets_problem_details_with_a_retry_time(): void
    {
        $this->getJson('/api/__test/service-down')
            ->assertServiceUnavailable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertHeader('Retry-After', '60')
            ->assertJsonPath('detail', ExternalService::DirectorySearch->label() . ' is temporarily unavailable.')
            ->assertDontSee('directory.example.test');
    }
}
