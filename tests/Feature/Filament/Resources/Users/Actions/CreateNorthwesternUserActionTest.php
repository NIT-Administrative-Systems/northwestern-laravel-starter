<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Resources\Users\Actions;

use App\Domains\Auth\Enums\RoleTypeEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\User;
use App\Filament\Resources\Users\Actions\CreateNorthwesternUserAction;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Providers\Filament\AdministrationPanelProvider;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Northwestern\SysDev\SOA\DirectorySearch;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CreateNorthwesternUserAction::class)]
final class CreateNorthwesternUserActionTest extends TestCase
{
    // Directory Search is optional locally; without a key the lookup explains that instead of reporting an API failure.
    public function test_without_a_directory_key_the_lookup_says_it_is_not_configured(): void
    {
        Filament::setCurrentPanel(AdministrationPanelProvider::ID);
        config(['nusoa.directorySearch.apiKey' => null]);
        $this->mock(DirectorySearch::class)->shouldNotReceive('lookup');

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->whereHas('role_type', fn ($query) => $query->where('slug', RoleTypeEnum::SystemManaged))->where('name', 'Super Administrator')->firstOrFail());
        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->callAction('create-nu-user', data: ['netid' => 'abc123'])
            ->assertHasFormErrors(['netid' => "Directory Search isn't configured. Set DIRECTORY_SEARCH_API_KEY to look people up."]);
    }
}
