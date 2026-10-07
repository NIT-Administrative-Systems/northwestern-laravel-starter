<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Actions\Api;

use App\Domains\Auth\Actions\Api\AuditServiceClientChange;
use App\Domains\Auth\Actions\Api\CreateServiceClient;
use App\Domains\Auth\Actions\Api\RevokeServiceClient;
use App\Domains\Auth\Actions\Api\UpdateServiceClientIpRestrictions;
use App\Domains\Auth\Actions\Applications\RegisterOAuthApplication;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\User\Enums\AuditEvent;
use App\Domains\User\Models\Audit;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(UpdateServiceClientIpRestrictions::class)]
#[CoversClass(AuditServiceClientChange::class)]
final class UpdateServiceClientIpRestrictionsTest extends TestCase
{
    public function test_it_replaces_the_allowlist_and_audits_the_previous_one(): void
    {
        [, $client] = resolve(CreateServiceClient::class)(User::factory()->api()->create(), 'Sync', now()->addDays(30), ['10.0.0.0/8']);

        resolve(UpdateServiceClientIpRestrictions::class)($client, ['192.0.2.0/24'], $this->administrator());

        $this->assertSame(['192.0.2.0/24'], $client->fresh()?->allowed_ips);

        $audit = Audit::query()->where('event', 'service_client_ip_restrictions_updated')->sole();
        $this->assertSame(['allowed_ips' => ['10.0.0.0/8']], $audit->old_values);
        $this->assertSame(['192.0.2.0/24'], $audit->new_values['allowed_ips']);
    }

    public function test_an_empty_list_allows_every_ip(): void
    {
        [, $client] = resolve(CreateServiceClient::class)(User::factory()->api()->create(), 'Sync', now()->addDays(30), ['10.0.0.0/8']);

        resolve(UpdateServiceClientIpRestrictions::class)($client, [], $this->administrator());

        $this->assertNull($client->fresh()?->allowed_ips);
    }

    // An application has no API user to record the change on.
    public function test_a_client_without_an_api_user_is_not_audited(): void
    {
        [, $client] = resolve(RegisterOAuthApplication::class)('Reporting Tool', ['https://reports.example.edu/callback'], true, []);

        resolve(AuditServiceClientChange::class)($client, AuditEvent::ServiceClientRevoked);

        $this->assertFalse(Audit::query()->where('event', 'service_client_revoked')->exists());
    }

    public function test_someone_without_manage_api_access_is_refused(): void
    {
        [, $client] = resolve(CreateServiceClient::class)(User::factory()->api()->create(), 'Sync', now()->addDays(30), ['10.0.0.0/8']);

        try {
            resolve(UpdateServiceClientIpRestrictions::class)($client, [], User::factory()->affiliate()->create());
            $this->fail('IP restrictions changed without Manage API Access.');
        } catch (AuthorizationException) {
            $this->assertSame(['10.0.0.0/8'], $client->fresh()?->allowed_ips);
        }
    }

    public function test_a_revoked_client_cannot_be_changed(): void
    {
        [, $client] = resolve(CreateServiceClient::class)(User::factory()->api()->create(), 'Sync', now()->addDays(30));
        resolve(RevokeServiceClient::class)($client);

        $this->expectException(InvalidArgumentException::class);

        resolve(UpdateServiceClientIpRestrictions::class)($client->fresh() ?? $client, ['192.0.2.0/24'], $this->administrator());
    }

    public function test_a_client_without_an_api_user_has_no_ip_restrictions(): void
    {
        [, $client] = resolve(RegisterOAuthApplication::class)('Reporting Tool', ['https://reports.example.edu/callback'], true, []);

        $this->expectException(InvalidArgumentException::class);

        resolve(UpdateServiceClientIpRestrictions::class)($client, ['192.0.2.0/24'], $this->administrator());
    }

    private function administrator(): User
    {
        $administrator = User::factory()->affiliate()->create();
        $administrator->givePermissionTo(SystemPermission::ManageApiAccess);

        return $administrator;
    }
}
