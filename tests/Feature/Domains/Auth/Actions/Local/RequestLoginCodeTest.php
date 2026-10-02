<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Actions\Local;

use App\Domains\Auth\Actions\Local\IssueLoginChallenge;
use App\Domains\Auth\Actions\Local\RequestLoginCode;
use App\Domains\Auth\Models\LoginChallenge;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\TestCase;

#[CoversClass(RequestLoginCode::class)]
final class RequestLoginCodeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['local-auth.rate_limit_per_hour' => 5]);
        config(['local-auth.rate_limit_per_ip_per_hour' => 20]);
        config(['rate-limiting.auth.login_code.request.per_minute' => 50]);
        config(['rate-limiting.auth.login_code.request.per_email_per_minute' => 50]);

        Mail::fake();

        $this->mock(Timebox::class, function ($mock) {
            $mock->shouldReceive('call')->andReturnUsing(
                fn (callable $callback, int $microseconds) => $callback(new Timebox())
            );
        });
    }

    public function test_issues_a_challenge_for_an_existing_local_user(): void
    {
        User::factory()->affiliate()->create(['email' => 'test@example.com']);

        $challenge = $this->request('Test@Example.com');

        $this->assertInstanceOf(LoginChallenge::class, $challenge);
        $this->assertSame('test@example.com', $challenge->email);
    }

    public function test_returns_null_for_an_unknown_email(): void
    {
        $this->assertNull($this->request('missing@example.com'));
        $this->assertSame(0, LoginChallenge::count());
    }

    public function test_unknown_emails_consume_the_per_email_hourly_limit(): void
    {
        $this->request('missing@example.com');

        $this->assertSame(1, RateLimiter::attempts('login-code:missing@example.com'));
    }

    public function test_per_email_hourly_limit_applies_to_existing_users(): void
    {
        config(['local-auth.rate_limit_per_hour' => 1]);
        User::factory()->affiliate()->create(['email' => 'test@example.com']);
        RateLimiter::hit('login-code:test@example.com', 3600);

        $this->assertRejectedWith('Too many login attempts', fn () => $this->request('test@example.com'));
    }

    public function test_per_email_hourly_limit_applies_identically_to_unknown_emails(): void
    {
        config(['local-auth.rate_limit_per_hour' => 1]);
        RateLimiter::hit('login-code:nonexistent@example.com', 3600);

        $this->assertRejectedWith('Too many login attempts', fn () => $this->request('nonexistent@example.com'));
    }

    public function test_enforces_the_per_ip_hourly_limit(): void
    {
        config(['local-auth.rate_limit_per_ip_per_hour' => 2]);

        $this->request('user1@example.com');
        $this->request('user2@example.com');

        $this->assertRejectedWith('Too many login attempts', fn () => $this->request('user3@example.com'));
    }

    public function test_per_ip_hourly_limit_does_not_block_other_ips(): void
    {
        config(['local-auth.rate_limit_per_ip_per_hour' => 1]);
        User::factory()->affiliate()->create(['email' => 'user2@example.com']);

        $this->request('user1@example.com');

        $this->assertInstanceOf(LoginChallenge::class, $this->request('user2@example.com', ip: '192.168.1.100'));
    }

    public function test_enforces_the_per_ip_per_minute_limit(): void
    {
        config(['rate-limiting.auth.login_code.request.per_minute' => 1]);

        $this->request('user1@example.com');

        $this->assertRejectedWith('Too many requests', fn () => $this->request('user2@example.com'));
    }

    public function test_enforces_the_per_email_per_minute_limit(): void
    {
        config(['rate-limiting.auth.login_code.request.per_email_per_minute' => 1]);

        $this->request('user@example.com');

        $this->assertRejectedWith('Too many requests', fn () => $this->request('user@example.com', ip: '192.168.1.100'));
    }

    public function test_surfaces_challenge_issuer_failures_as_email_errors(): void
    {
        User::factory()->affiliate()->create(['email' => 'test@example.com']);

        $this->mock(IssueLoginChallenge::class, function ($mock) {
            $mock->shouldReceive('__invoke')
                ->once()
                ->andThrow(new RuntimeException('Too many login attempts. Please try again in 1 minute(s).'));
        });

        $this->assertRejectedWith('Too many login attempts', fn () => $this->request('test@example.com'));
    }

    private function request(string $email, string $ip = '127.0.0.1'): ?LoginChallenge
    {
        return resolve(RequestLoginCode::class)($email, $ip, 'TestAgent/1.0');
    }

    private function assertRejectedWith(string $message, callable $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            $this->assertStringContainsString($message, (string) ($e->errors()['email'][0] ?? ''));

            return;
        }

        $this->fail('Expected the request to be rejected.');
    }
}
