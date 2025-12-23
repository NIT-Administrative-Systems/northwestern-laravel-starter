<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Controllers\Local;

use App\Domains\Auth\Http\Controllers\Local\VerifyLoginCodeController;
use App\Domains\Auth\Models\LoginChallenge;
use App\Domains\Auth\ValueObjects\LoginCodeSession;
use App\Domains\User\Enums\UserSegmentEnum;
use App\Domains\User\Models\User;
use App\Domains\User\Models\UserLoginRecord;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(VerifyLoginCodeController::class)]
class VerifyLoginCodeControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.local.enabled' => true]);
        config(['auth.local.code.max_attempts' => 5]);
        config(['auth.local.code.digits' => 6]);

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
        $this->assertEquals(UserSegmentEnum::EXTERNAL_USER, $loginRecord->segment);
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
        config(['auth.local.enabled' => false]);

        $response = $this->post(route('login-code.verify'), ['code' => '123456']);

        $response->assertNotFound();
    }
}
