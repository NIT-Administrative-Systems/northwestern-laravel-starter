<?php

declare(strict_types=1);

namespace App\Domains\User\Actions;

use App\Domains\Auth\SignIn;
use App\Domains\User\Models\User;
use Illuminate\Http\Request;

/**
 * Records a sign-in with the person's segment at the time, their IP address and
 * browser. SignIn::complete() calls it, as every sign-in method ends there.
 *
 * @see SignIn::complete()
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
