<?php

declare(strict_types=1);

namespace App\Domains\Api\Models;

use App\Domains\Api\Actions\ServiceClients\AuditServiceClientChange;
use App\Domains\Api\Enums\ClientOrigin;
use App\Domains\Api\Enums\CredentialStatus;
use App\Domains\Api\Http\Middleware\AuthenticatePassportToken;
use App\Domains\User\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Uri;
use Laravel\Passport\Client;

/**
 * Passport's OAuth client, with the starter's own columns: where the client
 * came from, an IP allowlist, a secret expiry, and its rotation history.
 *
 * The starter has three kinds of client, each with its own scope here,
 * so that callers don't rebuild the rule: {@see serviceClients()},
 * {@see applications()} and {@see mcpClients()}. Passport keeps
 * one personal access client to issue personal access tokens.
 *
 * A client whose secret has expired counts as revoked, so Passport refuses it at
 * the token endpoint, and {@see AuthenticatePassportToken} refuses the tokens
 * it already holds. The `revoked` column still records an explicit revoke.
 *
 * Its UUID key doesn't fit the audits table, so changes are recorded
 * as custom audit events on a user: a service client's on its API
 * user ({@see AuditServiceClientChange}), and an application's
 * or MCP client's on the administrator who made the change.
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
 * @property string|null $client_metadata_url
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
     * Scope the query to clients that aren't revoked and whose secret hasn't expired.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function active(Builder $query, ?CarbonInterface $at = null): Builder
    {
        return $query
            ->where('revoked', false)
            ->where(fn (Builder $q) => $q->whereNull('secret_expires_at')->orWhere('secret_expires_at', '>', $at ?? now()));
    }

    /**
     * Scope the query to the client credentials clients that belong to API users.
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
     * Scope the query to applications an administrator registered for people to use.
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
     * Scope the query to MCP clients, the only clients that register themselves.
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
     * Determine whether the client is unusable: revoked, or past its secret expiry.
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

    /**
     * Determine whether this is an MCP client: one that registered itself, or one the
     * server created from the client metadata document the client identified itself with.
     */
    public function isMcpClient(): bool
    {
        return $this->origin === ClientOrigin::Dynamic;
    }

    /**
     * The host whose client metadata document gave this MCP client its name, such as
     * `claude.ai`, or null when the client named itself by registering.
     */
    public function verifiedBy(): ?string
    {
        return $this->client_metadata_url === null ? null : Uri::of($this->client_metadata_url)->host();
    }

    /**
     * Determine whether people connect it without being asked to approve it.
     *
     * An administrator can mark an application first-party, such as a university tool.
     *
     * @param  list<\Laravel\Passport\Scope>  $scopes
     */
    public function skipsAuthorization(\Illuminate\Contracts\Auth\Authenticatable $user, array $scopes): bool
    {
        return $this->first_party;
    }

    private function secretExpired(): bool
    {
        return $this->secret_expires_at?->isPast() ?? false;
    }
}
