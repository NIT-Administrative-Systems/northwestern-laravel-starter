<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Actions\Api;

use App\Domains\Auth\Actions\Api\IssueAccessToken;
use App\Domains\Auth\Actions\Api\RotateAccessToken;
use App\Domains\Auth\Models\AccessToken;
use App\Domains\User\Models\User;
use Auth;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RotateAccessToken::class)]
final class RotateAccessTokenTest extends TestCase
{
    public function test_it_rotates_a_token(): void
    {
        $this->travelTo(now());

        $user = User::factory()->api()->create();

        [$oldTokenString, $oldToken] = new IssueAccessToken()($user, 'Test');

        $rotator = new RotateAccessToken(new IssueAccessToken());

        Auth::login($user);

        $newTokenString = $rotator($oldToken, $oldToken->name);

        $this->assertNotSame($oldTokenString, $newTokenString);

        $oldToken->refresh();
        $newToken = AccessToken::where('token_hash', AccessToken::hashFromPlain($newTokenString))->first();

        $this->assertNotNull($newToken);
        $this->assertEquals($oldToken->id, $newToken->rotated_from_token_id);
        $this->assertEquals($user->id, $newToken->rotated_by_user_id);
        $this->assertNotNull($oldToken->revoked_at);
        $this->assertNull($newToken->revoked_at);
    }

    public function test_it_throws_when_no_rotating_user_is_provided_and_no_auth_session_exists(): void
    {
        $user = User::factory()->api()->create();
        [, $oldToken] = new IssueAccessToken()($user, 'Test');

        Auth::logout();

        $rotator = new RotateAccessToken(new IssueAccessToken());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A user performing the rotation must be provided or an authenticated session must be active.');

        $rotator($oldToken, 'Rotated Token');
    }

    public function test_it_propagates_expires_at_and_allowed_ips_to_the_new_token(): void
    {
        $user = User::factory()->api()->create();
        [$oldTokenString, $oldToken] = new IssueAccessToken()($user, 'Original');

        $rotatedBy = User::factory()->api()->create();
        $expiresAt = CarbonImmutable::parse('2030-01-01 00:00:00');
        $allowedIps = ['10.0.0.1', '192.168.1.0/24'];

        $rotator = new RotateAccessToken(new IssueAccessToken());
        $newTokenString = $rotator(
            previousAccessToken: $oldToken,
            name: 'Rotated',
            rotatedBy: $rotatedBy,
            expiresAt: $expiresAt,
            allowedIps: $allowedIps,
        );

        $this->assertNotSame($oldTokenString, $newTokenString);

        $newToken = AccessToken::where('token_hash', AccessToken::hashFromPlain($newTokenString))->first();

        $this->assertNotNull($newToken);
        $this->assertTrue($newToken->expires_at?->equalTo($expiresAt));
        $this->assertSame($allowedIps, $newToken->allowed_ips);
        $this->assertSame($rotatedBy->id, $newToken->rotated_by_user_id);
    }
}
