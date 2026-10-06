<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\App\Widgets;

use App\Domains\Auth\Enums\RoleTypeEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\User;
use App\Domains\User\Models\UserLoginRecord;
use App\Filament\App\Widgets\YourAccountWidget;
use App\Providers\Filament\AppPanelProvider;
use Filament\Facades\Filament;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(YourAccountWidget::class)]
final class YourAccountWidgetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(AppPanelProvider::ID);
    }

    public function test_the_dashboard_shows_it(): void
    {
        $this->actingAs(User::factory()->create(['first_name' => 'Willie']))
            ->get('/app')
            ->assertOk()
            ->assertSee('Welcome, Willie');
    }

    // The newest login record is the current sign-in, so the greeting reports the one before it.
    public function test_a_returning_user_is_welcomed_back_without_sign_in_details(): void
    {
        $user = User::factory()->create(['first_name' => 'Willie']);
        UserLoginRecord::factory()->for($user)->create(['logged_in_at' => now()->subDays(2)]);
        UserLoginRecord::factory()->for($user)->create(['logged_in_at' => now()]);

        $this->actingAs($user);

        Livewire::test(YourAccountWidget::class)
            ->assertSee('Welcome back, Willie')
            ->assertDontSee('2 days ago');
    }

    public function test_a_first_sign_in_is_welcomed_rather_than_welcomed_back(): void
    {
        $user = User::factory()->create(['first_name' => 'Willie']);
        UserLoginRecord::factory()->for($user)->create();

        $this->actingAs($user);

        Livewire::test(YourAccountWidget::class)
            ->assertSee('Welcome, Willie')
            ->assertDontSee('Welcome back');
    }

    public function test_it_lists_roles_beyond_the_default_northwestern_user_role(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create(['name' => 'Program Coordinator']);
        $user->roles()->attach($role);

        $this->actingAs($user);

        Livewire::test(YourAccountWidget::class)
            ->assertSee('Program Coordinator')
            ->assertDontSee('Northwestern User');
    }

    public function test_it_links_to_the_account_area(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(YourAccountWidget::class)
            ->assertSee('Account')
            ->assertSee('/app/account/profile');
    }

    public function test_a_user_with_only_standard_access_is_told_so(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(YourAccountWidget::class)->assertSee('None beyond standard access.');
    }

    // Like the Help menu, each link shows only when it is configured.
    public function test_help_links_follow_the_support_settings(): void
    {
        $this->actingAs(User::factory()->create());

        config(['support.enabled' => true, 'support.documentation_url' => 'https://docs.example.test/']);

        Livewire::test(YourAccountWidget::class)
            ->assertSee('Need help?')
            ->assertSee('Contact Support')
            ->assertSee('https://docs.example.test/');

        config(['support.enabled' => false, 'support.documentation_url' => null]);

        Livewire::test(YourAccountWidget::class)
            ->assertDontSee('Need help?')
            ->assertDontSee('Contact Support');
    }
}
