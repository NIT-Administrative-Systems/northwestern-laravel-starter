<?php

declare(strict_types=1);

namespace App\Domains\User\Models;

use App\Domains\Core\Models\BaseModel;
use App\Domains\User\Enums\ApiTokenStatusEnum;
use Carbon\Carbon;
use Database\Factories\Domains\User\Models\ApiTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property list<string> $allowed_ips List of IP addresses from which this token can be used.
 */
class ApiToken extends BaseModel
{
    protected $table = 'user_api_tokens';

    /** @use HasFactory<ApiTokenFactory> */
    use HasFactory;

    protected $casts = [
        'last_used_at' => 'datetime',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'revoked_at' => 'datetime',
        'allowed_ips' => 'array',
    ];

    protected $hidden = ['token_hash'];

    protected array $auditExclude = ['token_hash'];

    protected $appends = ['status'];

    /**
     * Order API tokens by their operational relevance.
     *
     * This is a useful default for UI presentation, so that most relevant tokens are shown first.
     *
     * @param  Carbon|null  $at  Optional reference time for determining token validity. Defaults to now.
     */
    #[Scope]
    protected function orderByRelevance(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= Carbon::now();

        return $query
            // Active -> Pending -> Expired -> Revoked
            ->orderByRaw(
                'CASE
                    WHEN revoked_at IS NOT NULL THEN 0
                    WHEN valid_to IS NOT NULL AND valid_to < ? THEN 1
                    WHEN valid_from > ? THEN 2
                    ELSE 3
                 END DESC',
                [$at, $at]
            )
            // Used tokens before never used ones
            ->orderByRaw('last_used_at IS NOT NULL DESC')
            // For used tokens, most recently used first
            ->orderByDesc('last_used_at')
            // Among never-used tokens, most recently valid-from first
            ->orderByDesc('valid_from')
            // Tie-breaker by ID (newest first)
            ->orderByDesc('id');
    }

    #[Scope]
    protected function active(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= Carbon::now();

        return $query
            ->whereNull('revoked_at')
            ->whereNotNull('token_hash')
            ->where('valid_from', '<=', $at)
            ->where(
                fn ($q) => $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', $at)
            );
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
    public function rotated_from_token(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rotated_from_token_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rotated_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rotated_by_user_id');
    }

    /**
     * @return HasMany<ApiRequestLog, $this>
     */
    public function request_logs(): HasMany
    {
        return $this->hasMany(ApiRequestLog::class, 'user_api_token_id');
    }

    /** @return Attribute<ApiTokenStatusEnum, never> */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: function () {
                return match (true) {
                    filled($this->revoked_at) => ApiTokenStatusEnum::REVOKED,
                    $this->valid_to?->isPast() => ApiTokenStatusEnum::EXPIRED,
                    $this->valid_from->isFuture() => ApiTokenStatusEnum::PENDING,
                    default => ApiTokenStatusEnum::ACTIVE,
                };
            }
        );
    }

    /**
     * Generate a hash for the given plain token.
     *
     * @param  string  $token  The plain token to hash.
     * @return string The resulting hash.
     */
    public static function hashFromPlain(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }
}
