<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Http\Controllers\WebSSOController;
use App\Domains\Core\Exceptions\ServiceDownError;
use App\Domains\User\Actions\Directory\FindOrUpdateUserFromDirectory;
use App\Domains\User\Exceptions\BadDirectoryEntry;
use App\Domains\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Sleep;
use Northwestern\SysDev\SOA\DirectorySearch;
use PHPUnit\Framework\Attributes\CoversNothing;
use ReflectionMethod;
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
