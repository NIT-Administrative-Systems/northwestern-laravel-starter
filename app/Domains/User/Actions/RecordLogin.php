<?php

declare(strict_types=1);

namespace App\Domains\User\Actions;

use App\Domains\Auth\Http\Controllers\Local\VerifyLoginCodeController;
use App\Domains\Auth\Http\Controllers\WebSSOController;
use App\Domains\User\Models\User;
use Illuminate\Http\Request;

/**
 * Records a login event for a user after successful authentication.
 *
 * Captures the user's segment at the time of login (for historical metrics)
 * along with request metadata (IP address, user agent). Called from each
 * authentication controller after the user has been verified.
 *
 * @see WebSSOController
 * @see VerifyLoginCodeController
 * @see DetermineUserSegment
 */
readonly class RecordLogin
{
    public function __construct(
        private DetermineUserSegment $determineUserSegment,
    ) {
    }

    public function __invoke(User $user, Request $request): void
    {
        $user->login_records()->create([
            'logged_in_at' => now(),
            'segment' => ($this->determineUserSegment)($user),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
