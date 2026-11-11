<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Api\Services;

use App\Domains\Api\Http\Middleware\AuthenticatePassportToken;
use App\Domains\Api\Services\ApiRouteInspector;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ApiRouteInspector::class)]
final class ApiRouteInspectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::setRoutes(new RouteCollection());
    }

    protected function tearDown(): void
    {
        Route::setRoutes(new RouteCollection());
        parent::tearDown();
    }

    public function test_it_finds_a_protected_api_route(): void
    {
        Route::get('web/dashboard', fn () => 'web');
        Route::get('api/unprotected/v1', fn () => 'public');
        Route::get('api/protected/v1', fn () => 'secret')
            ->middleware([AuthenticatePassportToken::class]);

        $inspector = $this->inspector();

        $this->assertTrue($inspector->hasProtectedRoutes());
    }

    public function test_it_finds_a_protected_route_among_unprotected_ones(): void
    {
        Route::get('web/public', fn () => 'web');
        Route::get('api/health', fn () => 'public');
        Route::get('api/secret', fn () => 'secret')
            ->middleware(['auth', AuthenticatePassportToken::class, 'throttle']);
        Route::get('app/admin', fn () => 'app');

        $inspector = $this->inspector();

        $this->assertTrue($inspector->hasProtectedRoutes());
    }

    public function test_routes_outside_the_api_do_not_count(): void
    {
        Route::get('web/dashboard', fn () => 'web');
        Route::get('app/profile', fn () => 'app');

        $inspector = $this->inspector();

        $this->assertFalse($inspector->hasProtectedRoutes());
    }

    public function test_unprotected_api_routes_do_not_count(): void
    {
        Route::get('api/unprotected/v1/health', fn () => 'health');
        Route::get('api/public/v2/status', fn () => 'status');

        $inspector = $this->inspector();

        $this->assertFalse($inspector->hasProtectedRoutes());
    }

    public function test_no_routes_means_no_protected_routes(): void
    {
        $inspector = $this->inspector();

        $this->assertFalse($inspector->hasProtectedRoutes());
    }

    protected function inspector(): ApiRouteInspector
    {
        return resolve(ApiRouteInspector::class);
    }
}
