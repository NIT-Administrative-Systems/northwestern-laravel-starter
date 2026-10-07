<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use App\Domains\Core\Models\BaseModel;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A person's dismissal of an announcement from the banner. The announcement stays on the
 * Announcements page.
 *
 * @property int $announcement_id
 * @property int $user_id
 * @property Carbon $dismissed_at
 */
class AnnouncementDismissal extends BaseModel
{
    public $timestamps = false;

    /**
     * Not audited: dismissals are routine and per person. The announcement's own changes are.
     *
     * @var list<string>
     */
    protected $auditEvents = [];

    protected $casts = [
        'dismissed_at' => 'datetime',
    ];

    /** @return BelongsTo<Announcement, $this> */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
