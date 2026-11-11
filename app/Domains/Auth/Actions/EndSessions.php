<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions;

use App\Domains\Api\Actions\RevokeAllCredentials;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Signs a person out everywhere, deleting their sessions and cycling their
 * remember token, so that no browser signed in as them stays signed in.
 *
 * Their API credentials are separate: {@see RevokeAllCredentials} revokes those.
 *
 * Sessions can be found by person only with the database session
 * driver, the starter's default. With another driver they end
 * when they expire, and the remember token still changes.
 */
readonly class EndSessions
{
    public function __invoke(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->delete();
        }

        // Quietly: the token isn't audited, and the change that called this is recorded.
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }
}
