<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\App\Clusters\AccountCluster\Pages;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\User\Models\User;
use App\Filament\App\Clusters\AccountCluster\Pages\Preferences;
use App\Providers\Filament\AppPanelProvider;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
final class PreferencesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(AppPanelProvider::ID);
    }

    public function test_it_renders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/app/account/preferences')
            ->assertOk()
            ->assertSee('Timezone');
    }

    public function test_it_shows_and_saves_the_timezone(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Chicago']);
        $this->actingAs($user);

        Livewire::test(Preferences::class)
            ->assertSchemaStateSet(['timezone' => 'America/Chicago'], 'form')
            ->assertSeeHtml('type="submit"')
            ->fillForm(['timezone' => 'Europe/London'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Preferences Saved');

        $this->assertSame('Europe/London', $user->refresh()->timezone);
    }

    public function test_token_holders_choose_whether_to_be_emailed_before_tokens_expire(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Preferences::class)->assertDontSee('Expiring personal access tokens');

        $user->givePermissionTo(SystemPermission::CreatePersonalAccessTokens);

        Livewire::test(Preferences::class)
            ->assertSchemaStateSet(['emailBeforeAccessTokensExpire' => true], 'form')
            ->fillForm(['emailBeforeAccessTokensExpire' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($user->refresh()->preferences->emailBeforeAccessTokensExpire);
    }

    public function test_people_choose_whether_to_be_emailed_when_an_application_connects(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Preferences::class)
            ->assertSchemaStateSet(['emailWhenApplicationConnects' => true], 'form')
            ->fillForm(['emailWhenApplicationConnects' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($user->refresh()->preferences->emailWhenApplicationConnects);
    }

    public function test_people_choose_whether_to_be_emailed_announcements(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Preferences::class)
            ->assertSchemaStateSet(['emailAnnouncements' => true], 'form')
            ->fillForm(['emailAnnouncements' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($user->refresh()->preferences->emailAnnouncements);
    }

    public function test_it_refuses_a_value_that_is_not_a_timezone(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Chicago']);
        $this->actingAs($user);

        Livewire::test(Preferences::class)
            ->fillForm(['timezone' => 'Mars/Olympus_Mons'])
            ->call('save')
            ->assertHasFormErrors(['timezone']);

        $this->assertSame('America/Chicago', $user->refresh()->timezone);
    }

    public function test_an_impersonator_sees_the_preferences_but_cannot_save_them(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Chicago']);
        $this->actingAs($user);
        $this->impersonating();

        Livewire::test(Preferences::class)
            ->assertSee('You\'re impersonating this person.', escape: false)
            ->assertSchemaStateSet(['timezone' => 'America/Chicago'], 'form')
            ->assertDontSeeHtml('type="submit"')
            ->call('save')
            ->assertForbidden();

        $this->assertSame('America/Chicago', $user->refresh()->timezone);
    }

    private function impersonating(): void
    {
        $impersonate = Mockery::mock();
        $impersonate->shouldReceive('isImpersonating')->andReturn(true);
        $impersonate->shouldReceive('getImpersonatorId')->andReturn(null);

        $this->app->instance('impersonate', $impersonate);
    }
}
