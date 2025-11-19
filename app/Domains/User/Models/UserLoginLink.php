<?php

declare(strict_types=1);

namespace App\Domains\User\Models;

use App\Domains\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLoginLink extends BaseModel
{
    protected $table = 'user_one_time_login_links';

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @comment If the login link is valid (not used and not expired)
     *
     * @return Attribute<bool, never>
     */
    protected function isValid(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => is_null($this->used_at) && $this->expires_at->isFuture(),
        );
    }

    public function markAsUsed(string $ipAddress): void
    {
        $this->update([
            'used_at' => now(),
            'used_ip_address' => $ipAddress,
        ]);
    }

    #[Scope]
    protected function unused(Builder $query): Builder
    {
        return $query->whereNull('used_at');
    }

    #[Scope]
    protected function notExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
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
