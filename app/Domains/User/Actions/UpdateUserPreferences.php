<?php

declare(strict_types=1);

namespace App\Domains\User\Actions;

use App\Domains\User\Data\UserPreferences;
use App\Domains\User\Models\User;
use DateTimeZone;
use InvalidArgumentException;

class UpdateUserPreferences
{
    public function __invoke(User $user, string $timezone, UserPreferences $preferences): User
    {
        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException("{$timezone} is not a timezone identifier.");
        }

        $user->timezone = $timezone;
        $user->preferences = $preferences;
        $user->save();

        return $user;
    }
}
