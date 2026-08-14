<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\User\Events;

use App\Domains\User\Enums\NetIdUpdateAction;
use App\Domains\User\Events\NetIdUpdated;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NetIdUpdated::class)]
final class NetIdUpdatedTest extends TestCase
{
    public function test_it_parses_valid_webhook_payload(): void
    {
        $payload = 'netid=abc123&action=deactivate';

        $event = NetIdUpdated::fromPayload($payload);

        $this->assertSame('abc123', $event->netId);
        $this->assertSame(NetIdUpdateAction::Deactivate, $event->action);
    }

    public function test_it_normalizes_netid_to_lowercase(): void
    {
        $payload = 'netid=ABC123&action=deactivate';

        $event = NetIdUpdated::fromPayload($payload);

        $this->assertSame('abc123', $event->netId);
    }

    public function test_it_normalizes_action_to_lowercase(): void
    {
        $payload = 'netid=test&action=DEACTIVATE';

        $event = NetIdUpdated::fromPayload($payload);

        $this->assertSame(NetIdUpdateAction::Deactivate, $event->action);
    }

    public function test_it_handles_url_encoded_characters(): void
    {
        $payload = 'netid=test%40user&action=deactivate';

        $event = NetIdUpdated::fromPayload($payload);

        $this->assertSame('test@user', $event->netId);
    }

    public function test_it_throws_exception_when_netid_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Webhook payload missing required fields: netid and action');

        $payload = 'action=deactivate';

        NetIdUpdated::fromPayload($payload);
    }

    public function test_it_throws_exception_when_action_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Webhook payload missing required fields: netid and action');

        $payload = 'netid=abc123';

        NetIdUpdated::fromPayload($payload);
    }

    public function test_it_throws_exception_when_both_fields_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Webhook payload missing required fields: netid and action');

        $payload = '';

        NetIdUpdated::fromPayload($payload);
    }

    public function test_it_throws_exception_for_unknown_action(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Unknown action type: unknownaction');

        $payload = 'netid=test&action=unknownaction';

        NetIdUpdated::fromPayload($payload);
    }

    public function test_it_handles_extra_parameters_gracefully(): void
    {
        $payload = 'netid=abc123&action=deactivate&extra=ignored&another=alsoingnored';

        $event = NetIdUpdated::fromPayload($payload);

        $this->assertSame('abc123', $event->netId);
        $this->assertSame(NetIdUpdateAction::Deactivate, $event->action);
    }

    public function test_it_handles_parameters_in_different_order(): void
    {
        $payload = 'action=deactivate&netid=abc123';

        $event = NetIdUpdated::fromPayload($payload);

        $this->assertSame('abc123', $event->netId);
        $this->assertSame(NetIdUpdateAction::Deactivate, $event->action);
    }

    public function test_constructor_accepts_typed_values_directly(): void
    {
        $event = new NetIdUpdated('abc123', NetIdUpdateAction::Deactivate);

        $this->assertSame('abc123', $event->netId);
        $this->assertSame(NetIdUpdateAction::Deactivate, $event->action);
    }
}
