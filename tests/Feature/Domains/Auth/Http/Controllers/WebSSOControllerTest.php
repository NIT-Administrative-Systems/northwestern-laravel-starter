<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Controllers;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Auth\Enums\AuthType;
use App\Domains\Auth\Http\Controllers\WebSSOController;
use App\Domains\User\Actions\Lifecycle\DeactivateUser;
use App\Domains\User\Actions\Lifecycle\ReactivateUser;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Northwestern\SysDev\SOA\Auth\Strategy\WebSSOStrategy;
use Northwestern\SysDev\SOA\DirectorySearch;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Feature\Domains\User\Actions\Directory\FindOrUpdateUserFromDirectoryTest;
use Tests\TestCase;

#[CoversNothing]
final class WebSSOControllerTest extends TestCase
{
    // Socialite throws when services.northwestern-azure has no `redirect` key, even though
    // laravel-soa builds the callback URL from the route instead of reading it.
    public function test_entra_sign_in_redirects_to_microsoft_with_the_callback_route(): void
    {
        config([
            'services.northwestern-azure.client_id' => 'test-client-id',
            'services.northwestern-azure.client_secret' => 'test-client-secret',
        ]);

        // routes/auth.php only registers the Entra routes when credentials are set at boot.
        Route::middleware('web')->group(function (): void {
            Route::get('auth/azure-ad/redirect', [WebSSOController::class, 'oauthRedirect']);
            Route::post('auth/azure-ad/callback', [WebSSOController::class, 'oauthCallback'])->name('login-oauth-callback');
        });
        Route::getRoutes()->refreshNameLookups();

        $location = (string) $this->get('/auth/azure-ad/redirect')->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith('https://login.microsoftonline.com/', $location);
        $this->assertStringContainsString('redirect_uri=' . urlencode(url('/auth/azure-ad/callback')), $location);
    }

    // Signing in looks the NetID up and syncs it, which once restored a deactivated account.
    public function test_a_deactivated_netid_user_is_refused_until_an_administrator_reactivates_them(): void
    {
        config(['platform.wildcard_photo_sync' => false]);
        $person = User::factory()->create(['username' => 'abc123', 'auth_type' => AuthType::SSO]);
        $administrator = User::factory()->affiliate()->create();
        $administrator->givePermissionTo(SystemPermission::EditUsers);
        resolve(DeactivateUser::class)($person, null);

        $directorySearch = $this->createStub(DirectorySearch::class);
        $directorySearch->method('lookup')->willReturn(FindOrUpdateUserFromDirectoryTest::studentData('abc123'));
        $this->app->instance(DirectorySearch::class, $directorySearch);
        $strategy = $this->createStub(WebSSOStrategy::class);
        $strategy->method('login')->willReturn('abc123');
        $this->app->instance(WebSSOStrategy::class, $strategy);

        Route::middleware('web')->get('auth/websso/login', [WebSSOController::class, 'login'])->name('login-websso');
        Route::getRoutes()->refreshNameLookups();

        $this->get('/auth/websso/login')
            ->assertForbidden()
            ->assertSee('Account Deactivated');

        $this->assertGuest('web');
        $this->assertSoftDeleted($person);

        resolve(ReactivateUser::class)($person->fresh() ?? $person, $administrator);

        $this->get('/auth/websso/login')->assertRedirect();

        $this->assertTrue($person->is(Auth::guard('web')->user()));
    }
}
