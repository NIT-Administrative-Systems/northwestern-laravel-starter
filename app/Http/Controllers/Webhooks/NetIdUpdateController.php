<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Domains\User\Events\NetIdUpdated;
use App\Domains\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

/**
 * Handles incoming webhook notifications from Northwestern's **NetID Update** message topic.
 *
 * Messages are delivered when NetIDs are deactivated, deprovisioned, put on security hold,
 * and other actions. This controller validates the payload and dispatches an event for
 * relevant updates.
 */
class NetIdUpdateController extends Controller
{
    public function __invoke(Request $request): Response
    {
        try {
            $event = new NetIdUpdated($request->getContent());
        } catch (InvalidArgumentException $e) {
            return response($e->getMessage(), 204);
        }

        $user = User::query()
            ->sso()
            ->firstWhere('username', $event->netId);

        if ($user === null) {
            return response("NetID [{$event->netId}] not known to this application.", 204);
        }

        Event::dispatch($event);

        return response('Message received');
    }
}
