<?php

declare(strict_types=1);

namespace App\Domains\Auth\Models;

use App\Domains\Auth\Enums\AccessTokenStatusEnum;
use App\Domains\Core\Models\BaseModel;
use App\Domains\User\Models\User;
use Carbon\Carbon;
use Database\Factories\Domains\Auth\Models\AccessTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use SensitiveParameter;

/**
 * @property list<string> $allowed_ips List of IP addresses from which this token can be used.
 */
class AccessToken extends BaseModel
{
    /** @use HasFactory<AccessTokenFactory> */
    use HasFactory;

    protected $casts = [
        'token_prefix' => 'encrypted',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'allowed_ips' => 'array',
    ];

    protected $hidden = ['token_hash'];

    protected array $auditExclude = ['token_hash'];

    protected $appends = ['status'];

    /**
     * Order Access Tokens by their operational relevance.
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
            // Active -> Expired -> Revoked
            ->orderByRaw(
                'CASE
                    WHEN revoked_at IS NOT NULL THEN 0
                    WHEN expires_at IS NOT NULL AND expires_at < ? THEN 1
                    ELSE 2
                 END DESC',
                [$at]
            )
            // Used tokens before never used ones
            ->orderByRaw('last_used_at IS NOT NULL DESC')
            // For used tokens, most recently used first
            ->orderByDesc('last_used_at')
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
            ->where(
                fn ($q) => $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', $at)
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
     * @return BelongsTo<AccessToken, $this>
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
        return $this->hasMany(ApiRequestLog::class, 'access_token_id');
    }

    /** @return Attribute<AccessTokenStatusEnum, never> */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: function () {
                return match (true) {
                    filled($this->revoked_at) => AccessTokenStatusEnum::REVOKED,
                    $this->expires_at?->isPast() => AccessTokenStatusEnum::EXPIRED,
                    default => AccessTokenStatusEnum::ACTIVE,
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
    public static function hashFromPlain(#[SensitiveParameter] string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }
}
