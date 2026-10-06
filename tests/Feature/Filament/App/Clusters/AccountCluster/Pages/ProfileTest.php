<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\App\Clusters\AccountCluster\Pages;

use App\Domains\Auth\Enums\RoleTypeEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Enums\Affiliation;
use App\Domains\User\Models\User;
use App\Domains\User\Models\UserLoginRecord;
use App\Filament\App\Clusters\AccountCluster;
use App\Filament\App\Clusters\AccountCluster\Pages\Profile;
use App\Providers\Filament\AppPanelProvider;
use Filament\Facades\Filament;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Profile::class)]
#[CoversClass(AccountCluster::class)]
final class ProfileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(AppPanelProvider::ID);
    }

    public function test_the_account_area_opens_on_the_profile(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/app/account')
            ->assertRedirect(Profile::getUrl(panel: AppPanelProvider::ID));

        $this->get('/app/account/profile')
            ->assertOk()
            ->assertSee('Profile')
            ->assertSee('Preferences');
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/app/account/profile')->assertRedirect(route('filament.app.auth.login'));
    }

    public function test_netid_users_see_their_directory_details(): void
    {
        $this->actingAs(User::factory()->create([
            'first_name' => 'Willie',
            'last_name' => 'Wildcat',
            'username' => 'wwc123',
            'email' => 'willie@northwestern.edu',
            'primary_affiliation' => Affiliation::Staff,
            'job_titles' => ['Mascot'],
            'departments' => ['Athletics'],
        ]));

        Livewire::test(Profile::class)
            ->assertSee('From the Northwestern Directory')
            ->assertSee('Willie Wildcat')
            ->assertSee('wwc123')
            ->assertSee('willie@northwestern.edu')
            ->assertSee(Affiliation::Staff->getLabel())
            ->assertSee('Mascot')
            ->assertSee('Athletics')
            ->assertSee('NetID')
            ->assertDontSee('Request a change');
    }

    public function test_email_users_see_the_details_an_administrator_entered(): void
    {
        config(['support.enabled' => true]);

        $this->actingAs(User::factory()->affiliate()->create([
            'username' => 'willie-x1y2',
            'job_titles' => ['Visiting Scholar'],
        ]));

        Livewire::test(Profile::class)
            ->assertSee('Entered by an administrator')
            ->assertSee('Visiting Scholar')
            ->assertSee('Request a change')
            ->assertSee('Email verification code')
            ->assertDontSee('willie-x1y2');

        config(['support.enabled' => false]);

        Livewire::test(Profile::class)->assertDontSee('Request a change');
    }

    public function test_it_lists_the_most_recent_sign_ins(): void
    {
        $user = User::factory()->create();
        UserLoginRecord::factory()->for($user)->create(['logged_in_at' => now()->subYear(), 'ip_address' => '192.0.2.99']);
        UserLoginRecord::factory()->for($user)->count(Profile::RECENT_SIGN_INS)->create(['logged_in_at' => now()->subDay(), 'ip_address' => '192.0.2.10']);

        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->assertSee('192.0.2.10')
            ->assertDontSee('192.0.2.99');
    }

    public function test_it_says_how_long_sign_ins_are_kept(): void
    {
        $this->actingAs(User::factory()->create());

        config(['platform.retention.login_records' => 90]);
        Livewire::test(Profile::class)->assertSee('Records are kept for 90 days.');

        config(['platform.retention.login_records' => null]);
        Livewire::test(Profile::class)->assertDontSee('Records are kept for');
    }

    public function test_it_lists_roles_beyond_standard_access(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Profile::class)->assertSee('None beyond standard access.');

        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create(['name' => 'Program Coordinator']));
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->assertSee('Program Coordinator')
            ->assertDontSee('None beyond standard access.');
    }
}
