<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Actions;

use App\Domains\User\Actions\ValidateLoginLink;
use App\Domains\User\Models\LoginLink;
use App\Domains\User\Models\User;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ValidateLoginLink::class)]
class ValidateLoginLinkTest extends TestCase
{
    public function test_validates_correct_token(): void
    {
        $user = User::factory()->affiliate()->create();
        $rawToken = Str::random(64);
        $hashedToken = LoginLink::hashFromPlain($rawToken);

        LoginLink::create([
            'user_id' => $user->id,
            'token' => $hashedToken,
            'email' => $user->email,
            'expires_at' => now()->addMinutes(15),
        ]);

        $validatedUser = $this->action()($rawToken);

        $this->assertNotNull($validatedUser);
        $this->assertEquals($user->id, $validatedUser->id);
    }

    public function test_returns_null_for_invalid_token(): void
    {
        $user = User::factory()->affiliate()->create();
        $correctToken = Str::random(64);
        $wrongToken = Str::random(64);

        LoginLink::create([
            'user_id' => $user->id,
            'token' => LoginLink::hashFromPlain($correctToken),
            'email' => $user->email,
            'expires_at' => now()->addMinutes(15),
        ]);

        $validatedUser = $this->action()($wrongToken);

        $this->assertNull($validatedUser);
    }

    public function test_returns_null_for_nonexistent_token(): void
    {
        $validatedUser = $this->action()(Str::random(64));

        $this->assertNull($validatedUser);
    }

    public function test_returns_null_for_expired_token(): void
    {
        $user = User::factory()->affiliate()->create();
        $rawToken = Str::random(64);

        LoginLink::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $rawToken),
            'email' => $user->email,
            'expires_at' => now()->subMinute(),
        ]);

        $validatedUser = $this->action()($rawToken);

        $this->assertNull($validatedUser);
    }

    public function test_returns_null_for_used_token(): void
    {
        $user = User::factory()->affiliate()->create();
        $rawToken = Str::random(64);

        LoginLink::create([
            'user_id' => $user->id,
            'token' => LoginLink::hashFromPlain($rawToken),
            'email' => $user->email,
            'expires_at' => now()->addMinutes(15),
            'used_at' => now()->subMinutes(5),
        ]);

        $validatedUser = $this->action()($rawToken);

        $this->assertNull($validatedUser);
    }

    public function test_handles_multiple_unused_links_for_same_user(): void
    {
        $user = User::factory()->affiliate()->create();
        $token1 = Str::random(64);
        $token2 = Str::random(64);

        LoginLink::create([
            'user_id' => $user->id,
            'token' => LoginLink::hashFromPlain($token1),
            'email' => $user->email,
            'expires_at' => now()->addMinutes(15),
        ]);

        LoginLink::create([
            'user_id' => $user->id,
            'token' => LoginLink::hashFromPlain($token2),
            'email' => $user->email,
            'expires_at' => now()->addMinutes(15),
        ]);

        $user1 = $this->action()($token1);
        $user2 = $this->action()($token2);

        $this->assertNotNull($user1);
        $this->assertNotNull($user2);
        $this->assertEquals($user->id, $user1->id);
        $this->assertEquals($user->id, $user2->id);
    }

    public function test_does_not_validate_token_for_different_user(): void
    {
        $user1 = User::factory()->affiliate()->create(['email' => 'user1@example.com']);
        $user2 = User::factory()->affiliate()->create(['email' => 'user2@example.com']);
        $rawToken = Str::random(64);

        LoginLink::create([
            'user_id' => $user1->id,
            'token' => LoginLink::hashFromPlain($rawToken),
            'email' => $user1->email,
            'expires_at' => now()->addMinutes(15),
        ]);

        $validatedUser = $this->action()($rawToken);

        $this->assertNotNull($validatedUser);
        $this->assertEquals($user1->id, $validatedUser->id);
        $this->assertNotEquals($user2->id, $validatedUser->id);
    }

    public function test_returns_null_when_both_expired_and_used(): void
    {
        $user = User::factory()->affiliate()->create();
        $rawToken = Str::random(64);

        LoginLink::create([
            'user_id' => $user->id,
            'token' => LoginLink::hashFromPlain($rawToken),
            'email' => $user->email,
            'expires_at' => now()->subMinute(),
            'used_at' => now()->subMinutes(5),
        ]);

        $validatedUser = $this->action()($rawToken);

        $this->assertNull($validatedUser);
    }

    public function test_empty_string_token_returns_null(): void
    {
        $validatedUser = $this->action()('');

        $this->assertNull($validatedUser);
    }

    protected function action(): ValidateLoginLink
    {
        return resolve(ValidateLoginLink::class);
    }
}
