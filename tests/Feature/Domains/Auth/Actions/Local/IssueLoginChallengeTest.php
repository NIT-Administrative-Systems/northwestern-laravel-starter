<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Actions\Local;

use App\Domains\Auth\Actions\Local\IssueLoginChallenge;
use App\Domains\Auth\Jobs\SendLoginCodeEmailJob;
use App\Domains\Auth\Models\LoginChallenge;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\TestCase;

#[CoversClass(IssueLoginChallenge::class)]
class IssueLoginChallengeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.local.rate_limit_per_hour' => 5]);
        config(['auth.local.code.expires_in_minutes' => 15]);

        CarbonImmutable::setTestNow();
        Queue::fake();
        RateLimiter::clear('login-code:test@example.com');
    }

    public function test_issues_login_challenge_and_queues_mail(): void
    {
        $challenge = $this->action()('test@example.com', '192.168.1.1', 'TestAgent/1.0');

        $this->assertEquals('test@example.com', $challenge->email);
        $this->assertEquals('192.168.1.1', $challenge->requested_ip);
        $this->assertNotNull($challenge->code_hash);
        $this->assertTrue($challenge->expires_at->between(now()->addMinutes(15)->subSecond(), now()->addMinutes(15)->addSecond()));

        Queue::assertPushed(SendLoginCodeEmailJob::class, function ($job) use ($challenge) {
            $code = Crypt::decryptString($job->encryptedCode);

            return $job->loginChallengeId === $challenge->id
                && Hash::check($code, $challenge->code_hash);
        });
    }

    public function test_rate_limiting_prevents_multiple_requests(): void
    {
        config(['auth.local.rate_limit_per_hour' => 2]);

        RateLimiter::clear('login-code:ratelimit@example.com');

        $this->action()('ratelimit@example.com', null, null);
        $this->action()('ratelimit@example.com', null, null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Too many login attempts.');

        $this->action()('ratelimit@example.com', null, null);
    }

    public function test_rate_limit_message_includes_minutes(): void
    {
        config(['auth.local.rate_limit_per_hour' => 1]);

        RateLimiter::clear('login-code:ratelimit@example.com');

        $this->action()('ratelimit@example.com', null, null);

        try {
            $this->action()('ratelimit@example.com', null, null);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('minute(s)', $e->getMessage());
            $this->assertMatchesRegularExpression('/\d+\s+minute\(s\)/', $e->getMessage());
        }
    }

    public function test_rate_limiting_does_not_affect_different_emails(): void
    {
        config(['auth.local.rate_limit_per_hour' => 1]);

        RateLimiter::clear('login-code:user1@example.com');
        RateLimiter::clear('login-code:user2@example.com');

        $this->action()('user1@example.com', null, null);
        $challenge = $this->action()('user2@example.com', null, null);

        $this->assertInstanceOf(LoginChallenge::class, $challenge);
    }

    public function test_can_handle_null_ip_address(): void
    {
        $challenge = $this->action()('test@example.com', null, null);

        $this->assertNull($challenge->requested_ip);
    }

    public function test_creates_multiple_challenges_for_same_email(): void
    {
        RateLimiter::clear('login-code:multi@example.com');
        $challenge1 = $this->action()('multi@example.com', null, null);
        $challenge2 = $this->action()('multi@example.com', null, null);

        $this->assertNotEquals($challenge1->id, $challenge2->id);
        $this->assertNotEquals($challenge1->code_hash, $challenge2->code_hash);
    }

    public function test_rate_limiter_is_hit_after_successful_creation(): void
    {
        $rateLimitKey = 'login-code:test@example.com';

        $this->assertEquals(0, RateLimiter::attempts($rateLimitKey));

        $this->action()('test@example.com', null, null);

        $this->assertEquals(1, RateLimiter::attempts($rateLimitKey));
    }

    protected function action(): IssueLoginChallenge
    {
        return resolve(IssueLoginChallenge::class);
    }
}
