<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\User;
use App\Providers\EagerLoadEloquentUserProvider;
use Illuminate\Contracts\Hashing\Hasher;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(EagerLoadEloquentUserProvider::class)]
class EagerLoadEloquentUserProviderTest extends TestCase
{
    public function test_eager_loads_user_relationships(): void
    {
        $hasher = app(Hasher::class);
        $mockUser = User::factory()->create();
        $mockUser->roles()->attach(Role::factory()->create());

        $provider = new EagerLoadEloquentUserProvider($hasher, User::class);
        $resolvedUser = $provider->retrieveById($mockUser->id);

        $this->assertInstanceOf(User::class, $resolvedUser);
        $this->assertTrue($resolvedUser->relationLoaded('roles'));
        $this->assertTrue($resolvedUser->roles->first()->relationLoaded('permissions'));
        $this->assertTrue($resolvedUser->roles->first()->relationLoaded('role_type'));
    }
}
