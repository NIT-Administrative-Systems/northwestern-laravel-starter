<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\App\Starter\Pages\Auth;

use App\Domains\Auth\Actions\Local\FixedNumericOneTimeCodeGenerator;
use App\Domains\Auth\Contracts\OneTimeCodeGenerator;
use App\Domains\Auth\LoginCodes;
use App\Domains\Auth\Models\LoginChallenge;
use App\Domains\User\Models\User;
use App\Filament\App\Starter\Pages\Auth\EmailCodeLogin;
use App\Providers\Filament\AppPanelProvider;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Timebox;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
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
        // Every code is 123456.
        $this->app->bind(OneTimeCodeGenerator::class, FixedNumericOneTimeCodeGenerator::class);

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

        $this->assertSame(1, LoginChallenge::query()->where('email', 'test@example.com')->count());
        $this->assertSame('test@example.com', $this->loginCodes()->pendingEmail());
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
        $this->assertSame('missing@example.com', $this->loginCodes()->pendingEmail());
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
        $this->startFlow('test@example.com');

        Livewire::test(EmailCodeLogin::class)
            ->assertSet('email', 'test@example.com')
            ->assertSee('Check Your Email');
    }

    public function test_valid_code_signs_the_user_in_and_clears_the_flow(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);
        $this->startFlow($user->email);

        Livewire::test(EmailCodeLogin::class)
            ->fillForm(['code' => '123456'], 'codeForm')
            ->call('verifyCode')
            ->assertHasNoFormErrors([], 'codeForm')
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertNull($this->loginCodes()->pendingEmail());
    }

    public function test_valid_code_returns_users_to_the_page_they_asked_for(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);
        $this->startFlow($user->email);
        session(['url.intended' => url('/app/some-page')]);

        Livewire::test(EmailCodeLogin::class)
            ->fillForm(['code' => '123456'], 'codeForm')
            ->call('verifyCode')
            ->assertRedirect(url('/app/some-page'));
    }

    // A code an administrator sent, or one read elsewhere, has no challenge here.
    public function test_the_email_link_lets_a_code_issued_elsewhere_sign_the_user_in(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);
        $challenge = $this->challengeFor($user->email, '123456');

        Livewire::withQueryParams([LoginCodes::LINK_PARAMETER => $this->linkToken($challenge)])
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
            Livewire::withQueryParams([LoginCodes::LINK_PARAMETER => $token])
                ->test(EmailCodeLogin::class)
                ->assertSet('email', null)
                ->assertSee('Request a Verification Code');
        }
    }

    public function test_invalid_code_shows_an_error_on_the_code_field(): void
    {
        $user = User::factory()->affiliate()->create();
        $this->startFlow($user->email);

        Livewire::test(EmailCodeLogin::class)
            ->fillForm(['code' => '000000'], 'codeForm')
            ->call('verifyCode')
            ->assertHasFormErrors(['code'], 'codeForm');

        $this->assertGuest();
    }

    // The pending sign-in is per session, so the newest request replaces it.
    public function test_a_tab_whose_sign_in_another_tab_replaced_does_not_sign_in_as_the_other_address(): void
    {
        User::factory()->affiliate()->create(['email' => 'first@example.com']);
        $second = User::factory()->affiliate()->create(['email' => 'second@example.com']);
        $this->startFlow('first@example.com');
        $firstTab = Livewire::test(EmailCodeLogin::class)->assertSet('email', 'first@example.com');

        $this->startFlow($second->email);

        $firstTab->fillForm(['code' => '123456'], 'codeForm')
            ->call('verifyCode')
            ->assertHasFormErrors(['code'], 'codeForm')
            ->assertSet('email', 'second@example.com')
            ->assertSee('second@example.com');

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

    public function test_resend_sends_another_code_and_starts_the_cooldown(): void
    {
        User::factory()->affiliate()->create(['email' => 'test@example.com']);
        $this->startFlow('test@example.com');

        Livewire::test(EmailCodeLogin::class)
            ->call('resendCode')
            ->assertNotified('Code Sent')
            ->call('resendCode')
            ->assertNotified('Wait a Moment');

        $this->assertSame(2, LoginChallenge::count());
    }

    public function test_resend_respects_the_cooldown(): void
    {
        User::factory()->affiliate()->create(['email' => 'test@example.com']);
        $this->startFlow('test@example.com');
        RateLimiter::hit('login-code-resend:test@example.com', 30);

        Livewire::test(EmailCodeLogin::class)->call('resendCode')->assertNotified('Wait a Moment');

        $this->assertSame(1, LoginChallenge::count());
    }

    // An unknown email looks the same as a known one, resends included.
    public function test_resend_for_an_unknown_email_looks_the_same(): void
    {
        $this->startFlow('missing@example.com');

        Livewire::test(EmailCodeLogin::class)
            ->call('resendCode')
            ->assertNotified('Code Sent');

        $this->assertSame(0, LoginChallenge::count());
    }

    public function test_resend_sends_nothing_once_email_codes_are_turned_off(): void
    {
        User::factory()->affiliate()->create(['email' => 'test@example.com']);
        $this->startFlow('test@example.com');
        $component = Livewire::test(EmailCodeLogin::class);

        config(['local-auth.enabled' => false]);

        $component->call('resendCode')->assertNotFound();

        $this->assertSame(1, LoginChallenge::count());
    }

    public function test_resend_without_a_pending_sign_in_returns_to_the_first_step(): void
    {
        Livewire::test(EmailCodeLogin::class)
            ->call('resendCode')
            ->assertSet('email', null)
            ->assertSee('Request a Verification Code');
    }

    public function test_using_a_different_email_returns_to_the_first_step(): void
    {
        $this->startFlow('test@example.com');

        Livewire::test(EmailCodeLogin::class)
            ->call('useDifferentEmail')
            ->assertSet('email', null)
            ->assertSee('Request a Verification Code');

        $this->assertNull($this->loginCodes()->pendingEmail());
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
        parse_str((string) parse_url($this->loginCodes()->link($challenge), PHP_URL_QUERY), $query);

        return $query[LoginCodes::LINK_PARAMETER];
    }

    /**
     * Request a code as the email step does: 123456 for an account, a decoy otherwise.
     */
    private function startFlow(string $email): void
    {
        $this->loginCodes()->request($email, request());
    }

    private function loginCodes(): LoginCodes
    {
        return resolve(LoginCodes::class);
    }
}
