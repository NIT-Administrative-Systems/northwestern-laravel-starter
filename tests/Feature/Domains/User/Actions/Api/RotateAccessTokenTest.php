<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Actions\Api;

use App\Domains\User\Actions\Api\IssueAccessToken;
use App\Domains\User\Actions\Api\RotateAccessToken;
use App\Domains\User\Models\AccessToken;
use App\Domains\User\Models\User;
use Auth;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RotateAccessToken::class)]
class RotateAccessTokenTest extends TestCase
{
    public function test_it_rotates_a_token(): void
    {
        Carbon::setTestNow(now());

        $user = User::factory()->api()->create();

        [$oldTokenString, $oldToken] = new IssueAccessToken()($user, 'Test');

        $rotator = new RotateAccessToken(new IssueAccessToken());

        Auth::login($user);

        $newTokenString = $rotator($oldToken, $oldToken->name);

        $this->assertNotEquals($oldTokenString, $newTokenString);

        $oldToken->refresh();
        $newToken = AccessToken::where('token_hash', AccessToken::hashFromPlain($newTokenString))->first();

        $this->assertNotNull($newToken);
        $this->assertEquals($oldToken->id, $newToken->rotated_from_token_id);
        $this->assertEquals($user->id, $newToken->rotated_by_user_id);
        $this->assertNotNull($oldToken->revoked_at);
        $this->assertNull($newToken->revoked_at);
    }
}
