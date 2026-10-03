<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Http\Controllers\WebSSOController;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(WebSSOController::class)]
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
}
