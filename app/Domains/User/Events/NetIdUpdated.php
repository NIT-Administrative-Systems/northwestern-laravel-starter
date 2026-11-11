<?php

declare(strict_types=1);

namespace App\Domains\User\Events;

use App\Domains\User\Enums\NetIdUpdateAction;
use App\Domains\User\Http\Controllers\Webhooks\NetIdUpdateController;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

/**
 * Dispatched for each message the NetID update webhook receives.
 *
 * The payload is form data, such as `netid=abc123&action=deactivate`.
 *
 * @see NetIdUpdateController
 */
class NetIdUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $netId,
        public readonly NetIdUpdateAction $action,
    ) {
    }

    /**
     * Parse a raw URL-encoded webhook payload into an event instance.
     *
     * @throws InvalidArgumentException If the payload is malformed or missing required fields
     */
    public static function fromPayload(string $rawPayload): self
    {
        parse_str($rawPayload, $parsed);

        if (! isset($parsed['netid'], $parsed['action'])
            || ! is_string($parsed['netid'])
            || ! is_string($parsed['action'])
        ) {
            throw new InvalidArgumentException('Webhook payload missing required fields: netid and action');
        }

        $netId = strtolower(trim($parsed['netid']));
        $actionValue = strtolower(trim($parsed['action']));

        $action = NetIdUpdateAction::tryFrom($actionValue);
        if ($action === null) {
            throw new InvalidArgumentException("Unknown action type: {$actionValue}");
        }

        return new self($netId, $action);
    }
}
