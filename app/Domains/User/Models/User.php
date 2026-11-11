<?php

declare(strict_types=1);

namespace App\Domains\User\Models;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Access\Enums\SystemRole;
use App\Domains\Access\Models\Concerns\AuditsRoles;
use App\Domains\Access\Models\Concerns\TracksPermissionSources;
use App\Domains\Access\Models\Role;
use App\Domains\Api\Actions\RevokeAllCredentials;
use App\Domains\Api\Models\ApiRequestLog;
use App\Domains\Api\Models\OAuthConnection;
use App\Domains\Auth\Enums\AuthType;
use App\Domains\Auth\Models\Concerns\HandlesImpersonation;
use App\Domains\Auth\Models\LoginChallenge;
use App\Domains\Core\Models\Concerns\RecordsAuditEvents;
use App\Domains\Core\Models\DatabaseNotification;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\User\Data\UserPreferences;
use App\Domains\User\Enums\Affiliation;
use App\Domains\User\QueryBuilders\UserBuilder;
use App\Http\Middleware\EnvironmentLockdown;
use App\Providers\Filament\AdministrationPanelProvider;
use App\Providers\Filament\AppPanelProvider;
use Database\Factories\Domains\User\Models\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Northwestern\SysDev\Chassis\Models\Concerns\Auditable as AuditableConcern;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Someone who uses the application, or an API user its service clients act as.
 *
 * Northwestern people sign in with their NetID and the directory
 * keeps their details current. Local accounts sign in with a
 * code sent to their email, and API users never sign in.
 *
 * @property int $id
 * @property AuthType $auth_type
 * @property Affiliation|null $primary_affiliation
 * @property string $username The NetID, or a generated name for a local account or API user
 * @property string|null $employee_id The student ID when someone has both, otherwise the employee ID
 * @property string|null $hr_employee_id The myHR employee ID, only when it differs from the student ID
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $description
 * @property list<string> $job_titles
 * @property list<string> $departments
 * @property string $timezone
 * @property UserPreferences $preferences
 * @property string|null $wildcard_photo_s3_key
 * @property Carbon|null $wildcard_photo_last_synced_at
 * @property bool|null $netid_inactive
 * @property Carbon|null $last_directory_sync_at
 * @property Carbon|null $directory_sync_last_failed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $full_name
 * @property-read string $clerical_name
 * @property-read bool $is_local_user
 * @property-read bool $is_api_user
 * @property-read Collection<int, Role> $non_default_roles
 * @property Collection<int, Role> $roles
 *
 * @method BelongsToMany<Role, $this> roles()
 */
