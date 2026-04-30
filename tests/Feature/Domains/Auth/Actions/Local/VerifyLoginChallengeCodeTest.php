<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Actions\Local;

use App\Domains\Auth\Actions\Local\VerifyLoginChallengeCode;
use App\Domains\Auth\Models\LoginChallenge;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(VerifyLoginChallengeCode::class)]
final class VerifyLoginChallengeCodeTest extends TestCase
{
    public function test_verifies_correct_code_and_consumes_challenge(): void
    {
        $code = '123456';
        $challenge = LoginChallenge::create([
            'email' => 'test@example.com',
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        $ok = $this->action()($challenge, $code, '203.0.113.10', 'TestAgent');

        $this->assertTrue($ok);
        $this->assertNotNull($challenge->fresh()->consumed_at);
        $this->assertEquals('203.0.113.10', $challenge->fresh()->consumed_ip);
    }

    public function test_invalid_code_increments_attempts(): void
    {
        $challenge = LoginChallenge::create([
            'email' => 'test@example.com',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $ok = $this->action()($challenge, '000000', null, null);

        $this->assertFalse($ok);
        $this->assertEquals(1, $challenge->fresh()->attempts);
    }

    public function test_does_not_lock_challenge_below_max_attempts(): void
    {
        config(['local-auth.code.max_attempts' => 3]);

        $challenge = LoginChallenge::create([
            'email' => 'test@example.com',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->action()($challenge, '000000', null, null);
        $this->action()($challenge, '000000', null, null);

        $fresh = $challenge->fresh();
        $this->assertEquals(2, $fresh->attempts);
        $this->assertNull($fresh->locked_until);
    }

    public function test_locks_challenge_exactly_at_max_attempts(): void
    {
        config(['local-auth.code.max_attempts' => 3]);
        config(['local-auth.code.lock_minutes' => 15]);

        $challenge = LoginChallenge::create([
            'email' => 'test@example.com',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        // 3 failures — exactly at the threshold
        $this->action()($challenge, '000000', null, null);
        $this->action()($challenge, '000000', null, null);
        $this->action()($challenge, '000000', null, null);

        $fresh = $challenge->fresh();
        $this->assertEquals(3, $fresh->attempts);
        $this->assertNotNull($fresh->locked_until);
        $this->assertTrue($fresh->locked_until->isFuture());
    }

    public function test_lock_duration_uses_configured_minutes(): void
    {
        config(['local-auth.code.max_attempts' => 1]);
        config(['local-auth.code.lock_minutes' => 30]);

        $challenge = LoginChallenge::create([
            'email' => 'test@example.com',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->action()($challenge, '000000', null, null);

        $fresh = $challenge->fresh();
        $this->assertNotNull($fresh->locked_until);

        $this->assertTrue($fresh->locked_until->isAfter(now()->addMinutes(29)));
        $this->assertTrue($fresh->locked_until->isBefore(now()->addMinutes(31)));
    }

    public function test_returns_false_for_expired_challenge(): void
    {
        $challenge = LoginChallenge::create([
            'email' => 'test@example.com',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->subMinute(),
        ]);

        $ok = $this->action()($challenge, '123456', null, null);

        $this->assertFalse($ok);
    }

    public function test_returns_false_for_consumed_challenge(): void
    {
        $challenge = LoginChallenge::create([
            'email' => 'test@example.com',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => CarbonImmutable::now(),
        ]);

        $ok = $this->action()($challenge, '123456', null, null);

        $this->assertFalse($ok);
    }

    protected function action(): VerifyLoginChallengeCode
    {
        return resolve(VerifyLoginChallengeCode::class);
    }
}
