<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth;

use App\Domains\Auth\Enums\AuthType;
use App\Domains\Auth\Enums\SignInMethod;
use App\Domains\Auth\Enums\SsoProvider;
use App\Domains\Auth\SignIn;
use App\Domains\User\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(SignIn::class)]
#[CoversClass(SsoProvider::class)]
#[CoversClass(SignInMethod::class)]
final class SignInTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Each provider's routes exist only when it's configured as the application boots.
        foreach ([...array_map(fn (SsoProvider $provider): string => $provider->loginRoute(), SsoProvider::cases()), ...array_map(fn (SsoProvider $provider): string => $provider->logoutRoute(), SsoProvider::cases())] as $name) {
            if (! Route::has($name)) {
                Route::get("test-sso/{$name}", fn () => null)->name($name);
            }
        }

        Route::getRoutes()->refreshNameLookups();

        $this->useSso(null);
        config(['local-auth.enabled' => true]);
    }

    /**
     * @return \Iterator<string, array{array<string, mixed>, ?SsoProvider}>
     */
    public static function providerProvider(): \Iterator
    {
        yield 'none' => [[], null];
        yield 'Entra ID' => [['services.northwestern-azure.client_id' => 'id', 'services.northwestern-azure.client_secret' => 'secret'], SsoProvider::EntraId];
        yield 'Entra ID without its secret' => [['services.northwestern-azure.client_id' => 'id'], null];
        yield 'Online Passport by API key' => [['nusoa.sso.apigeeApiKey' => 'key'], SsoProvider::OnlinePassport];
        yield 'Online Passport by ForgeRock direct' => [['nusoa.sso.strategy' => 'forgerock-direct'], SsoProvider::OnlinePassport];
        yield 'both, where Online Passport wins' => [['nusoa.sso.apigeeApiKey' => 'key', 'services.northwestern-azure.client_id' => 'id', 'services.northwestern-azure.client_secret' => 'secret'], SsoProvider::OnlinePassport];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    #[DataProvider('providerProvider')]
    public function test_one_sso_provider_is_configured(array $config, ?SsoProvider $expected): void
    {
        config($config);

        $this->assertSame($expected, SsoProvider::configured());
    }

    public function test_each_provider_names_its_routes(): void
    {
        $this->assertSame(['Online Passport', 'login-websso', 'login-websso-logout'], [SsoProvider::OnlinePassport->label(), SsoProvider::OnlinePassport->loginRoute(), SsoProvider::OnlinePassport->logoutRoute()]);
        $this->assertSame(['Entra ID', 'login-oauth-redirect', 'login-oauth-logout'], [SsoProvider::EntraId->label(), SsoProvider::EntraId->loginRoute(), SsoProvider::EntraId->logoutRoute()]);
    }

    public function test_it_offers_the_methods_that_are_set_up(): void
    {
        $this->assertSame([SignInMethod::EmailCode], $this->signIn()->methods());

        $this->useSso(SsoProvider::EntraId);
        $this->assertSame([SignInMethod::Sso, SignInMethod::EmailCode], $this->signIn()->methods());

        config(['local-auth.enabled' => false]);
        $this->assertSame([SignInMethod::Sso], $this->signIn()->methods());
        $this->assertFalse($this->signIn()->offers(SignInMethod::EmailCode));
    }

    // An explicit allowlist: never "anywhere but production".
    public function test_sign_in_as_is_offered_only_in_the_local_environment(): void
    {
        $this->assertFalse($this->signIn()->offers(SignInMethod::SignInAs));

        $this->app->detectEnvironment(fn (): string => 'local');

        $this->assertTrue($this->signIn()->offers(SignInMethod::SignInAs));
    }

    public function test_each_offered_method_has_a_place_to_start(): void
    {
        $this->useSso(SsoProvider::OnlinePassport);

        $this->assertSame(route('login-websso'), $this->signIn()->url(SignInMethod::Sso));
        $this->assertSame(route('filament.app.auth.login-code'), $this->signIn()->url(SignInMethod::EmailCode));

        $this->app->detectEnvironment(fn (): string => 'local');
        $this->assertNull($this->signIn()->url(SignInMethod::SignInAs));

        $this->useSso(null);
        $this->assertNull($this->signIn()->url(SignInMethod::Sso));
    }

    public function test_completing_a_sign_in_starts_a_fresh_session_and_records_it(): void
    {
        $user = User::factory()->create();
        session(['url.intended' => url('/app/some-page')]);
        $sessionId = session()->getId();
        $token = session()->token();

        $response = $this->signIn()->complete($user, $this->request(), SignInMethod::Sso);

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame($token, session()->token());
        $this->assertSame(1, $user->login_records()->count());
        $this->assertSame(url('/app/some-page'), $response->getTargetUrl());
    }

    public function test_without_a_page_to_return_to_people_land_on_home(): void
    {
        $response = $this->signIn()->complete(User::factory()->create(), $this->request(), SignInMethod::SignInAs);

        $this->assertSame(url('/'), $response->getTargetUrl());
    }

    /**
     * @return \Iterator<string, array{SignInMethod, bool}>
     */
    public static function rememberProvider(): \Iterator
    {
        yield 'single sign-on' => [SignInMethod::Sso, false];
        yield 'an email code' => [SignInMethod::EmailCode, true];
        yield 'sign-in-as' => [SignInMethod::SignInAs, false];
    }

    // An email code costs a trip to the inbox, so only it outlasts the session.
    #[DataProvider('rememberProvider')]
    public function test_only_an_email_code_sign_in_is_remembered(SignInMethod $method, bool $remembered): void
    {
        $this->signIn()->complete(User::factory()->create(), $this->request(), $method);

        $this->assertSame($remembered, $method->remembers());
        $guard = Auth::guard('web');
        $this->assertInstanceOf(SessionGuard::class, $guard);
        $this->assertSame($remembered, Cookie::hasQueued($guard->getRecallerName()));
    }

    public function test_single_sign_on_people_sign_out_through_their_provider(): void
    {
        $this->useSso(SsoProvider::EntraId);
        $this->actingAs(User::factory()->create(['auth_type' => AuthType::SSO]));

        $response = $this->signIn()->signOut($this->request());

        $this->assertSame(route('login-oauth-logout'), $response->getTargetUrl());
    }

    /**
     * @return \Iterator<string, array{AuthType, ?SsoProvider}>
     */
    public static function localSignOutProvider(): \Iterator
    {
        yield 'a local account' => [AuthType::Local, SsoProvider::OnlinePassport];
        yield 'single sign-on with no provider configured any more' => [AuthType::SSO, null];
    }

    #[DataProvider('localSignOutProvider')]
    public function test_everyone_else_signs_out_here(AuthType $authType, ?SsoProvider $provider): void
    {
        $this->useSso($provider);
        // As a request loads them: logging out rotates the remember token, which a model fresh from the factory lacks.
        $this->actingAs(User::factory()->create(['auth_type' => $authType])->fresh());
        $token = session()->token();

        $response = $this->signIn()->signOut($this->request());

        $this->assertGuest();
        $this->assertNotSame($token, session()->token());
        $this->assertSame(route('filament.app.auth.login'), $response->getTargetUrl());
    }

    public function test_signing_out_with_nobody_signed_in_returns_to_sign_in(): void
    {
        $response = $this->signIn()->signOut($this->request());

        $this->assertSame(route('filament.app.auth.login'), $response->getTargetUrl());
    }

    private function useSso(?SsoProvider $provider): void
    {
        config([
            'nusoa.sso.apigeeApiKey' => $provider === SsoProvider::OnlinePassport ? 'key' : null,
            'nusoa.sso.strategy' => null,
            'services.northwestern-azure.client_id' => $provider === SsoProvider::EntraId ? 'id' : null,
            'services.northwestern-azure.client_secret' => $provider === SsoProvider::EntraId ? 'secret' : null,
        ]);
    }

    private function signIn(): SignIn
    {
        return resolve(SignIn::class);
    }

    private function request(): Request
    {
        $request = Request::create('/');
        $request->setUserResolver(fn (): ?User => Auth::guard('web')->user());

        return $request;
    }
}
