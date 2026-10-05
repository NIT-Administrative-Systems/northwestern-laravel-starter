<?php

declare(strict_types=1);

namespace App\Domains\Support\Jobs;

use App\Domains\Auth\Enums\AuthType;
use App\Domains\Support\Enums\AnnouncementAudience;
use App\Domains\Support\Models\Announcement;
use App\Domains\Support\Notifications\AnnouncementNotification;
use App\Domains\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * Notifies an announcement's audience, once: the signed-in people it's for who can use the app
 * panel. Claims the announcement before sending, so a second dispatch sends nothing.
 */
class NotifyAnnouncementAudience implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Announcement $announcement,
    ) {
    }

    public function handle(): void
    {
        $claimed = Announcement::query()
            ->whereKey($this->announcement->getKey())
            ->where('notify_audience', true)
            ->whereNull('notified_at')
            ->update(['notified_at' => Carbon::now()]);

        if ($claimed === 0) {
            return;
        }

        self::audience($this->announcement)->chunkById(500, function (Collection $people): void {
            Notification::send($people, new AnnouncementNotification($this->announcement));
        });
    }

    /**
     * The people an announcement is for: everyone who can use the app panel, or, for a targeted
     * one, those holding one of its roles or having one of its affiliations.
     *
     * @return Builder<User>
     */
    public static function audience(Announcement $announcement): Builder
    {
        $people = User::query()
            ->where('auth_type', '!=', AuthType::API)
            ->where(fn (Builder $active) => $active->whereNull('netid_inactive')->orWhere('netid_inactive', false));

        if ($announcement->audience !== AnnouncementAudience::Targeted) {
            return $people;
        }

        return $people->where(fn (Builder $match) => $match
            ->whereHas('roles', fn (Builder $roles) => $roles->whereKey($announcement->role_ids ?? []))
            ->orWhereIn('primary_affiliation', $announcement->affiliations ?? []));
    }
}
