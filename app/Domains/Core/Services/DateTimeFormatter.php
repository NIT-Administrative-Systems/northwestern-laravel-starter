<?php

declare(strict_types=1);

namespace App\Domains\Core\Services;

use App\Domains\User\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class DateTimeFormatter
{
    public function datetime(CarbonInterface|string|null $datetime, User $user, ?string $format = null): string
    {
        if (! $datetime) {
            return 'n/a';
        }

        $format ??= (string) config('app.datetime_display_format');

        /**
         * Intended to handle `format()` on a column in a Livewire DataTable, since the query returns string from the
         * database without letting Eloquent cast it to a Carbon instance.
         */
        if (is_string($datetime)) {
            $datetime = new Carbon($datetime);
        }

        return $datetime->copy()
            ->setTimezone($user->timezone)
            ->format($format);
    }

    public function buildDatetimeDirective(): callable
    {
        return function ($expression): string {
            $class = '\\' . self::class;

            return "<?php echo resolve({$class}::class)->datetime({$expression}, auth()->user()); ?>";
        };
    }
}
