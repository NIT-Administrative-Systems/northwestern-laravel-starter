<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Actions\Local;

use App\Domains\Auth\Actions\Local\AuthenticateWithLoginCode;
use App\Domains\Auth\Models\LoginChallenge;
use App\Domains\User\Enums\UserSegment;
use App\Domains\User\Models\User;
use App\Domains\User\Models\UserLoginRecord;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AuthenticateWithLoginCode::class)]
final class AuthenticateWithLoginCodeTest extends TestCase
{
    /** @var list<int> The minimum durations, in microseconds, the action asked the timebox for. */
    private array $timeboxMinimums = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(Timebox::class, function ($mock) {
            $mock->shouldReceive('call')->andReturnUsing(function (callable $callback, int $microseconds) {
                $this->timeboxMinimums[] = $microseconds;

                return $callback(new Timebox());
            });
        });

        config(['local-auth.code.max_attempts' => 5]);
        config(['rate-limiting.auth.login_code.verify.per_minute' => 50]);
        config(['rate-limiting.auth.login_code.verify.per_challenge_per_minute' => 50]);
    }

    public function test_valid_code_returns_the_user_and_records_the_login(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);
        $challenge = $this->challengeFor($user->email, '123456');

        $authenticated = $this->authenticate((string) $challenge->id, '123456');

        $this->assertTrue($authenticated->is($user));
        $this->assertSame(1, UserLoginRecord::count());
        $this->assertSame(UserSegment::ExternalUser, UserLoginRecord::first()->segment);
    }

    public function test_valid_code_marks_the_email_verified_when_missing(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'unverified@example.com', 'email_verified_at' => null]);
        $challenge = $this->challengeFor($user->email, '123456');

        $this->authenticate((string) $challenge->id, '123456');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_invalid_code_is_rejected(): void
    {
        $user = User::factory()->affiliate()->create();
        $challenge = $this->challengeFor($user->email, '123456');

        $this->assertRejectedWith("That code didn't work", fn () => $this->authenticate((string) $challenge->id, '000000'));
    }

    public function test_missing_challenge_id_is_rejected(): void
    {
        $this->assertRejectedWith("That code didn't work", fn () => $this->authenticate(null, '123456'));
    }

    public function test_uuid_decoy_challenge_id_is_rejected(): void
    {
        $this->assertRejectedWith("That code didn't work", fn () => $this->authenticate(Str::uuid()->toString(), '123456'));
    }

    public function test_non_numeric_challenge_id_is_rejected_without_a_database_error(): void
    {
        $this->assertRejectedWith("That code didn't work", fn () => $this->authenticate('not-a-valid-id', '123456'));
    }

    public function test_locked_challenge_is_rejected_with_the_lockout_message(): void
    {
        config(['local-auth.code.lock_minutes' => 15]);
        $user = User::factory()->affiliate()->create();
        $challenge = $this->challengeFor($user->email, '123456', lockedUntil: now()->addMinutes(5));

        $this->assertRejectedWith('Too many attempts', fn () => $this->authenticate((string) $challenge->id, '123456'));
    }

    public function test_challenge_for_a_deleted_user_is_rejected(): void
    {
        $challenge = $this->challengeFor('missing-user@example.com', '123456');

        $this->assertRejectedWith("That code didn't work", fn () => $this->authenticate((string) $challenge->id, '123456'));
    }

    public function test_wrong_codes_lock_the_challenge_at_the_attempt_limit(): void
    {
        $user = User::factory()->affiliate()->create();
        $challenge = $this->challengeFor($user->email, '123456');

        foreach (range(1, 5) as $attempt) {
            $this->assertRejectedWith("That code didn't work", fn () => $this->authenticate((string) $challenge->id, '000000'));
        }

        $challenge->refresh();
        $this->assertSame(5, $challenge->attempts);
        $this->assertNotNull($challenge->locked_until);
        $this->assertRejectedWith('Too many attempts', fn () => $this->authenticate((string) $challenge->id, '123456'));
    }

    public function test_real_and_decoy_challenges_take_the_same_minimum_time(): void
    {
        $user = User::factory()->affiliate()->create();
        $challenge = $this->challengeFor($user->email, '123456');

        $this->assertRejectedWith("That code didn't work", fn () => $this->authenticate((string) $challenge->id, '000000'));
        $this->assertRejectedWith("That code didn't work", fn () => $this->authenticate(Str::uuid()->toString(), '000000'));

        $this->assertCount(2, $this->timeboxMinimums);
        foreach ($this->timeboxMinimums as $microseconds) {
            $this->assertGreaterThanOrEqual(500_000, $microseconds);
        }
    }

    public function test_enforces_the_per_ip_per_minute_limit(): void
    {
        config(['rate-limiting.auth.login_code.verify.per_minute' => 1]);

        $this->assertRejectedWith("That code didn't work", fn () => $this->authenticate(null, '123456'));
        $this->assertRejectedWith('Too many attempts', fn () => $this->authenticate(null, '123456'));
    }

    public function test_enforces_the_per_challenge_per_minute_limit(): void
    {
        config(['rate-limiting.auth.login_code.verify.per_challenge_per_minute' => 1]);
        $user = User::factory()->affiliate()->create();
        $challenge = $this->challengeFor($user->email, '123456');

        $this->assertRejectedWith("That code didn't work", fn () => $this->authenticate((string) $challenge->id, '000000'));
        $this->assertRejectedWith('Too many attempts', fn () => $this->authenticate((string) $challenge->id, '123456', ip: '192.168.1.100'));
    }

    private function challengeFor(string $email, string $code, ?CarbonInterface $lockedUntil = null): LoginChallenge
    {
        return LoginChallenge::create([
            'email' => $email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
            'locked_until' => $lockedUntil,
        ]);
    }

    private function authenticate(?string $challengeId, string $code, string $ip = '127.0.0.1'): User
    {
        $request = Request::create('/', server: ['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => 'TestAgent/1.0']);

        return resolve(AuthenticateWithLoginCode::class)($challengeId, $code, $request);
    }

    private function assertRejectedWith(string $message, callable $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            $this->assertStringContainsString($message, (string) ($e->errors()['code'][0] ?? ''));

            return;
        }

        $this->fail('Expected the code to be rejected.');
    }
}
