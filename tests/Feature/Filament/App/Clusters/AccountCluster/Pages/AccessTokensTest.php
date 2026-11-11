<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\App\Clusters\AccountCluster\Pages;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Api\Enums\CredentialStatus;
use App\Domains\Api\Enums\TokenExpiration;
use App\Domains\Api\Models\OAuthToken;
use App\Domains\User\Models\User;
use App\Filament\App\Clusters\AccountCluster\Pages\AccessTokens;
use App\Filament\Support\RevealOnceSecret;
use App\Providers\Filament\AppPanelProvider;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Concerns\IssuesPersonalAccessTokens;
use Tests\TestCase;

#[CoversNothing]
final class AccessTokensTest extends TestCase
{
    use IssuesPersonalAccessTokens;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(AppPanelProvider::ID);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(SystemPermission::CreatePersonalAccessTokens, SystemPermission::ViewUsers);
        $this->actingAs($this->user);
    }

    public function test_only_holders_of_the_permission_see_the_page(): void
    {
        $this->get('/app/account/access-tokens')->assertOk()->assertSee('Personal Access Tokens');
        $this->get('/app/account/profile')->assertSee('Access Tokens');

        $this->actingAs(User::factory()->create());
        $this->get('/app/account/access-tokens')->assertForbidden();
        $this->get('/app/account/profile')->assertDontSee('Access Tokens');
    }

    public function test_it_lists_the_persons_tokens_only(): void
    {
        [, $mine] = $this->personalAccessToken($this->user);
        [, $theirs] = $this->personalAccessToken(User::factory()->create());

        Livewire::test(AccessTokens::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    // The token is shown once: kept encrypted in the session for the copy step, then forgotten.
    // Created and Expires show dates only, on the person's calendar rather than UTC's.
    public function test_dates_are_in_the_persons_timezone(): void
    {
        $this->user->forceFill(['timezone' => 'America/Chicago'])->save();
        // 9 p.m. on November 9 in Chicago.
        $this->travelTo(CarbonImmutable::parse('2026-11-10 03:00:00', 'UTC'));
        $this->personalAccessToken($this->user);

        Livewire::test(AccessTokens::class)
            ->assertSee('Nov 9, 2026')
            ->assertDontSee('Nov 10, 2026');
    }

    public function test_creating_a_token_shows_it_once(): void
    {
        $component = Livewire::test(AccessTokens::class)
            ->mountAction(TestAction::make('createToken')->table())
            ->fillForm(['name' => 'Export script', 'scopes' => ['view-users'], 'lifetime' => TokenExpiration::SixMonths->value])
            ->goToNextWizardStep();

        $token = OAuthToken::query()->where('user_id', $this->user->getKey())->sole();
        $this->assertSame('Export script', $token->name);
        $this->assertSame(['view-users'], $token->scopes);
        $this->assertTrue($token->expires_at->isSameDay(now()->addDays(180)));

        $shown = (string) RevealOnceSecret::for('personal_access_token:create')->secret($component->instance());
        $this->withToken($shown)->getJson('/api/v1/me')->assertOk();

        $component->callMountedAction();
        $this->assertSame([], preg_grep('/^reveal_once_secret:/', array_keys(Session::all())));
        $this->assertStringNotContainsString($shown, json_encode($component->instance()->all(), JSON_THROW_ON_ERROR));
    }

    public function test_creating_again_after_cancelling_creates_a_new_token(): void
    {
        $component = Livewire::test(AccessTokens::class)
            ->mountAction(TestAction::make('createToken')->table())
            ->fillForm(['name' => 'Abandoned', 'lifetime' => TokenExpiration::SixMonths->value])
            ->goToNextWizardStep()
            ->unmountAction()
            ->mountAction(TestAction::make('createToken')->table())
            ->fillForm(['name' => 'Second', 'lifetime' => TokenExpiration::SixMonths->value])
            ->goToNextWizardStep();

        $second = OAuthToken::query()->where('user_id', $this->user->getKey())->where('name', 'Second')->sole();
        $this->assertSame($second->getKey(), RevealOnceSecret::for('personal_access_token:create')->identifier($component->instance()));
    }

    public function test_a_person_can_only_choose_scopes_their_permissions_cover(): void
    {
        $this->user->revokePermissionTo(SystemPermission::ViewUsers);

        Livewire::test(AccessTokens::class)
            ->mountAction(TestAction::make('createToken')->table())
            ->fillForm(['name' => 'Export script', 'scopes' => ['view-users'], 'lifetime' => TokenExpiration::OneMonth->value])
            ->goToNextWizardStep()
            ->assertHasFormErrors(['scopes.0']);

        $this->assertSame(0, OAuthToken::query()->count());
    }

    public function test_reaching_the_limit_says_so(): void
    {
        config(['api.personal_access_tokens.max_active' => 1]);
        $this->personalAccessToken($this->user);

        Livewire::test(AccessTokens::class)
            ->mountAction(TestAction::make('createToken')->table())
            ->fillForm(['name' => 'Another', 'lifetime' => TokenExpiration::OneMonth->value])
            ->goToNextWizardStep()
            ->assertNotified('You have reached the maximum number of active personal access tokens. Revoke one to create another.');

        $this->assertSame(1, OAuthToken::query()->count());
    }

    public function test_a_person_revokes_a_token(): void
    {
        [, $token] = $this->personalAccessToken($this->user);

        Livewire::test(AccessTokens::class)->callAction(TestAction::make('revoke')->table($token));

        $this->assertSame(CredentialStatus::Revoked, $token->fresh()?->status);
    }

    public function test_an_impersonator_sees_the_tokens_but_cannot_create_or_revoke_them(): void
    {
        [, $token] = $this->personalAccessToken($this->user);

        $impersonate = Mockery::mock();
        $impersonate->shouldReceive('isImpersonating')->andReturn(true);
        $impersonate->shouldReceive('getImpersonatorId')->andReturn(null);
        $this->app->instance('impersonate', $impersonate);

        Livewire::test(AccessTokens::class)
            ->assertSee('You\'re impersonating this person.', escape: false)
            ->assertCanSeeTableRecords([$token])
            ->assertActionHidden(TestAction::make('createToken')->table())
            ->assertActionHidden(TestAction::make('revoke')->table($token));
    }
}
