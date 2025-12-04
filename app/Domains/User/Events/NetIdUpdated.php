<?php

declare(strict_types=1);

namespace App\Domains\User\Events;

use App\Domains\User\Enums\NetIdUpdateActionEnum;
use App\Http\Controllers\Webhooks\NetIdUpdateController;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

/**
 * Fired when a message is delivered to the NetID update webhook.
 *
 * The webhook sends URL-encoded form data in the format of:
 * `netid=abc123&action=deactivate`
 *
 * @see NetIdUpdateController
 */
class NetIdUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly string $netId;

    public readonly NetIdUpdateActionEnum $action;

    /**
     * @throws InvalidArgumentException If the payload is malformed or missing required fields
     */
    public function __construct(string $rawPayload)
    {
        parse_str($rawPayload, $parsed);

        if (! isset($parsed['netid'], $parsed['action'])) {
            throw new InvalidArgumentException('Webhook payload missing required fields: netid and action');
        }

        $this->netId = strtolower(trim($parsed['netid']));
        $actionValue = strtolower(trim($parsed['action']));

        $action = NetIdUpdateActionEnum::tryFrom($actionValue);
        if ($action === null) {
            throw new InvalidArgumentException("Unknown action type: {$actionValue}");
        }

        $this->action = $action;
    }
}
