<?php

declare(strict_types=1);

namespace App\Domains\Auth\Models;

use App\Domains\Auth\Enums\ClientOrigin;
use App\Domains\Auth\Enums\CredentialStatus;
use App\Domains\User\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Laravel\Passport\Client;

/**
 * Passport's OAuth client, with the starter's columns: where the client came from, an IP
 * allowlist, a secret expiry, and rotation history.
 *
 * The starter has three kinds, each with a scope here so callers don't rebuild the rule:
 * {@see serviceClients()}, {@see applications()} and {@see mcpClients()}. Passport also
 * keeps one personal access client, which issues personal access tokens.
 *
 * A client whose secret has expired counts as revoked, so Passport refuses it at the token
 * endpoint and {@see \App\Domains\Auth\Http\Middleware\AuthenticatePassportToken} refuses
 * the tokens it already holds. `revoked` in the database still records an explicit revoke.
 *
 * Changes are audited on the owning API user by {@see \App\Domains\Auth\Actions\Api\AuditServiceClientChange}.
 *
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string|null $contact_email
 * @property bool $first_party
 * @property list<string>|null $scopes
 * @property list<string> $redirect_uris
 * @property string|null $secret
 * @property list<string> $grant_types
 * @property ClientOrigin $origin
 * @property list<string>|null $allowed_ips
 * @property Carbon|null $secret_expires_at
 * @property Carbon|null $secret_expiration_notified_at
 * @property Carbon|null $last_used_at
 * @property bool $revoked
 * @property-read CredentialStatus $status
 */
class OAuthClient extends Client
{
    protected $casts = [
        'grant_types' => 'array',
        'scopes' => 'array',
        'redirect_uris' => 'array',
        'revoked' => 'bool',
        'origin' => ClientOrigin::class,
        'first_party' => 'bool',
        'allowed_ips' => 'array',
        'secret_expires_at' => 'datetime',
        'secret_expiration_notified_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    /**
     * Clients that can still be used: not revoked, and with a secret that hasn't expired.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function active(Builder $query, ?CarbonInterface $at = null): Builder
    {
        return $query
            ->where('revoked', false)
            ->where(fn (Builder $q) => $q->whereNull('secret_expires_at')->orWhere('secret_expires_at', '>', $at ?? Carbon::now()));
    }

    /**
     * Service clients: client-credentials clients an administrator created for an API user.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function serviceClients(Builder $query): Builder
    {
        return $query->where('origin', ClientOrigin::Administrator)->whereHasMorph('owner', [User::class]);
    }

    /**
     * Applications an administrator registered for people to connect through the authorization code flow.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function applications(Builder $query): Builder
    {
        return $query->where('origin', ClientOrigin::Administrator)->where('grant_types', 'like', '%"authorization_code"%');
    }

    /**
     * MCP clients, which are the only clients that register themselves (D66).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function mcpClients(Builder $query): Builder
    {
        return $query->where('origin', ClientOrigin::Dynamic);
    }

    /**
     * Whether this is an MCP client. It registered itself, so its name is whatever it claimed.
     */
    public function isMcpClient(): bool
    {
        return $this->origin === ClientOrigin::Dynamic;
    }

    /** @return BelongsTo<self, $this> */
    public function rotated_from_client(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rotated_from_client_id');
    }

    /** @return BelongsTo<User, $this> */
    public function rotated_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rotated_by_user_id');
    }

    /** @return HasMany<OAuthConnection, $this> */
    public function connections(): HasMany
    {
        return $this->hasMany(OAuthConnection::class, 'oauth_client_id');
    }

    /**
     * An administrator can mark an application first-party, such as the organization's own
     * tool, so people aren't asked to approve it.
     *
     * @param  list<\Laravel\Passport\Scope>  $scopes
     */
    public function skipsAuthorization(\Illuminate\Contracts\Auth\Authenticatable $user, array $scopes): bool
    {
        return $this->first_party;
    }

    /**
     * Whether the client is unusable, either revoked or past its secret expiry.
     *
     * @return Attribute<bool, bool>
     */
    protected function revoked(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): bool => (bool) $value || $this->secretExpired(),
            set: fn (bool $value): bool => $value,
        );
    }

    /** @return Attribute<CredentialStatus, never> */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn (): CredentialStatus => match (true) {
                (bool) ($this->attributes['revoked'] ?? false) => CredentialStatus::Revoked,
                $this->secretExpired() => CredentialStatus::Expired,
                default => CredentialStatus::Active,
            },
        )->withoutObjectCaching(); // An expiry passes without the model changing.
    }

    private function secretExpired(): bool
    {
        return $this->secret_expires_at?->isPast() ?? false;
    }
}
