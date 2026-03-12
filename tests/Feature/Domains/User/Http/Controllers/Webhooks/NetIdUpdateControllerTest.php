<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Http\Controllers\Webhooks;

use App\Domains\Auth\Enums\AuthType;
use App\Domains\Core\Concerns\MocksEventHub;
use App\Domains\User\Enums\NetIdUpdateAction;
use App\Domains\User\Events\NetIdUpdated;
use App\Domains\User\Http\Controllers\Webhooks\NetIdUpdateController;
use App\Domains\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(NetIdUpdateController::class)]
class NetIdUpdateControllerTest extends TestCase
{
    use MocksEventHub, RefreshDatabase, WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        Route::post('netid-update', NetIdUpdateController::class)->eventHubWebhook('etidentity.ldap.netid.term')->name('netid-update');

        Event::fake();
    }

    public function test_it_dispatches_event_for_deactivation_action(): void
    {
        User::factory()->create(['username' => 'abc123', 'auth_type' => AuthType::Sso]);

        $response = $this->send('etidentity.ldap.netid.term', 'netid=abc123&action=deactivate');

        $response
            ->assertAccepted()
            ->assertJson([
                'status' => NetIdUpdateController::STATUS_ACCEPTED,
                'netid' => 'abc123',
                'action' => NetIdUpdateAction::Deactivate->value,
            ]);

        Event::assertDispatched(fn (NetIdUpdated $event) => $event->netId === 'abc123'
            && $event->action === NetIdUpdateAction::Deactivate);
    }

    public function test_it_dispatches_event_for_deprovision_action(): void
    {
        User::factory()->create(['username' => 'test123', 'auth_type' => AuthType::Sso]);

        $response = $this->send('etidentity.ldap.netid.term', 'netid=test123&action=deprovision');

        $response
            ->assertAccepted()
            ->assertJson([
                'status' => NetIdUpdateController::STATUS_ACCEPTED,
                'netid' => 'test123',
                'action' => NetIdUpdateAction::Deprovision->value,
            ]);

        Event::assertDispatched(fn (NetIdUpdated $event) => $event->netId === 'test123'
            && $event->action === NetIdUpdateAction::Deprovision);
    }

    public function test_it_dispatches_event_for_security_hold_action(): void
    {
        User::factory()->create(['username' => 'sec456', 'auth_type' => AuthType::Sso]);

        $response = $this->send('etidentity.ldap.netid.term', 'netid=sec456&action=sechold');

        $response
            ->assertAccepted()
            ->assertJson([
                'status' => NetIdUpdateController::STATUS_ACCEPTED,
                'netid' => 'sec456',
                'action' => NetIdUpdateAction::SecurityHold->value,
            ]);

        Event::assertDispatched(fn (NetIdUpdated $event) => $event->netId === 'sec456'
            && $event->action === NetIdUpdateAction::SecurityHold);
    }

    public function test_it_returns_ignored_for_unknown_users(): void
    {
        $response = $this->send('etidentity.ldap.netid.term', 'netid=unknown9999&action=deactivate');

        $response
            ->assertOk()
            ->assertJson([
                'status' => NetIdUpdateController::STATUS_IGNORED,
                'reason' => NetIdUpdateController::REASON_UNKNOWN_USER,
                'netid' => 'unknown9999',
            ]);

        Event::assertNotDispatched(NetIdUpdated::class);
    }

    public function test_it_returns_ignored_json_for_malformed_payload(): void
    {
        $response = $this->send('etidentity.ldap.netid.term', 'invalid payload data');

        $response
            ->assertOk()
            ->assertJson([
                'status' => NetIdUpdateController::STATUS_IGNORED,
                'reason' => NetIdUpdateController::REASON_INVALID_PAYLOAD,
            ]);

        Event::assertNotDispatched(NetIdUpdated::class);
    }

    public function test_it_returns_ignored_json_for_unknown_action(): void
    {
        $response = $this->send('etidentity.ldap.netid.term', 'netid=test123&action=unknownaction');

        $response
            ->assertOk()
            ->assertJson([
                'status' => NetIdUpdateController::STATUS_IGNORED,
                'reason' => NetIdUpdateController::REASON_INVALID_PAYLOAD,
            ]);

        Event::assertNotDispatched(NetIdUpdated::class);
    }
}
