<?php

declare(strict_types=1);

namespace App\Domains\Auth\Models;

use App\Domains\Core\Models\BaseModel;
use App\Domains\User\Models\User;
use Database\Factories\Domains\Auth\Models\ImpersonationLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Northwestern\SysDev\Chassis\Models\Concerns\PrunesAfterRetentionPeriod;

/**
 * Who impersonated whom, and when: one entry each time impersonation starts.
 *
 * @see \App\Domains\Auth\Actions\Impersonation\StartImpersonation
 * @see \App\Domains\Auth\Actions\Impersonation\StopImpersonation
 */
class ImpersonationLog extends BaseModel
{
    /** @use HasFactory<ImpersonationLogFactory> */
    use HasFactory, PrunesAfterRetentionPeriod;

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

    protected function retentionConfigKey(): string
    {
        return 'platform.retention.impersonation_logs';
    }
}
