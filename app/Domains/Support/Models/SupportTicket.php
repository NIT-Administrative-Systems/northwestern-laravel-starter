<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use App\Domains\Core\Models\BaseModel;
use App\Domains\Support\Enums\TicketSystemEnum;
use App\Domains\User\Models\User;
use Database\Factories\Domains\Support\Models\SupportTicketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mews\Purifier\Casts\CleanHtml;

/**
 * A user-submitted support ticket persisted as a local submission record.
 *
 * This model represents the platform's record of a support request — not the
 * external ticket itself. The {@see $ticketing_system} and {@see $ticket_number}
 * fields reflect which gateway handled the submission and the reference it returned.
 */
class SupportTicket extends BaseModel
{
    /** @use HasFactory<SupportTicketFactory> */
    use HasFactory, SoftDeletes;

    protected $casts = [
        'ticketing_system' => TicketSystemEnum::class,
        'posted_to_ticketing_system_at' => 'datetime',
        'fallback_sent_at' => 'datetime',
        'post_error' => 'boolean',
        'details' => CleanHtml::class,
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wasPostedSuccessfully(): bool
    {
        return ! $this->post_error && $this->posted_to_ticketing_system_at !== null;
    }
}
