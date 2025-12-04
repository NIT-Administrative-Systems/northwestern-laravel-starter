<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\User\Events;

use App\Domains\User\Enums\NetIdUpdateActionEnum;
use App\Domains\User\Events\NetIdUpdated;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(NetIdUpdated::class)]
class NetIdUpdatedTest extends TestCase
{
    public function test_it_parses_valid_webhook_payload(): void
    {
        $payload = 'netid=abc123&action=deactivate';

        $event = new NetIdUpdated($payload);

        $this->assertEquals('abc123', $event->netId);
        $this->assertEquals(NetIdUpdateActionEnum::DEACTIVATE, $event->action);
    }

    public function test_it_normalizes_netid_to_lowercase(): void
    {
        $payload = 'netid=ABC123&action=deactivate';

        $event = new NetIdUpdated($payload);

        $this->assertEquals('abc123', $event->netId);
    }

    public function test_it_normalizes_action_to_lowercase(): void
    {
        $payload = 'netid=test&action=DEACTIVATE';

        $event = new NetIdUpdated($payload);

        $this->assertEquals(NetIdUpdateActionEnum::DEACTIVATE, $event->action);
    }

    public function test_it_handles_url_encoded_characters(): void
    {
        $payload = 'netid=test%40user&action=deactivate';

        $event = new NetIdUpdated($payload);

        $this->assertEquals('test@user', $event->netId);
    }

    public function test_it_throws_exception_when_netid_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Webhook payload missing required fields: netid and action');

        $payload = 'action=deactivate';

        new NetIdUpdated($payload);
    }

    public function test_it_throws_exception_when_action_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Webhook payload missing required fields: netid and action');

        $payload = 'netid=abc123';

        new NetIdUpdated($payload);
    }

    public function test_it_throws_exception_when_both_fields_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Webhook payload missing required fields: netid and action');

        $payload = '';

        new NetIdUpdated($payload);
    }

    public function test_it_throws_exception_for_unknown_action(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown action type: unknownaction');

        $payload = 'netid=test&action=unknownaction';

        new NetIdUpdated($payload);
    }

    public function test_it_handles_extra_parameters_gracefully(): void
    {
        $payload = 'netid=abc123&action=deactivate&extra=ignored&another=alsoingnored';

        $event = new NetIdUpdated($payload);

        $this->assertEquals('abc123', $event->netId);
        $this->assertEquals(NetIdUpdateActionEnum::DEACTIVATE, $event->action);
    }

    public function test_it_handles_parameters_in_different_order(): void
    {
        $payload = 'action=deactivate&netid=abc123';

        $event = new NetIdUpdated($payload);

        $this->assertEquals('abc123', $event->netId);
        $this->assertEquals(NetIdUpdateActionEnum::DEACTIVATE, $event->action);
    }
}
