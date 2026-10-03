<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Resources\Users\Schemas;

use App\Domains\Auth\Enums\RoleTypeEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\User;
use App\Filament\Resources\Users\Schemas\NorthwesternUserInfolist;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(NorthwesternUserInfolist::class)]
final class NorthwesternUserInfolistTest extends TestCase
{
    // Directory Search is optional locally, so Force Sync only shows when it can work.
    public function test_force_sync_shows_only_when_directory_search_is_configured(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->whereHas('role_type', fn ($query) => $query->where('slug', RoleTypeEnum::SystemManaged))->where('name', 'Super Administrator')->firstOrFail());
        $person = User::factory()->create();

        config(['nusoa.directorySearch.apiKey' => 'test-api-key']);
        $this->actingAs($admin)->get("/administration/users/{$person->getKey()}")->assertOk()->assertSee('Force Sync');

        config(['nusoa.directorySearch.apiKey' => null]);
        $this->actingAs($admin)->get("/administration/users/{$person->getKey()}")->assertOk()->assertDontSee('Force Sync');
    }
}
