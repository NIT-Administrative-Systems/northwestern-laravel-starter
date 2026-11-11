<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Controllers;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Auth\Enums\AuthType;
use App\Domains\Auth\Http\Controllers\WebSSOController;
use App\Domains\Core\Exceptions\ServiceDownError;
use App\Domains\User\Actions\Directory\FindOrUpdateUserFromDirectory;
use App\Domains\User\Actions\Lifecycle\DeactivateUser;
use App\Domains\User\Actions\Lifecycle\ReactivateUser;
use App\Domains\User\Exceptions\BadDirectoryEntry;
use App\Domains\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Sleep;
use Northwestern\SysDev\SOA\Auth\Strategy\WebSSOStrategy;
use Northwestern\SysDev\SOA\DirectorySearch;
use PHPUnit\Framework\Attributes\CoversNothing;
use ReflectionMethod;
use Tests\Feature\Domains\User\Actions\Directory\FindOrUpdateUserFromDirectoryTest;
use Tests\TestCase;

#[CoversNothing]
final class WebSSOControllerTest extends TestCase
{
    // Socialite throws if services.northwestern-azure has no `redirect` key,
    // though laravel-soa builds the callback URL from the route instead.
    public function test_entra_sign_in_redirects_to_microsoft_with_the_callback_route(): void
    {
        config([
            'services.northwestern-azure.client_id' => 'test-client-id',
            'services.northwestern-azure.client_secret' => 'test-client-secret',
        ]);

        // routes/auth.php registers the Entra ID routes only with credentials set at boot.
        Route::middleware('web')->group(function (): void {
            Route::get('auth/azure-ad/redirect', [WebSSOController::class, 'oauthRedirect']);
            Route::post('auth/azure-ad/callback', [WebSSOController::class, 'oauthCallback'])->name('login-oauth-callback');
        });
        Route::getRoutes()->refreshNameLookups();

        $location = (string) $this->get('/auth/azure-ad/redirect')->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith('https://login.microsoftonline.com/', $location);
        $this->assertStringContainsString('redirect_uri=' . urlencode(url('/auth/azure-ad/callback')), $location);
    }

    // Signing in looks the NetID up and syncs it,
    // which once restored deactivated people.
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

    public function test_a_new_person_whose_directory_lookup_fails_gets_the_503_page_after_three_tries(): void
    {
        Sleep::fake();
        $directoryApi = $this->createMock(DirectorySearch::class);
        $directoryApi->expects($this->exactly(3))->method('lookup')->willReturn(false);
        $directoryApi->method('getLastError')->willReturn('Verify connectivity to https://directory.example from the server: cURL error 28');

        try {
            $this->findUserByNetId($directoryApi, 'new123');
            $this->fail('A failed lookup for someone new signed them in.');
        } catch (ServiceDownError $e) {
            $this->assertSame(503, $e->render(Request::create('/auth/azure-ad/callback', 'POST'))->getStatusCode());
        }

        $this->assertFalse(User::query()->where('username', 'new123')->exists());
    }

    public function test_an_incomplete_entry_for_a_new_person_is_not_retried(): void
    {
        $directoryApi = $this->createMock(DirectorySearch::class);
        $directoryApi->expects($this->once())->method('lookup')->willReturn(['uid' => 'new123']);

        $this->expectException(BadDirectoryEntry::class);

        $this->findUserByNetId($directoryApi, 'new123');
    }

    private function findUserByNetId(DirectorySearch $directoryApi, string $netId): mixed
    {
        $finder = resolve(FindOrUpdateUserFromDirectory::class, ['directorySearch' => $directoryApi]);

        return new ReflectionMethod(WebSSOController::class, 'findUserByNetID')->invoke(resolve(WebSSOController::class), $finder, $netId);
    }
}
