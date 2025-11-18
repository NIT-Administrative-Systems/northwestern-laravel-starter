<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Domains\User\Models\User;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(UserController::class)]
class UserControllerTest extends TestCase
{
    private User $user;

    private const string TEMPORARY_URL = 'https://example.com/temporary-url';

    private const string WILDCARD_PHOTO_KEY = 'wildcard-photos/example.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        if (! config('platform.wildcard_photo_sync.enabled')) {
            $this->markTestSkipped('Wildcard photo sync is not enabled');
        }

        $this->user = User::factory()->create();

        Storage::fake('s3');
    }

    public function test_returns_temporary_url_for_user_with_photo_when_authorized(): void
    {
        $this->user->update(['wildcard_photo_s3_key' => self::WILDCARD_PHOTO_KEY]);

        Gate::shouldReceive('allows')
            ->once()
            ->andReturn(true);

        Storage::shouldReceive('temporaryUrl')
            ->withArgs(function ($path, $expiration): bool {
                return is_string($path) && $expiration instanceof \DateTimeInterface;
            })
            ->once()
            ->andReturn(self::TEMPORARY_URL);

        $response = $this->actingAs($this->user)
            ->get(route('users.wildcard-photo', $this->user));

        $response->assertRedirect(self::TEMPORARY_URL);
        $response->assertHeader('Cache-Control', 'max-age=1800, private');
    }

    public function test_returns_default_photo_when_user_has_no_photo(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('users.wildcard-photo', $this->user));

        $response->assertRedirect(asset('images/default-profile-photo.svg'));
        $response->assertHeader('Cache-Control', 'max-age=1800, private');
    }

    public function test_returns_default_photo_when_user_is_not_authorized(): void
    {
        $this->user->update(['wildcard_photo_s3_key' => self::WILDCARD_PHOTO_KEY]);

        Gate::shouldReceive('allows')
            ->once()
            ->andReturn(false);

        $response = $this->actingAs($this->user)
            ->get(route('users.wildcard-photo', $this->user));

        $response->assertRedirect(asset('images/default-profile-photo.svg'));
        $response->assertHeader('Cache-Control', 'max-age=1800, private');
    }
}
