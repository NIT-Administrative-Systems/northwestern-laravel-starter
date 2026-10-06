<?php

declare(strict_types=1);

namespace Tests\Feature\Exceptions;

use App\Domains\User\Models\User;
use App\View\Components\ErrorLayout;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\TestCase;

#[CoversClass(ErrorLayout::class)]
final class ErrorPagesTest extends TestCase
{
    /**
     * Roll back only the real test connection, not the unreachable one these tests switch to.
     *
     * @var list<string>
     */
    protected array $connectionsToTransact = ['phpunit'];

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false]);

        Route::get('/__test/server-error', fn () => throw new RuntimeException('Sensitive failure detail'));
        Route::get('/__test/unavailable', fn () => abort(503));
    }

    public function test_not_found_page_uses_the_error_layout_with_the_footer(): void
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertSee('Page Not Found')
            ->assertSee('Back to Homepage')
            ->assertSee('Privacy Statement');
    }

    public function test_not_found_page_has_the_help_menu_and_sign_in_for_guests(): void
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertSee('data-testid="help-menu-trigger"', escape: false)
            ->assertSee('data-testid="sign-in-link"', escape: false);
    }

    public function test_not_found_page_knows_who_is_signed_in(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/no-such-page')
            ->assertNotFound()
            ->assertSee('fi-user-menu', escape: false)
            ->assertDontSee('data-testid="sign-in-link"', escape: false);
    }

    public function test_unknown_api_paths_are_not_caught_by_the_web_fallback(): void
    {
        $this->get('/api/no-such-endpoint')
            ->assertNotFound()
            ->assertCookieMissing(config('session.cookie'));
    }

    public function test_service_unavailable_page_keeps_the_bare_header(): void
    {
        $this->get('/__test/unavailable')
            ->assertServiceUnavailable()
            ->assertSee('Service Unavailable')
            ->assertDontSee('data-testid="help-menu-trigger"', escape: false)
            ->assertDontSee('data-testid="sign-in-link"', escape: false);
    }

    public function test_server_error_page_renders_when_the_database_is_unavailable(): void
    {
        $this->actingAs(User::factory()->create());
        $this->breakTheDatabase();

        $this->get('/__test/server-error')
            ->assertInternalServerError()
            ->assertSee('Something Went Wrong')
            ->assertSee('Privacy Statement');
    }

    public function test_server_error_details_fail_closed_in_production_when_permissions_cannot_be_checked(): void
    {
        $this->actingAs(User::factory()->create());
        $this->app->detectEnvironment(fn () => 'production');
        $this->breakTheDatabase();

        $this->get('/__test/server-error')
            ->assertInternalServerError()
            ->assertDontSee('Sensitive failure detail');
    }

    public function test_server_error_details_are_shown_outside_production(): void
    {
        $this->get('/__test/server-error')
            ->assertInternalServerError()
            ->assertSee('Sensitive failure detail')
            ->assertSee('Non-Production Only');
    }

    /**
     * Point the default connection at a port nothing listens on, so any query fails.
     */
    private function breakTheDatabase(): void
    {
        config([
            'database.connections.unreachable' => [
                ...config('database.connections.phpunit'),
                'port' => 1,
            ],
            'database.default' => 'unreachable',
        ]);

        $this->beforeApplicationDestroyed(fn () => config(['database.default' => 'phpunit']));
    }
}
