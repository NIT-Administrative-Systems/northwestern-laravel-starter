<?php

declare(strict_types=1);

namespace Tests\Feature\View\Components;

use App\Domains\User\Models\User;
use App\View\Components\WildcardPhoto;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(WildcardPhoto::class)]
class WildcardPhotoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Route::has('users.wildcard-photo')) {
            Route::get('/users/{user}/wildcard-photo', fn (User $user) => (string) $user->id)->name('users.wildcard-photo');
        }
    }

    public function test_render_includes_cache_busting_hash_when_photo_key_exists(): void
    {
        $user = User::factory()->create([
            'wildcard_photo_s3_key' => 'wildcard-photos/example.jpg',
            'wildcard_photo_last_synced_at' => now(),
        ]);

        $component = new WildcardPhoto($user);
        $view = $component->render();
        $data = $view->getData();

        $expectedUrl = route('users.wildcard-photo', [
            'user' => $user,
            'c' => md5($user->wildcard_photo_last_synced_at->toString()),
        ]);

        $this->assertSame($expectedUrl, $data['imageUrl']);
        $this->assertTrue($data['user']->is($user));
    }

    public function test_render_uses_plain_route_when_no_photo_key_exists(): void
    {
        $user = User::factory()->create([
            'wildcard_photo_s3_key' => null,
            'wildcard_photo_last_synced_at' => null,
        ]);

        $component = new WildcardPhoto($user);
        $view = $component->render();
        $data = $view->getData();

        $this->assertSame(route('users.wildcard-photo', $user), $data['imageUrl']);
    }
}
