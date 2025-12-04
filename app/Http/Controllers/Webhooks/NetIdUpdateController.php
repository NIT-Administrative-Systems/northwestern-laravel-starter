<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

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
 * Handles webhook notifications from Northwestern's **NetID Update** message topic.
 *
 * NetID update messages are sent when a user is deactivated, deprovisioned,
 * placed on security hold, or other lifecycle changes.
 *
 * @see NetIdUpdated For payload parsing and event originination
 * @see ProcessNetIdUpdate For event handling
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
            $event = new NetIdUpdated($payload);
        } catch (Throwable $e) {
            /**
             * Unexpected errors should be reported but still return a successful
             * response. We don't want malformed requests to hit the DLQ and
             * continue retrying invalid payloads.
             */
            report_unless($e instanceof InvalidArgumentException, $e);

            return response()->json([
                'status' => self::STATUS_IGNORED,
                'reason' => self::REASON_INVALID_PAYLOAD,
                'message' => $e->getMessage(),
            ]);
        }

        $user = User::query()
            ->sso()
            ->firstWhere('username', $event->netId);

        if ($user === null) {
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
