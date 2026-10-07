<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth;

use App\Domains\Auth\Contracts\OneTimeCodeGenerator;
use App\Domains\Auth\Jobs\SendLoginCodeEmailJob;
use App\Domains\Auth\LoginCodes;
use App\Domains\Auth\Models\LoginChallenge;
use App\Domains\User\Models\User;
use App\Domains\User\Models\UserLoginRecord;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LoginCodes::class)]
final class LoginCodesTest extends TestCase
{
    /** @var list<int> The minimum durations, in microseconds, asked of the timebox. */
    private array $timeboxMinimums = [];

    /** @var list<string> The codes still to hand out, in order; 123456 once they run out. */
    private array $codes = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'local-auth.rate_limit_per_hour' => 5,
            'local-auth.rate_limit_per_ip_per_hour' => 20,
            'local-auth.code.digits' => 6,
            'local-auth.code.expires_in_minutes' => 15,
            'local-auth.code.max_attempts' => 5,
            'local-auth.code.lock_minutes' => 15,
            'local-auth.code.resend_cooldown_seconds' => 30,
            'rate-limiting.auth.login_code.request.per_minute' => 50,
            'rate-limiting.auth.login_code.request.per_email_per_minute' => 50,
            'rate-limiting.auth.login_code.verify.per_minute' => 50,
            'rate-limiting.auth.login_code.verify.per_challenge_per_minute' => 50,
        ]);

        Queue::fake();
        $this->freezeTime();

        $this->mock(Timebox::class, function ($mock) {
            $mock->shouldReceive('call')->andReturnUsing(function (callable $callback, int $microseconds) {
                $this->timeboxMinimums[] = $microseconds;

                return $callback(new Timebox());
            });
        });

        $this->app->instance(OneTimeCodeGenerator::class, new readonly class(fn (): string => array_shift($this->codes) ?? '123456') implements OneTimeCodeGenerator
        {
            /** @param  Closure(): string  $next */
            public function __construct(private Closure $next)
            {
            }

            public function __invoke(int $digits): string
            {
                return ($this->next)();
            }
        });
    }

    public function test_a_local_account_gets_a_code_that_signs_it_in(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com', 'email_verified_at' => null]);

        $this->codes()->request('  Test@Example.COM ', $this->request(userAgent: str_repeat('a', 600)));

        $challenge = LoginChallenge::query()->sole();
        $this->assertSame('test@example.com', $challenge->email);
        $this->assertSame('127.0.0.1', $challenge->requested_ip);
        $this->assertSame(512, strlen((string) $challenge->requested_user_agent));
        $this->assertSame(now()->addMinutes(15)->toDateTimeString(), $challenge->expires_at->toDateTimeString());
        $this->assertSame('test@example.com', $this->codes()->pendingEmail());
        Queue::assertPushed(SendLoginCodeEmailJob::class, fn (SendLoginCodeEmailJob $job): bool => $job->loginChallengeId === $challenge->id
            && Hash::check(Crypt::decryptString($job->encryptedCode), $challenge->code_hash));

        $signedIn = $this->codes()->verify('123456', $this->request());

        $this->assertTrue($signedIn->is($user));
        $this->assertNotNull($user->fresh()?->email_verified_at);
        $this->assertNotNull($challenge->fresh()?->consumed_at);
        $this->assertNull($this->codes()->pendingEmail());
        // Recording the sign-in is SignIn::complete()'s, so every method records it the same way.
        $this->assertSame(0, UserLoginRecord::query()->count());
    }

    // An unknown email reaches the same code step, so a request can't tell whether it has an account.
    public function test_an_unknown_email_gets_the_same_code_step_and_no_code(): void
    {
        $this->codes()->request('missing@example.com', $this->request());

        $this->assertSame(0, LoginChallenge::query()->count());
        $this->assertSame('missing@example.com', $this->codes()->pendingEmail());
        $this->assertSame(1, RateLimiter::attempts('login-code:missing@example.com'));
        $this->assertRejected('code', 'That code didn\'t work', fn () => $this->codes()->verify('123456', $this->request()));
    }

    public function test_requests_and_checks_take_the_same_minimum_time_for_known_and_unknown_emails(): void
    {
        User::factory()->affiliate()->create(['email' => 'known@example.com']);

        $this->codes()->request('known@example.com', $this->request());
        $this->assertRejected('code', 'That code didn\'t work', fn () => $this->codes()->verify('000000', $this->request()));
        $this->codes()->request('missing@example.com', $this->request());
        $this->assertRejected('code', 'That code didn\'t work', fn () => $this->codes()->verify('000000', $this->request()));

        $this->assertCount(4, $this->timeboxMinimums);

        foreach ($this->timeboxMinimums as $microseconds) {
            $this->assertGreaterThanOrEqual(500_000, $microseconds);
        }
    }

    // A known email used to have its hourly limit checked twice in one request; it's counted once.
    public function test_a_request_counts_once_against_the_hourly_limit(): void
    {
        User::factory()->affiliate()->create(['email' => 'test@example.com']);

        $this->codes()->request('test@example.com', $this->request());

        $this->assertSame(1, RateLimiter::attempts('login-code:test@example.com'));
    }

    public function test_the_hourly_limit_refuses_known_and_unknown_emails_alike(): void
    {
        config(['local-auth.rate_limit_per_hour' => 1]);
        User::factory()->affiliate()->create(['email' => 'known@example.com']);
        RateLimiter::hit('login-code:known@example.com', 3600);
        RateLimiter::hit('login-code:missing@example.com', 3600);

        foreach (['known@example.com', 'missing@example.com'] as $email) {
            // A real plural, with one through nine spelled out: "in 60 minutes", "in one minute".
            $this->assertRejected('email', 'Too many sign-in attempts', fn () => $this->codes()->request($email, $this->request()));
        }

        $this->assertSame(0, LoginChallenge::query()->count());
    }

    public function test_the_hourly_limit_per_ip_stops_one_address_using_up_many_accounts(): void
    {
        config(['local-auth.rate_limit_per_ip_per_hour' => 2]);
        User::factory()->affiliate()->create(['email' => 'user4@example.com']);

        $this->codes()->request('user1@example.com', $this->request());
        $this->codes()->request('user2@example.com', $this->request());

        $this->assertRejected('email', 'Too many sign-in attempts', fn () => $this->codes()->request('user3@example.com', $this->request()));

        $this->codes()->request('user4@example.com', $this->request(ip: '192.168.1.100'));
        $this->assertSame(1, LoginChallenge::query()->count());
    }

    public function test_requests_are_limited_per_minute_by_ip_and_by_email(): void
    {
        config(['rate-limiting.auth.login_code.request.per_minute' => 1]);
        $this->codes()->request('user1@example.com', $this->request());
        $this->assertRejected('email', 'Too many requests', fn () => $this->codes()->request('user2@example.com', $this->request()));

        config(['rate-limiting.auth.login_code.request.per_minute' => 50, 'rate-limiting.auth.login_code.request.per_email_per_minute' => 1]);
        $this->assertRejected('email', 'Too many requests', fn () => $this->codes()->request('user1@example.com', $this->request(ip: '192.168.1.100')));
    }

    public function test_a_resend_replaces_the_code_to_enter(): void
    {
        $this->codes = ['111111', '222222'];
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);
        $this->codes()->request('test@example.com', $this->request());

        $this->codes()->resend($this->request());

        $this->assertSame(2, LoginChallenge::query()->count());
        $this->assertRejected('code', 'That code didn\'t work', fn () => $this->codes()->verify('111111', $this->request()));
        $this->assertTrue($this->codes()->verify('222222', $this->request())->is($user));
    }

    public function test_resends_wait_for_the_cooldown(): void
    {
        $this->codes()->request('missing@example.com', $this->request());
        $this->assertSame(0, $this->codes()->resendAvailableIn());

        $this->codes()->resend($this->request());

        $this->assertGreaterThan(0, $this->codes()->resendAvailableIn());
        $this->assertRejected('email', 'You can request another code in', fn () => $this->codes()->resend($this->request()));
    }

    public function test_a_resend_for_an_unknown_email_keeps_its_decoy(): void
    {
        $this->codes()->request('missing@example.com', $this->request());

        $this->codes()->resend($this->request());

        $this->assertSame(0, LoginChallenge::query()->count());
        $this->assertSame('missing@example.com', $this->codes()->pendingEmail());
        $this->assertRejected('code', 'That code didn\'t work', fn () => $this->codes()->verify('123456', $this->request()));
    }

    public function test_a_resend_is_refused_by_the_request_limits(): void
    {
        config(['local-auth.rate_limit_per_hour' => 1]);
        $this->codes()->request('missing@example.com', $this->request());

        $this->assertRejected('email', 'Too many sign-in attempts', fn () => $this->codes()->resend($this->request()));
    }

    public function test_a_resend_without_a_pending_sign_in_does_nothing(): void
    {
        $this->codes()->resend($this->request());

        $this->assertSame(0, $this->codes()->resendAvailableIn());
        $this->assertSame(0, LoginChallenge::query()->count());
    }

    public function test_wrong_codes_lock_the_challenge_at_the_attempt_limit(): void
    {
        User::factory()->affiliate()->create(['email' => 'test@example.com']);
        $this->codes()->request('test@example.com', $this->request());

        foreach (range(1, 4) as $attempt) {
            $this->assertRejected('code', 'That code didn\'t work', fn () => $this->codes()->verify('000000', $this->request()));
        }

        $this->assertNull(LoginChallenge::query()->sole()->locked_until);
        $this->assertRejected('code', 'That code didn\'t work', fn () => $this->codes()->verify('000000', $this->request()));

        $challenge = LoginChallenge::query()->sole();
        $this->assertSame(5, $challenge->attempts);
        $this->assertSame(now()->addMinutes(15)->toDateTimeString(), $challenge->locked_until?->toDateTimeString());
        $this->assertRejected('code', 'Too many attempts. Try again in 15 minutes.', fn () => $this->codes()->verify('123456', $this->request()));
    }

    public function test_an_expired_or_used_code_is_rejected(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);
        $this->codes()->request('test@example.com', $this->request());
        LoginChallenge::query()->sole()->update(['expires_at' => now()->subMinute()]);

        $this->assertRejected('code', 'That code didn\'t work', fn () => $this->codes()->verify('123456', $this->request()));

        $this->codes()->request('test@example.com', $this->request());
        $this->assertTrue($this->codes()->verify('123456', $this->request())->is($user));
        $this->codes()->startFromLink($this->linkToken(LoginChallenge::query()->latest('id')->firstOrFail()));
        $this->assertNull($this->codes()->pendingEmail());
    }

    public function test_a_code_for_an_account_deleted_since_is_rejected(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);
        $this->codes()->request('test@example.com', $this->request());
        $user->forceDelete();

        $this->assertRejected('code', 'That code didn\'t work', fn () => $this->codes()->verify('123456', $this->request()));
    }

    public function test_a_code_with_no_pending_sign_in_or_a_tampered_one_is_rejected(): void
    {
        $this->assertRejected('code', 'That code didn\'t work', fn () => $this->codes()->verify('123456', $this->request()));

        session(['login_code.email' => 'test@example.com', 'login_code.challenge_id' => 'not-encrypted']);

        $this->assertRejected('code', 'That code didn\'t work', fn () => $this->codes()->verify('123456', $this->request()));
        $this->assertFalse(session()->has('login_code.challenge_id'));
    }

    public function test_checks_are_limited_per_minute_by_ip_and_by_challenge(): void
    {
        config(['rate-limiting.auth.login_code.verify.per_minute' => 1]);
        $this->assertRejected('code', 'That code didn\'t work', fn () => $this->codes()->verify('123456', $this->request()));
        $this->assertRejected('code', 'Too many attempts', fn () => $this->codes()->verify('123456', $this->request()));

        config(['rate-limiting.auth.login_code.verify.per_minute' => 50, 'rate-limiting.auth.login_code.verify.per_challenge_per_minute' => 1]);
        User::factory()->affiliate()->create(['email' => 'test@example.com']);
        $this->codes()->request('test@example.com', $this->request());
        $this->assertRejected('code', 'That code didn\'t work', fn () => $this->codes()->verify('000000', $this->request()));
        $this->assertRejected('code', 'Too many attempts', fn () => $this->codes()->verify('123456', $this->request(ip: '192.168.1.100')));
    }

    public function test_an_administrator_sends_a_code_as_their_own_request(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'Partner@Example.com']);

        $challenge = $this->codes()->issueFor($user, $this->request(ip: '203.0.113.42', userAgent: null));

        $this->assertSame('partner@example.com', $challenge->email);
        $this->assertSame('203.0.113.42', $challenge->requested_ip);
        $this->assertNull($challenge->requested_user_agent);
        $this->assertSame(1, RateLimiter::attempts('login-code:partner@example.com'));
        Queue::assertPushed(SendLoginCodeEmailJob::class);
    }

    // An administrator can't flood someone's inbox either.
    public function test_codes_an_administrator_sends_count_against_the_persons_hourly_limit(): void
    {
        config(['local-auth.rate_limit_per_hour' => 1]);
        $user = User::factory()->affiliate()->create(['email' => 'partner@example.com']);
        $this->codes()->issueFor($user, $this->request());

        $this->assertRejected('email', 'Too many sign-in attempts', fn () => $this->codes()->issueFor($user, $this->request()));
    }

    // A code sent by an administrator, or read on another device, has no sign-in pending in this browser.
    public function test_an_emails_link_starts_the_code_step_for_its_challenge(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'partner@example.com']);
        $challenge = $this->codes()->issueFor($user, $this->request());

        $this->codes()->startFromLink($this->linkToken($challenge));

        $this->assertSame('partner@example.com', $this->codes()->pendingEmail());
        $this->assertTrue($this->codes()->verify('123456', $this->request())->is($user));
    }

    public function test_a_link_that_is_tampered_with_or_names_no_challenge_is_ignored(): void
    {
        foreach (['not-a-token', Crypt::encryptString('999999'), Crypt::encryptString(Str::uuid()->toString())] as $token) {
            $this->codes()->startFromLink($token);

            $this->assertNull($this->codes()->pendingEmail());
        }
    }

    public function test_cancelling_ends_the_pending_sign_in(): void
    {
        $this->codes()->request('missing@example.com', $this->request());

        $this->codes()->cancel();

        $this->assertNull($this->codes()->pendingEmail());
    }

    private function codes(): LoginCodes
    {
        return resolve(LoginCodes::class);
    }

    private function request(string $ip = '127.0.0.1', ?string $userAgent = 'TestAgent/1.0'): Request
    {
        $request = Request::create('/', server: ['REMOTE_ADDR' => $ip]);
        $userAgent === null ? $request->headers->remove('User-Agent') : $request->headers->set('User-Agent', $userAgent);

        return $request;
    }

    private function linkToken(LoginChallenge $challenge): string
    {
        parse_str((string) parse_url($this->codes()->link($challenge), PHP_URL_QUERY), $query);

        return (string) $query[LoginCodes::LINK_PARAMETER];
    }

    private function assertRejected(string $field, string $message, callable $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            $this->assertStringContainsString($message, (string) ($e->errors()[$field][0] ?? ''));

            return;
        }

        $this->fail('Expected it to be refused.');
    }
}
