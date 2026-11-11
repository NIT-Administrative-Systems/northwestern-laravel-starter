<?php

declare(strict_types=1);

namespace App\Domains\User\Http\Controllers\Webhooks;

use App\Domains\User\Events\NetIdUpdated;
use App\Domains\User\Listeners\ProcessNetIdUpdate;
use App\Domains\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Throwable;

/**
 * Receives Northwestern's NetID Update messages from EventHub.
 *
 * They arrive when a NetID is deactivated, deprovisioned or put on security hold.
 *
 * @see NetIdUpdated
 * @see ProcessNetIdUpdate
 */
class NetIdUpdateController extends Controller
{
    public const string STATUS_ACCEPTED = 'accepted';

    public const string STATUS_IGNORED = 'ignored';

    public const string REASON_INVALID_PAYLOAD = 'invalid-payload';

    public const string REASON_UNKNOWN_USER = 'unknown-user';

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = $request->getContent();
            $event = NetIdUpdated::fromPayload($payload);
        } catch (Throwable $e) {
            // Anything unexpected is reported, but the response is still a success,
            // so the dead letter queue doesn't retry a malformed message forever.
            report_unless($e instanceof InvalidArgumentException, $e);

            return response()->json([
                'status' => self::STATUS_IGNORED,
                'reason' => self::REASON_INVALID_PAYLOAD,
                'message' => $e->getMessage(),
            ]);
        }

        $isKnownNetId = User::query()
            ->withTrashed()
            ->sso()
            ->where('username', $event->netId)
            ->exists();

        if (! $isKnownNetId) {
            return response()->json([
                'status' => self::STATUS_IGNORED,
                'reason' => self::REASON_UNKNOWN_USER,
                'netid' => $event->netId,
            ]);
        }

        Event::dispatch($event);

        return response()->json([
            'status' => self::STATUS_ACCEPTED,
            'netid' => $event->netId,
            'action' => $event->action->value,
        ], 202);
    }
}
