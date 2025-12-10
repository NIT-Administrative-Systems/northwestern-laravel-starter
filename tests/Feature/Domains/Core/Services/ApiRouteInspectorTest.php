<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Core\Services;

use App\Domains\Core\Services\ApiRouteInspector;
use App\Http\Middleware\AuthenticatesAccessTokens;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ApiRouteInspector::class)]
class ApiRouteInspectorTest extends TestCase
{
    protected function tearDown(): void
    {
        Route::setRoutes(new RouteCollection());
        parent::tearDown();
    }

    public function test_has_protected_routes_returns_true_when_protected_api_route_exists(): void
    {
        Route::get('web/dashboard', fn () => 'web');
        Route::get('api/unprotected/v1', fn () => 'public');
        Route::get('api/protected/v1', fn () => 'secret')
            ->middleware([AuthenticatesAccessTokens::class]);

        $inspector = $this->inspector();

        $this->assertTrue($inspector->hasProtectedRoutes());
    }

    public function test_has_protected_routes_handles_mixed_routes(): void
    {
        Route::get('web/public', fn () => 'web');
        Route::get('api/health', fn () => 'public');
        Route::get('api/secret', fn () => 'secret')
            ->middleware(['auth', AuthenticatesAccessTokens::class, 'throttle']);
        Route::get('app/admin', fn () => 'app');

        $inspector = $this->inspector();

        $this->assertTrue($inspector->hasProtectedRoutes());
    }

    public function test_has_protected_routes_returns_false_when_no_api_routes_exist(): void
    {
        Route::get('web/dashboard', fn () => 'web');
        Route::get('app/profile', fn () => 'app');

        $inspector = $this->inspector();

        $this->assertFalse($inspector->hasProtectedRoutes());
    }

    public function test_has_protected_routes_returns_false_when_only_unprotected_api_routes_exist(): void
    {
        Route::get('api/unprotected/v1/health', fn () => 'health');
        Route::get('api/public/v2/status', fn () => 'status');

        $inspector = $this->inspector();

        $this->assertFalse($inspector->hasProtectedRoutes());
    }

    public function test_has_protected_routes_handles_empty_routes(): void
    {
        $inspector = $this->inspector();

        $this->assertFalse($inspector->hasProtectedRoutes());
    }

    protected function inspector(): ApiRouteInspector
    {
        return resolve(ApiRouteInspector::class);
    }
}
