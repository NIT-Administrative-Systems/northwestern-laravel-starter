<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use App\Domains\Core\Models\BaseModel;
use App\Domains\Support\Enums\TicketSystem;
use App\Domains\User\Models\User;
use Database\Factories\Domains\Support\Models\SupportTicketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\HtmlString;
use Northwestern\SysDev\Chassis\Models\Concerns\PrunesAfterRetentionPeriod;

/**
 * The application's record of a support request someone submitted.
 *
 * It isn't the ticket itself: ticketing_system and ticket_number say
 * which system took the request, and the reference it gave back.
 *
 * Kept forever unless `platform.retention.support_tickets` sets a number of days.
 */
class SupportTicket extends BaseModel
{
    /** @use HasFactory<SupportTicketFactory> */
    use HasFactory, PrunesAfterRetentionPeriod, SoftDeletes;

    protected $casts = [
        'ticketing_system' => TicketSystem::class,
        'posted_to_ticketing_system_at' => 'datetime',
        'fallback_sent_at' => 'datetime',
        'post_error' => 'boolean',
    ];

    protected function retentionConfigKey(): string
    {
        return 'platform.retention.support_tickets';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The details as HTML for the emails, TeamDynamix and Administration.
     *
     * It's the text as typed, escaped and with its line breaks.
     * People type plain text, so no HTML they enter renders.
     */
    public function detailsHtml(): HtmlString
    {
        return new HtmlString(nl2br(e((string) $this->details), false));
    }

    public function wasPostedSuccessfully(): bool
    {
        return ! $this->post_error && $this->posted_to_ticketing_system_at !== null;
    }
}
