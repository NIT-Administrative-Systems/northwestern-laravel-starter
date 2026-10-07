<?php

declare(strict_types=1);

namespace App\Domains\Api\Models;

use App\Domains\Core\Models\BaseModel;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;

/**
 * A person's connection to an OAuth application. It is recorded when the application first
 * gets a token for them and removed when they disconnect it; the tokens themselves are
 * Passport's.
 *
 * @property int $id
 * @property int $user_id
 * @property string $oauth_client_id
 * @property list<string> $scopes
 * @property Carbon $connected_at
 * @property Carbon|null $last_used_at
 * @property-read OAuthClient $oauth_client
 * @property-read User $user
 */
class OAuthConnection extends BaseModel
{
    protected $table = 'oauth_connections';

    protected $casts = [
        'scopes' => 'array',
        'connected_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    /** @var list<string> */
    protected array $auditExclude = ['last_used_at'];

    /**
     * Connections that still give the application access: it holds an access token that
     * hasn't expired, or a refresh token that can get another.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function live(Builder $query): Builder
    {
        $tokens = Passport::token()->getTable();
        $refreshTokens = Passport::refreshToken()->getTable();
        $now = Carbon::now();

        return $query->whereExists(fn ($token) => $token
            ->select(DB::raw(1))
            ->from($tokens)
            ->whereColumn("{$tokens}.user_id", 'oauth_connections.user_id')
            ->whereColumn("{$tokens}.client_id", 'oauth_connections.oauth_client_id')
            ->where("{$tokens}.revoked", false)
            ->where(fn ($live) => $live
                ->where("{$tokens}.expires_at", '>', $now)
                ->orWhereExists(fn ($refresh) => $refresh
                    ->select(DB::raw(1))
                    ->from($refreshTokens)
                    ->whereColumn("{$refreshTokens}.access_token_id", "{$tokens}.id")
                    ->where("{$refreshTokens}.revoked", false)
                    ->where("{$refreshTokens}.expires_at", '>', $now))));
    }

    /** @return BelongsTo<OAuthClient, $this> */
    public function oauth_client(): BelongsTo
    {
        return $this->belongsTo(OAuthClient::class, 'oauth_client_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
