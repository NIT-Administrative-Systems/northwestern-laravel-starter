<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Core\Enums\AuditEvent;
use App\Domains\User\Actions\Lifecycle\DeactivateUser;
use App\Domains\User\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Deactivates local (email-code) accounts nobody has signed in to for the number
 * of days in `platform.accounts.local_inactivity_days`, counting the day an
 * account was created or reactivated as a sign-in. Northwestern accounts
 * are left to the NetID lifecycle, and API users to their credentials.
 *
 * The last sign-in comes from the sign-in records and the reactivation
 * from the Account Reactivated audit. Keep both retention periods at
 * least as long as this, or someone recently active looks inactive.
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
            ->whereDoesntHave('login_records', fn (Builder $query) => $query->where('logged_in_at', '>=', $cutoff))
            ->whereDoesntHave('audits', fn (Builder $query) => $query
                ->where('event', AuditEvent::AccountReactivated->value)
                ->where('created_at', '>=', $cutoff));

        $deactivated = 0;

        foreach ($inactive->lazyById() as $user) {
            $deactivate($user, null, ['reason' => "no sign-in for {$days} days"]);
            $deactivated++;
        }

        $this->components->info("Deactivated {$deactivated} local " . Str::plural('account', $deactivated) . " with no sign-in for {$days} days.");

        return self::SUCCESS;
    }
}
