<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Webhooks;

use App\Domains\Core\Concerns\MocksEventHub;
use App\Domains\User\Enums\AuthTypeEnum;
use App\Domains\User\Events\NetIdUpdated;
use App\Domains\User\Models\User;
use App\Http\Controllers\Webhooks\NetIdUpdateController;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(NetIdUpdateController::class)]
class NetIdUpdateControllerTest extends TestCase
{
    use MocksEventHub, WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

    public function test_it_dispatches_event_for_deactivation_action(): void
    {
        User::factory()->create(['username' => 'abc123', 'auth_type' => AuthTypeEnum::SSO]);

        $response = $this->send('etidentity.ldap.netid.term', 'netid=abc123&action=deactivate');

        $response->assertSuccessful();
        Event::assertDispatched(NetIdUpdated::class);
    }

    public function test_it_dispatches_event_for_deprovision_action(): void
    {
        User::factory()->create(['username' => 'test123', 'auth_type' => AuthTypeEnum::SSO]);

        $response = $this->send('etidentity.ldap.netid.term', 'netid=test123&action=deprovision');

        $response->assertSuccessful();
        Event::assertDispatched(NetIdUpdated::class);
    }

    public function test_it_dispatches_event_for_security_hold_action(): void
    {
        User::factory()->create(['username' => 'sec456', 'auth_type' => AuthTypeEnum::SSO]);

        $response = $this->send('etidentity.ldap.netid.term', 'netid=sec456&action=sechold');

        $response->assertSuccessful();
        Event::assertDispatched(NetIdUpdated::class);
    }

    public function test_it_returns_no_content_for_unknown_netid(): void
    {
        $response = $this->send('etidentity.ldap.netid.term', 'netid=unknown&action=deactivate');

        $response->assertNoContent();
        Event::assertNotDispatched(NetIdUpdated::class);
    }

    public function test_it_returns_no_content_for_malformed_payload(): void
    {
        $response = $this->send('etidentity.ldap.netid.term', 'invalid payload data');

        $response->assertNoContent();
        Event::assertNotDispatched(NetIdUpdated::class);
    }

    public function test_it_returns_no_content_for_unknown_action(): void
    {
        User::factory()->create(['username' => 'test123', 'auth_type' => AuthTypeEnum::SSO]);

        $response = $this->send('etidentity.ldap.netid.term', 'netid=test123&action=unknownaction');

        $response->assertNoContent();
        Event::assertNotDispatched(NetIdUpdated::class);
    }
}
