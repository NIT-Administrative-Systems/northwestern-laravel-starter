<?php

declare(strict_types=1);

namespace App\Domains\User\Models;

use App\Domains\Core\Enums\ApiRequestFailureEnum;
use Database\Factories\Domains\User\Models\ApiRequestLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiRequestLog extends Model
{
    /** @use HasFactory<ApiRequestLogFactory> */
    use HasFactory, MassPrunable;

    public const null UPDATED_AT = null;

    protected $casts = [
        'failure_reason' => ApiRequestFailureEnum::class,
        'created_at' => 'datetime',
    ];

    /**
     * Automatically deletes logs older than the configured retention period.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $retentionDays = (int) config('auth.api.request_logging.retention_days', 90);

        if ($retentionDays <= 0) {
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()->where('created_at', '<', now()->subDays($retentionDays));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ApiToken, $this>
     */
    public function api_token(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class, 'user_api_token_id');
    }
}
