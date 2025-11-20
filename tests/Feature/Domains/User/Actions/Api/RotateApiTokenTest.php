<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Actions\Api;

use App\Domains\User\Actions\Api\IssueApiToken;
use App\Domains\User\Actions\Api\RotateApiToken;
use App\Domains\User\Models\ApiToken;
use App\Domains\User\Models\User;
use Auth;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RotateApiToken::class)]
class RotateApiTokenTest extends TestCase
{
    public function test_it_rotates_a_token(): void
    {
        Carbon::setTestNow(now());

        $user = User::factory()->api()->create();

        [$oldTokenString, $oldToken] = new IssueApiToken()($user);

        $rotator = new RotateApiToken(new IssueApiToken());

        Auth::login($user);

        $newTokenString = $rotator($oldToken);

        $this->assertNotEquals($oldTokenString, $newTokenString);

        $oldToken->refresh();
        $newToken = ApiToken::where('token_hash', ApiToken::hashFromPlain($newTokenString))->first();

        $this->assertNotNull($newToken);
        $this->assertEquals($oldToken->id, $newToken->rotated_from_token_id);
        $this->assertEquals($user->id, $newToken->rotated_by_user_id);
        $this->assertNotNull($oldToken->revoked_at);
        $this->assertNull($newToken->revoked_at);
    }
}