class User extends Authenticatable implements Auditable, FilamentUser, HasAvatar, HasName, OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use AuditableConcern, HandlesImpersonation, HasApiTokens, HasFactory, Notifiable, RecordsAuditEvents, SoftDeletes, TracksPermissionSources;

    use AuditsRoles, HasRoles {
        HasRoles::assignRole as private;
        HasRoles::removeRole as private;
        HasRoles::syncRoles as private;
    }

    /**
     * Roles and permissions belong to the web guard. Without this, Spatie
     * checks them against the api guard on API requests, where Passport
     * makes its own guard the default, and finds none of the roles.
     */
    protected string $guard_name = 'web';

    protected $casts = [
        'auth_type' => AuthType::class,
        'primary_affiliation' => Affiliation::class,
        'departments' => 'array',
        'job_titles' => 'array',
        'preferences' => UserPreferences::class,
        'wildcard_photo_last_synced_at' => 'datetime',
        'last_directory_sync_at' => 'datetime',
        'directory_sync_last_failed_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @var list<string> */
    protected array $auditExclude = [
        'last_directory_sync_at',
        'remember_token',
        'password',
    ];

    protected static function booted(): void
    {
        // Credentials change only under the name of whoever changes them, so
        // revoking them would be refused while someone is impersonating.
        static::deleting(static function (): void {
            if (resolve('impersonate')->isImpersonating()) {
                throw new AuthorizationException("Users can't be deleted while impersonating someone. Stop impersonating, then try again.");
            }
        });

        // A deleted account can't call the API: its tokens and clients go with it.
        static::deleted(static fn (User $user) => resolve(RevokeAllCredentials::class)($user));
    }

    /** @return HasMany<UserLoginRecord, $this> */
    public function login_records(): HasMany
    {
        return $this->hasMany(UserLoginRecord::class);
    }

    /** @return HasOne<UserLoginRecord, $this> */
    public function latest_login_record(): HasOne
    {
        return $this->hasOne(UserLoginRecord::class)->latestOfMany();
    }

    /** @return HasMany<LoginChallenge, $this> */
    public function login_challenges(): HasMany
    {
        return $this->hasMany(LoginChallenge::class, 'email', 'email');
    }

    /** @return HasMany<ApiRequestLog, $this> */
    public function api_request_logs(): HasMany
    {
        return $this->hasMany(ApiRequestLog::class);
    }

    /** @return HasMany<OAuthConnection, $this> */
    public function oauth_connections(): HasMany
    {
        return $this->hasMany(OAuthConnection::class);
    }

    /** @return HasMany<SupportTicket, $this> */
    public function support_tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * The starter's notification model, which prunes
     * notifications after their retention period.
     *
     * @return MorphMany<DatabaseNotification, $this>
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(DatabaseNotification::class, 'notifiable')->latest();
    }

    /**
     * @comment The user's full name in the format of "First Last".
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): string => $value
                ?? trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')),
        );
    }

    /**
     * @comment The user's full name in the format of "Last, First".
     *
     * @return Attribute<string, never>
     */
    protected function clericalName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => "{$this->last_name}, {$this->first_name}",
        );
    }

    /**
     * @comment Whether the user is a local user.
     *
     * @return Attribute<bool, never>
     */
    protected function isLocalUser(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->auth_type === AuthType::Local,
        );
    }

    /**
     * @comment Whether the user is an API user.
     *
     * @return Attribute<bool, never>
     */
    protected function isApiUser(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->auth_type === AuthType::API,
        );
    }

    /**
     * @comment The user's roles beyond Northwestern User, which every SSO user is given.
     *
     * Use this to decide whether someone has real access to the application.
     *
     * @see EnvironmentLockdown
     *
     * @return Attribute<Collection<int, Role>, never>
     */
    protected function nonDefaultRoles(): Attribute
    {
        return Attribute::make(
            get: fn (): Collection => $this->roles->reject(
                fn (Role $role): bool => $role->name === SystemRole::NorthwesternUser->value
            ),
        );
    }

    /** @return UserBuilder<static> */
    public static function query(): UserBuilder
    {
        /** @var UserBuilder<static> $builder */
        $builder = parent::query();

        return $builder;
    }

    /**
     * @param  Builder  $query
     * @return UserBuilder<self>
     */
    public function newEloquentBuilder($query): UserBuilder
    {
        return new UserBuilder($query);
    }

    /**
     * Delete the user and revoke their credentials together, so neither happens alone.
     *
     * @throws AuthorizationException While impersonating, or when a credential can't be revoked
     */
    public function delete(): ?bool
    {
        return DB::transaction(fn (): ?bool => parent::delete());
    }

    /**
     * Determine whether the account is deleted or its NetID deactivated.
     *
     * It reads the raw attributes, since a user built in memory
     * or selected with fewer columns may not have them, and
     * reading an attribute that wasn't retrieved throws.
     */
    public function isInactive(): bool
    {
        $attributes = $this->getAttributes();

        return ($attributes['deleted_at'] ?? null) !== null || (bool) ($attributes['netid_inactive'] ?? false);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // A deactivated or deleted person has no panel, so a session
        // from before the change is refused on its next request.
        if ($this->isInactive()) {
            return false;
        }

        /**
         * An unhandled panel throws on purpose, so a new panel left out
         * of this match fails loudly rather than letting anyone in.
         *
         * @phpstan-ignore match.unhandled
         */
        return match ($panel->getId()) {
            // API users authenticate with bearer tokens only and never have a panel session.
            AppPanelProvider::ID => ! $this->is_api_user,
            AdministrationPanelProvider::ID => $this->can(SystemPermission::AccessAdministrationPanel),
        };
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if (! config('platform.wildcard_photo_sync')) {
            return null;
        }

        return route('users.wildcard-photo', $this);
    }

    public function getFilamentName(): string
    {
        return $this->full_name;
    }

    /**
     * The user provider Passport issues tokens for. Passport infers it only for
     * the eloquent driver, and the users provider uses eager-load-eloquent.
     */
    public function getProviderName(): string
    {
        return 'users';
    }
}
