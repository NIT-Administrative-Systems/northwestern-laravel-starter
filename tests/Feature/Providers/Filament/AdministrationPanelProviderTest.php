<?php

declare(strict_types=1);

namespace Tests\Feature\Providers\Filament;

use App\Domains\Access\Enums\RoleTypeEnum;
use App\Domains\Access\Enums\SystemRole;
use App\Domains\Access\Models\Role;
use App\Domains\Api\Models\ApiRequestLog;
use App\Domains\Core\Models\Audit;
use App\Domains\Support\Models\Announcement;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\User\Models\User;
use App\Domains\User\Models\UserLoginRecord;
use App\Filament\Administration\Resources\Roles\Pages\ViewRole;
use App\Filament\Administration\Resources\Roles\RoleResource;
use App\Filament\Administration\Resources\Users\Pages\ViewUser;
use App\Filament\Administration\Resources\Users\UserResource;
use App\Providers\Filament\AdministrationPanelProvider;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Northwestern\SysDev\Chassis\Testing\Browser\FilamentPages;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Concerns\IssuesPersonalAccessTokens;
use Tests\Concerns\IssuesServiceClientTokens;
use Tests\Concerns\RunsAuthorizationCodeFlow;
use Tests\TestCase;

#[CoversNothing]
final class AdministrationPanelProviderTest extends TestCase
{
    use IssuesPersonalAccessTokens, IssuesServiceClientTokens, RunsAuthorizationCodeFlow;

    /**
     * Both panels use Filament's strict authorization, which throws when a page, table or action
     * asks about a model with no policy, or a policy without that ability, instead of allowing it.
     * Tables only ask about their rows, so every table here has one.
     */
    public function test_every_page_table_and_tab_has_the_authorization_it_asks_for(): void
    {
        config(['mcp.enabled' => true, 'api.enabled' => true, 'api.request_logging.enabled' => true, 'support.enabled' => true]);

        $superAdministrator = User::factory()->create();
        $superAdministrator->roles()->attach(Role::query()->where('name', SystemRole::SuperAdministrator)->sole());
        $this->actingAs($superAdministrator);

        $this->registerMcpClient();
        $this->registerApplication();
        $this->serviceClientToken($apiUser = User::factory()->api()->create());
        $this->personalAccessToken($superAdministrator);
        ApiRequestLog::factory()->create(['user_id' => $superAdministrator->id]);
        UserLoginRecord::factory()->create(['user_id' => $superAdministrator->id]);
        $ticket = SupportTicket::factory()->create(['user_id' => $superAdministrator->id]);
        $announcement = Announcement::factory()->create();
        $person = User::factory()->affiliate()->create();
        // A System Managed role has no edit page, so the role is a type that does.
        $role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create();
        $person->roles()->attach($role);
        $audit = Audit::query()->latest('id')->firstOrFail();

        $paths = [
            ...FilamentPages::in(AdministrationPanelProvider::ID),
            ...FilamentPages::in('app'),
            UserResource::getUrl('view', ['record' => $superAdministrator], panel: AdministrationPanelProvider::ID),
            UserResource::getUrl('view', ['record' => $apiUser], panel: AdministrationPanelProvider::ID),
            UserResource::getUrl('view', ['record' => $person], panel: AdministrationPanelProvider::ID),
            RoleResource::getUrl('view', ['record' => $role], panel: AdministrationPanelProvider::ID),
            RoleResource::getUrl('edit', ['record' => $role], panel: AdministrationPanelProvider::ID),
            RoleResource::getUrl('history', ['record' => $role], panel: AdministrationPanelProvider::ID),
            "/administration/announcements/{$announcement->id}/edit",
            "/administration/support-tickets/{$ticket->id}",
            "/administration/audits/{$audit->id}",
        ];

        foreach ($paths as $path) {
            $response = $this->get($path);

            // A cluster's page sends you on to its first page.
            $this->assertTrue($response->isOk() || $response->isRedirect(), "{$path} answered {$response->status()}.");
        }

        // Relation managers load after the page, so each is rendered on its own.
        Filament::setCurrentPanel(AdministrationPanelProvider::ID);

        foreach ([[$superAdministrator, UserResource::class, ViewUser::class], [$apiUser, UserResource::class, ViewUser::class], [$role, RoleResource::class, ViewRole::class]] as [$owner, $resource, $page]) {
            foreach ($resource::getRelations() as $relationManager) {
                if ($relationManager::canViewForRecord($owner, $page)) {
                    Livewire::test($relationManager, ['ownerRecord' => $owner, 'pageClass' => $page])->assertOk();
                }
            }
        }
    }
}
