<?php

declare(strict_types=1);

namespace App\Domains\User\Models;

use App\Domains\Core\Models\BaseModel;
use Database\Factories\Domains\User\Models\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Impersonation audit log model.
 *
 * Records all user impersonation events for security and auditing purposes.
 * Each log entry captures:
 * - Who initiated the impersonation (impersonator)
 * - Who was impersonated (target user)
 * - When the impersonation started and ended
 * - The IP address where the impersonation occurred
 *
 * This provides a complete audit trail of all impersonation sessions,
 * which is critical for security compliance and troubleshooting.
 *
 * @see \App\Domains\Auth\Actions\Impersonation\StartImpersonation
 * @see \App\Domains\Auth\Actions\Impersonation\StopImpersonation
 */
class ImpersonationLog extends BaseModel
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $table = 'user_impersonation_logs';

    /** @return BelongsTo<User, $this> */
    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function impersonated(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonated_user_id');
    }
}
