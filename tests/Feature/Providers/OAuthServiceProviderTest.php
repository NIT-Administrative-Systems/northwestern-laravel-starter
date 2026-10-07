<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Api\ApiScopes;
use App\Providers\OAuthServiceProvider;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Bridge\AccessTokenRepository;
use Laravel\Passport\Passport;
use Northwestern\SysDev\Chassis\Passport\ExpiringAccessTokenRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(OAuthServiceProvider::class)]
final class OAuthServiceProviderTest extends TestCase
{
    public function test_passport_issues_the_catalogs_scopes(): void
    {
        $this->assertSame(array_keys(ApiScopes::all()), array_column(Passport::scopes()->all(), 'id'));
        $this->assertFalse(Passport::hasScope(SystemPermission::ManageAll->value));
    }

    public function test_credentials_cannot_be_created_through_passport_endpoints(): void
    {
        $this->assertFalse(Route::has('passport.device'));
        $this->assertFalse(Route::has('passport.clients.store'));
        $this->assertFalse(Route::has('passport.personal.tokens.store'));
        $this->assertTrue(Route::has('passport.token'));
    }

    public function test_token_lifetimes(): void
    {
        $this->assertSame(3600, (int) CarbonInterval::instance(Passport::tokensExpireIn())->totalSeconds);
        $this->assertSame(30, (int) CarbonInterval::instance(Passport::refreshTokensExpireIn())->totalDays);
        $this->assertSame(365, (int) CarbonInterval::instance(Passport::personalAccessTokensExpireIn())->totalDays);
    }

    // Integrations call from servers; a browser origin must be allowed explicitly (config/cors.php).
    public function test_the_api_allows_no_browser_origin_by_default(): void
    {
        $this->options('/api/v1/me', [], [
            'Origin' => 'https://evil.example.test',
            'Access-Control-Request-Method' => 'GET',
        ])->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_access_tokens_can_expire_before_their_signed_lifetime(): void
    {
        $this->assertInstanceOf(ExpiringAccessTokenRepository::class, resolve(AccessTokenRepository::class));
    }
}
