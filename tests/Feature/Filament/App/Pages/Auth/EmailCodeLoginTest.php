<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\App\Pages\Auth;

use App\Domains\Auth\Models\LoginChallenge;
use App\Domains\Auth\ValueObjects\LoginCodeSession;
use App\Domains\User\Models\User;
use App\Filament\App\Pages\Auth\EmailCodeLogin;
use App\Providers\Filament\AppPanelProvider;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Timebox;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(EmailCodeLogin::class)]
final class EmailCodeLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(AppPanelProvider::ID);

        config(['local-auth.enabled' => true]);
        config(['local-auth.code.digits' => 6]);
        config(['local-auth.code.resend_cooldown_seconds' => 30]);

        Mail::fake();

        $this->mock(Timebox::class, function ($mock) {
            $mock->shouldReceive('call')->andReturnUsing(
                fn (callable $callback, int $microseconds) => $callback(new Timebox())
            );
        });
    }

    public function test_page_renders_over_http(): void
    {
        $this->get('/app/login/email')->assertOk()->assertSee('Request a Verification Code');
    }

    public function test_returns_404_when_local_auth_is_disabled(): void
    {
        config(['local-auth.enabled' => false]);

        Livewire::test(EmailCodeLogin::class)->assertNotFound();
    }

    public function test_signed_in_users_are_redirected_home(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(EmailCodeLogin::class)->assertRedirect('/');
    }

    public function test_requesting_a_code_moves_to_the_code_step_and_stores_the_challenge(): void
    {
        User::factory()->affiliate()->create(['email' => 'test@example.com']);

        Livewire::test(EmailCodeLogin::class)
            ->fillForm(['email' => 'Test@Example.com'])
            ->call('requestCode')
            ->assertHasNoFormErrors()
            ->assertSet('email', 'test@example.com')
            ->assertSee('Check Your Email');

        $challenge = LoginChallenge::query()->where('email', 'test@example.com')->sole();

        $this->assertSame('test@example.com', session(LoginCodeSession::EMAIL));
        $this->assertSame((string) $challenge->id, LoginCodeSession::challengeId());
    }

    public function test_unknown_emails_get_the_same_code_step_with_a_decoy_challenge(): void
    {
        Livewire::test(EmailCodeLogin::class)
            ->fillForm(['email' => 'missing@example.com'])
            ->call('requestCode')
            ->assertHasNoFormErrors()
            ->assertSet('email', 'missing@example.com')
            ->assertSee('Check Your Email');

        $this->assertSame(0, LoginChallenge::count());
        $this->assertFalse(ctype_digit((string) LoginCodeSession::challengeId()));
    }

    public function test_email_is_required_and_validated(): void
    {
        Livewire::test(EmailCodeLogin::class)
            ->fillForm(['email' => ''])
            ->call('requestCode')
            ->assertHasFormErrors(['email' => 'required']);

        Livewire::test(EmailCodeLogin::class)
            ->fillForm(['email' => 'bad-email'])
            ->call('requestCode')
            ->assertHasFormErrors(['email' => 'email']);
    }

    public function test_rate_limit_errors_are_shown_on_the_email_field(): void
    {
        config(['local-auth.rate_limit_per_hour' => 1]);
        RateLimiter::hit('login-code:test@example.com', 3600);

        Livewire::test(EmailCodeLogin::class)
            ->fillForm(['email' => 'test@example.com'])
            ->call('requestCode')
            ->assertHasFormErrors(['email'])
            ->assertSet('email', null);
    }

    public function test_reloading_returns_to_the_code_step(): void
    {
        session([LoginCodeSession::EMAIL => 'test@example.com']);

        Livewire::test(EmailCodeLogin::class)
            ->assertSet('email', 'test@example.com')
            ->assertSee('Check Your Email');
    }

    public function test_valid_code_signs_the_user_in_and_clears_the_flow(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);
        $this->startFlow($user->email, $this->challengeFor($user->email, '123456'));

        Livewire::test(EmailCodeLogin::class)
            ->fillForm(['code' => '123456'], 'codeForm')
            ->call('verifyCode')
            ->assertHasNoFormErrors([], 'codeForm')
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);

        foreach (LoginCodeSession::KEYS as $key) {
            $this->assertFalse(session()->has($key));
        }
    }

    public function test_valid_code_returns_users_to_the_page_they_asked_for(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);
        $this->startFlow($user->email, $this->challengeFor($user->email, '123456'));
        session(['url.intended' => url('/app/some-page')]);

        Livewire::test(EmailCodeLogin::class)
            ->fillForm(['code' => '123456'], 'codeForm')
            ->call('verifyCode')
            ->assertRedirect(url('/app/some-page'));
    }

    // Codes sent by an administrator, or read on another device, have no challenge in this session.
    public function test_the_email_link_lets_a_code_issued_elsewhere_sign_the_user_in(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);
        $challenge = $this->challengeFor($user->email, '123456');

        Livewire::withQueryParams([LoginCodeSession::LINK_PARAMETER => $this->linkToken($challenge)])
            ->test(EmailCodeLogin::class)
            ->assertSet('email', 'test@example.com')
            ->assertSee('Check Your Email')
            ->fillForm(['code' => '123456'], 'codeForm')
            ->call('verifyCode')
            ->assertHasNoFormErrors([], 'codeForm')
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_links_to_expired_or_unknown_challenges_are_ignored(): void
    {
        $expired = $this->challengeFor('test@example.com', '123456');
        $expired->update(['expires_at' => now()->subMinute()]);

        foreach ([$this->linkToken($expired), Crypt::encryptString('999999'), 'not-a-token'] as $token) {
            Livewire::withQueryParams([LoginCodeSession::LINK_PARAMETER => $token])
                ->test(EmailCodeLogin::class)
                ->assertSet('email', null)
                ->assertSee('Request a Verification Code');
        }
    }

    public function test_invalid_code_shows_an_error_on_the_code_field(): void
    {
        $user = User::factory()->affiliate()->create();
        $this->startFlow($user->email, $this->challengeFor($user->email, '123456'));

        Livewire::test(EmailCodeLogin::class)
            ->fillForm(['code' => '000000'], 'codeForm')
            ->call('verifyCode')
            ->assertHasFormErrors(['code'], 'codeForm');

        $this->assertGuest();
    }

    public function test_code_must_have_the_configured_number_of_digits(): void
    {
        $this->startFlow('test@example.com');

        Livewire::test(EmailCodeLogin::class)
            ->fillForm(['code' => '123'], 'codeForm')
            ->call('verifyCode')
            ->assertHasFormErrors(['code'], 'codeForm');
    }

    public function test_resend_replaces_the_challenge_and_starts_the_cooldown(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);
        $original = $this->challengeFor($user->email, '123456');
        $this->startFlow($user->email, $original);

        Livewire::test(EmailCodeLogin::class)
            ->call('resendCode')
            ->assertNotified('Code Sent');

        $this->assertNotSame((string) $original->id, LoginCodeSession::challengeId());
        $this->assertTrue(RateLimiter::tooManyAttempts('login-code-resend:test@example.com', 1));
    }

    public function test_resend_respects_the_cooldown(): void
    {
        $this->startFlow('test@example.com');
        RateLimiter::hit('login-code-resend:test@example.com', 30);

        Livewire::test(EmailCodeLogin::class)->call('resendCode');

        $this->assertSame(0, LoginChallenge::count());
    }

    public function test_resend_keeps_the_decoy_for_unknown_emails(): void
    {
        $this->startFlow('missing@example.com');
        $decoy = LoginCodeSession::challengeId();

        Livewire::test(EmailCodeLogin::class)
            ->call('resendCode')
            ->assertNotified('Code Sent');

        $this->assertSame($decoy, LoginCodeSession::challengeId());
    }

    public function test_using_a_different_email_returns_to_the_first_step(): void
    {
        $this->startFlow('test@example.com');

        Livewire::test(EmailCodeLogin::class)
            ->call('useDifferentEmail')
            ->assertSet('email', null)
            ->assertSee('Request a Verification Code');

        $this->assertNull(LoginCodeSession::email());
    }

    private function challengeFor(string $email, string $code): LoginChallenge
    {
        return LoginChallenge::create([
            'email' => $email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    private function linkToken(LoginChallenge $challenge): string
    {
        parse_str((string) parse_url(LoginCodeSession::link($challenge), PHP_URL_QUERY), $query);

        return $query[LoginCodeSession::LINK_PARAMETER];
    }

    private function startFlow(string $email, ?LoginChallenge $challenge = null): void
    {
        session([
            LoginCodeSession::EMAIL => $email,
            LoginCodeSession::CHALLENGE_ID => Crypt::encryptString($challenge instanceof LoginChallenge ? (string) $challenge->id : 'decoy-uuid'),
        ]);
    }
}
