<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Actions;

use App\Domains\User\Actions\SendLoginLink;
use App\Domains\User\Mail\LoginLinkNotification;
use App\Domains\User\Models\User;
use App\Domains\User\Models\UserLoginLink;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\TestCase;

#[CoversClass(SendLoginLink::class)]
class SendLoginLinkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.local.enabled' => true]);
        config(['auth.local.login_link_expiration_minutes' => 15]);
        config(['auth.local.rate_limit_per_hour' => 5]);

        CarbonImmutable::setTestNow();
        Mail::fake();
        RateLimiter::clear('login-link:test@example.com');
    }

    public function test_sends_login_link_to_local_user(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);

        $loginLink = $this->action()($user, '192.168.1.1');

        $this->assertEquals($user->id, $loginLink->user_id);
        $this->assertEquals('test@example.com', $loginLink->email);
        $this->assertEquals('192.168.1.1', $loginLink->requested_ip_address);
        $this->assertNotNull($loginLink->token);
        $this->assertNotNull($loginLink->expires_at);
        $this->assertNull($loginLink->used_at);

        Mail::assertQueued(LoginLinkNotification::class, function ($mail) {
            return $mail->hasTo('test@example.com');
        });
    }

    public function test_throws_exception_for_non_local_user(): void
    {
        $user = User::factory()->create(['email' => 'sso-user@northwestern.edu']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Login links can only be sent to local users.');

        $this->action()($user);
    }

    public function test_stores_hashed_token_in_database(): void
    {
        $user = User::factory()->affiliate()->create();

        $loginLink = $this->action()($user);

        $this->assertEquals(64, strlen($loginLink->token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $loginLink->token);
    }

    public function test_login_link_expires_after_configured_minutes(): void
    {
        config(['auth.local.login_link_expiration_minutes' => 30]);

        $user = User::factory()->affiliate()->create();

        $loginLink = $this->action()($user);

        $this->assertTrue($loginLink->expires_at->between(now()->addMinutes(30)->subSecond(), now()->addMinutes(30)->addSecond()));
    }

    public function test_rate_limiting_prevents_multiple_requests(): void
    {
        config(['auth.local.rate_limit_per_hour' => 3]);

        $user = User::factory()->affiliate()->create(['email' => 'ratelimit@example.com']);

        $this->action()($user);
        $this->action()($user);
        $this->action()($user);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Too many login attempts.');

        $this->action()($user);
    }

    public function test_rate_limit_message_includes_minutes(): void
    {
        config(['auth.local.rate_limit_per_hour' => 1]);

        $user = User::factory()->affiliate()->create(['email' => 'ratelimit@example.com']);

        $this->action()($user);

        try {
            $this->action()($user);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('minute(s)', $e->getMessage());
            $this->assertMatchesRegularExpression('/\d+\s+minute\(s\)/', $e->getMessage());
        }
    }

    public function test_rate_limiting_does_not_affect_different_users(): void
    {
        config(['auth.local.rate_limit_per_hour' => 1]);

        $user1 = User::factory()->affiliate()->create(['email' => 'user1@example.com']);
        $user2 = User::factory()->affiliate()->create(['email' => 'user2@example.com']);

        // First user hits rate limit
        $this->action()($user1);

        // Second user should still work
        $loginLink = $this->action()($user2);
        $this->assertInstanceOf(UserLoginLink::class, $loginLink);
    }

    public function test_can_handle_null_ip_address(): void
    {
        $user = User::factory()->affiliate()->create();

        $loginLink = $this->action()($user, null);

        $this->assertNull($loginLink->requested_ip_address);
    }

    public function test_creates_multiple_login_links_for_same_user(): void
    {
        $user = User::factory()->affiliate()->create();

        $link1 = $this->action()($user);
        $link2 = $this->action()($user);

        $this->assertNotEquals($link1->id, $link2->id);
        $this->assertNotEquals($link1->token, $link2->token);
        $this->assertEquals(2, $user->login_links()->count());
    }

    public function test_rate_limit_uses_configured_value(): void
    {
        config(['auth.local.rate_limit_per_hour' => 10]);

        $user = User::factory()->affiliate()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->action()($user);
        }

        $this->expectException(RuntimeException::class);
        $this->action()($user);
    }

    public function test_mail_is_queued_not_sent_immediately(): void
    {
        $user = User::factory()->affiliate()->create([
            'email' => 'queued@example.com',
        ]);

        $this->action()($user);

        Mail::assertQueued(LoginLinkNotification::class);
        Mail::assertNothingSent();
    }

    public function test_rate_limiter_is_hit_after_successful_creation(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);

        $rateLimitKey = "login-link:{$user->email}";

        $this->assertEquals(0, RateLimiter::attempts($rateLimitKey));

        $this->action()($user);

        $this->assertEquals(1, RateLimiter::attempts($rateLimitKey));
    }

    protected function action(): SendLoginLink
    {
        return resolve(SendLoginLink::class);
    }
}
