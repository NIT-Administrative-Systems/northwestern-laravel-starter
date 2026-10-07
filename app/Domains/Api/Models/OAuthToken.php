<?php

declare(strict_types=1);

namespace App\Domains\Api\Models;

use App\Domains\Api\Enums\CredentialStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;
use Laravel\Passport\Token;

/**
 * Passport's access token, with the starter's columns: when it was last used and when its
 * owner was last reminded that it expires.
 *
 * @property string $id
 * @property int|null $user_id
 * @property string $client_id
 * @property string|null $name
 * @property list<string> $scopes
 * @property bool $revoked
 * @property Carbon|null $expires_at
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expiration_notified_at
 * @property Carbon $created_at
 * @property-read CredentialStatus $status
 */
class OAuthToken extends Token
{
    protected $casts = [
        'scopes' => 'array',
        'revoked' => 'bool',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'expiration_notified_at' => 'datetime',
    ];

    /**
     * Personal access tokens: issued by a personal access client to the person who created them.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function personal(Builder $query): Builder
    {
        return $query->whereHas('client', fn (Builder $client) => $client->where('grant_types', 'like', '%"personal_access"%'));
    }

    /**
     * Tokens that still work: not revoked and not expired.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function active(Builder $query, ?CarbonInterface $at = null): Builder
    {
        return $query->where('revoked', false)->where('expires_at', '>', $at ?? Carbon::now());
    }

    /** @return Attribute<CredentialStatus, never> */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn (): CredentialStatus => match (true) {
                $this->revoked => CredentialStatus::Revoked,
                $this->expires_at?->isPast() ?? false => CredentialStatus::Expired,
                default => CredentialStatus::Active,
            },
        )->withoutObjectCaching(); // An expiry passes without the model changing.
    }
}
