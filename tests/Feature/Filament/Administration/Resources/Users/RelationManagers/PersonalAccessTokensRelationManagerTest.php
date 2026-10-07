<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Administration\Resources\Users\RelationManagers;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Auth\Enums\CredentialStatus;
use App\Domains\Core\Models\Audit;
use App\Domains\User\Models\User;
use App\Filament\Administration\Resources\Users\Pages\ViewUser;
use App\Filament\Administration\Resources\Users\RelationManagers\PersonalAccessTokensRelationManager;
use App\Providers\Filament\AdministrationPanelProvider;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Concerns\IssuesPersonalAccessTokens;
use Tests\TestCase;

#[CoversNothing]
final class PersonalAccessTokensRelationManagerTest extends TestCase
{
    use IssuesPersonalAccessTokens;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(AdministrationPanelProvider::ID);

        $admin = User::factory()->create();
        $admin->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ManageApiAccess);
        $this->actingAs($admin);
    }

    public function test_it_shows_for_people_to_holders_of_manage_api_access(): void
    {
        $person = User::factory()->create();

        $this->assertTrue(PersonalAccessTokensRelationManager::canViewForRecord($person, ViewUser::class));
        $this->assertFalse(PersonalAccessTokensRelationManager::canViewForRecord(User::factory()->api()->create(), ViewUser::class));

        $this->actingAs(User::factory()->create());
        $this->assertFalse(PersonalAccessTokensRelationManager::canViewForRecord($person, ViewUser::class));
    }

    // For a leaked token: the administrator revokes it, and the person's audit history says so.
    public function test_an_administrator_revokes_a_persons_token(): void
    {
        $person = User::factory()->create();
        [, $token] = $this->personalAccessToken($person);

        Livewire::test(PersonalAccessTokensRelationManager::class, ['ownerRecord' => $person, 'pageClass' => ViewUser::class])
            ->assertCanSeeTableRecords([$token])
            ->callAction(TestAction::make('revoke')->table($token));

        $this->assertSame(CredentialStatus::Revoked, $token->fresh()?->status);
        $this->assertTrue(Audit::query()->where('event', 'personal_access_token_revoked')->where('auditable_id', $person->getKey())->exists());
    }

    // Turning the API off doesn't take away the means to clean up.
    public function test_an_administrator_still_revokes_while_the_api_is_off(): void
    {
        $person = User::factory()->create();
        [, $token] = $this->personalAccessToken($person);
        config(['api.enabled' => false]);

        $this->assertTrue(PersonalAccessTokensRelationManager::canViewForRecord($person, ViewUser::class));

        Livewire::test(PersonalAccessTokensRelationManager::class, ['ownerRecord' => $person, 'pageClass' => ViewUser::class])
            ->callAction(TestAction::make('revoke')->table($token));

        $this->assertSame(CredentialStatus::Revoked, $token->fresh()?->status);
    }

    public function test_revoke_is_hidden_while_impersonating(): void
    {
        $person = User::factory()->create();
        [, $token] = $this->personalAccessToken($person);
        $this->impersonating();

        Livewire::test(PersonalAccessTokensRelationManager::class, ['ownerRecord' => $person, 'pageClass' => ViewUser::class])
            ->assertActionHidden(TestAction::make('revoke')->table($token));
    }

    private function impersonating(): void
    {
        $impersonate = Mockery::mock();
        $impersonate->shouldReceive('isImpersonating')->andReturn(true);
        $impersonate->shouldReceive('getImpersonatorId')->andReturn(null);
        $this->app->instance('impersonate', $impersonate);
    }
}
