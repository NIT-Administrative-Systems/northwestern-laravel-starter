<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Controllers\Local;

use App\Domains\Auth\Http\Controllers\Local\VerifyLoginCodeController;
use App\Domains\Auth\Models\LoginChallenge;
use App\Domains\Auth\ValueObjects\LoginCodeSession;
use App\Domains\User\Enums\UserSegment;
use App\Domains\User\Models\User;
use App\Domains\User\Models\UserLoginRecord;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(VerifyLoginCodeController::class)]
class VerifyLoginCodeControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['local-auth.enabled' => true]);
        config(['local-auth.code.max_attempts' => 5]);
        config(['local-auth.code.digits' => 6]);

        RateLimiter::clear('login-code:form:' . session()->getId());
    }

    public function test_valid_code_authenticates_user_and_clears_session(): void
    {
        $user = User::factory()->affiliate()->create(['email' => 'test@example.com']);
        $code = '123456';

        $challenge = LoginChallenge::create([
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->withSession([
            LoginCodeSession::EMAIL => $user->email,
            LoginCodeSession::CHALLENGE_ID => Crypt::encryptString((string) $challenge->id),
        ]);

        $response = $this->post(route('login-code.verify'), ['code' => $code]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertEquals(1, UserLoginRecord::count());
        $loginRecord = UserLoginRecord::first();
        $this->assertEquals(UserSegment::ExternalUser, $loginRecord->segment);
        $this->assertNotNull($loginRecord->ip_address);
        $this->assertNotNull($loginRecord->user_agent);
        foreach (LoginCodeSession::KEYS as $key) {
            $this->assertFalse(session()->has($key));
        }
    }

    public function test_invalid_code_returns_error(): void
    {
        $user = User::factory()->affiliate()->create();
        $challenge = LoginChallenge::create([
            'email' => $user->email,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->withSession([
            LoginCodeSession::EMAIL => $user->email,
            LoginCodeSession::CHALLENGE_ID => Crypt::encryptString((string) $challenge->id),
        ]);

        $response = $this->post(route('login-code.verify'), ['code' => '000000']);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_route_returns_404_when_disabled(): void
    {
        config(['local-auth.enabled' => false]);

        $response = $this->post(route('login-code.verify'), ['code' => '123456']);

        $response->assertNotFound();
    }

    public function test_handles_uuid_decoy_challenge_id_gracefully(): void
    {
        $uuidDecoy = Str::uuid()->toString();

        $this->withSession([
            LoginCodeSession::EMAIL => 'nonexistent@example.com',
            LoginCodeSession::CHALLENGE_ID => Crypt::encryptString($uuidDecoy),
        ]);

        $response = $this->post(route('login-code.verify'), ['code' => '123456']);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_handles_non_numeric_challenge_id_without_database_error(): void
    {
        $this->withSession([
            LoginCodeSession::EMAIL => 'test@example.com',
            LoginCodeSession::CHALLENGE_ID => Crypt::encryptString('not-a-valid-id'),
        ]);

        $response = $this->post(route('login-code.verify'), ['code' => '123456']);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_returns_error_when_challenge_id_is_missing_from_session(): void
    {
        $response = $this->post(route('login-code.verify'), ['code' => '123456']);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_clears_session_and_returns_error_when_challenge_id_cannot_be_decrypted(): void
    {
        $response = $this->withSession([
            LoginCodeSession::EMAIL => 'test@example.com',
            LoginCodeSession::CHALLENGE_ID => 'invalid-ciphertext',
        ])->post(route('login-code.verify'), ['code' => '123456']);

        $response->assertSessionHasErrors('code');
        $response->assertSessionMissing(LoginCodeSession::CHALLENGE_ID);
        $this->assertGuest();
    }

    public function test_returns_lockout_error_when_challenge_is_locked(): void
    {
        config(['local-auth.code.lock_minutes' => 15]);

        $user = User::factory()->affiliate()->create(['email' => 'locked@example.com']);
        $challenge = LoginChallenge::create([
            'email' => $user->email,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
            'locked_until' => now()->addMinutes(5),
        ]);

        $response = $this->withSession([
            LoginCodeSession::EMAIL => $user->email,
            LoginCodeSession::CHALLENGE_ID => Crypt::encryptString((string) $challenge->id),
        ])->post(route('login-code.verify'), ['code' => '123456']);

        $response->assertSessionHasErrors('code');
        $this->assertStringContainsString('Too many attempts', (string) session('errors')->first('code'));
        $this->assertGuest();
    }

    public function test_returns_invalid_code_when_challenge_user_no_longer_exists(): void
    {
        $challenge = LoginChallenge::create([
            'email' => 'missing-user@example.com',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->withSession([
            LoginCodeSession::EMAIL => 'missing-user@example.com',
            LoginCodeSession::CHALLENGE_ID => Crypt::encryptString((string) $challenge->id),
        ])->post(route('login-code.verify'), ['code' => '123456']);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_valid_code_marks_email_as_verified_when_missing(): void
    {
        $user = User::factory()->affiliate()->create([
            'email' => 'unverified@example.com',
            'email_verified_at' => null,
        ]);

        $challenge = LoginChallenge::create([
            'email' => $user->email,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->withSession([
            LoginCodeSession::EMAIL => $user->email,
            LoginCodeSession::CHALLENGE_ID => Crypt::encryptString((string) $challenge->id),
        ])->post(route('login-code.verify'), ['code' => '123456']);

        $response->assertRedirect('/');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
