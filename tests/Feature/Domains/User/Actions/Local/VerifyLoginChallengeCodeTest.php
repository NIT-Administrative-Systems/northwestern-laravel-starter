<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Actions\Local;

use App\Domains\User\Actions\Local\VerifyLoginChallengeCode;
use App\Domains\User\Models\LoginChallenge;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(VerifyLoginChallengeCode::class)]
class VerifyLoginChallengeCodeTest extends TestCase
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

    public function test_locks_challenge_after_max_attempts(): void
    {
        config(['auth.local.code.max_attempts' => 2]);
        config(['auth.local.code.lock_minutes' => 15]);

        $challenge = LoginChallenge::create([
            'email' => 'test@example.com',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->action()($challenge, '000000', null, null);
        $this->action()($challenge, '000000', null, null);

        $fresh = $challenge->fresh();
        $this->assertNotNull($fresh->locked_until);
        $this->assertTrue($fresh->locked_until->isFuture());
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
