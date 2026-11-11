<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\User\Actions\Lifecycle\DeactivateUser;
use App\Domains\User\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Deactivates local (email-code) accounts nobody has signed in to for the number
 * of days in `platform.accounts.local_inactivity_days`. A new account gets the
 * same time to be used for the first time. Northwestern accounts are left to
 * the NetID lifecycle, and API users have their own credentials' expiry.
 *
 * The last sign-in comes from the sign-in records, so keep the retention
 * of `platform.retention.login_records` at least as long as this, or
 * someone who signed in before the oldest record looks inactive.
 */
class DeactivateInactiveLocalUsersCommand extends Command
{
    protected $signature = 'users:deactivate-inactive-local';

    protected $description = 'Deactivate local accounts nobody has signed in to for the configured number of days';

    public function handle(DeactivateUser $deactivate): int
    {
        $days = config('platform.accounts.local_inactivity_days');

        if ($days === null || $days === '') {
            $this->components->info('Local accounts never expire. Set LOCAL_ACCOUNT_INACTIVITY_DAYS to expire them.');

            return self::SUCCESS;
        }

        $cutoff = Carbon::now()->subDays((int) $days);

        $inactive = User::query()
            ->local()
            ->where('created_at', '<', $cutoff)
            ->whereDoesntHave('login_records', fn (Builder $query) => $query->where('logged_in_at', '>=', $cutoff));

        $deactivated = 0;

        foreach ($inactive->lazyById() as $user) {
            $deactivate($user, null, ['reason' => "no sign-in for {$days} days"]);
            $deactivated++;
        }

        $this->components->info("Deactivated {$deactivated} local account(s) with no sign-in for {$days} days.");

        return self::SUCCESS;
    }
}
