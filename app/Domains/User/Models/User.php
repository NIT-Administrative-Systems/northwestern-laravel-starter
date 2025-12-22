<?php

declare(strict_types=1);

namespace App\Domains\User\Models;

use App\Domains\Core\Models\Concerns\Auditable as AuditableConcern;
use App\Domains\User\Enums\AffiliationEnum;
use App\Domains\User\Enums\AuthTypeEnum;
use App\Domains\User\Enums\PermissionEnum;
use App\Domains\User\Enums\SystemRoleEnum;
use App\Domains\User\Models\Concerns\AuditsRoles;
use App\Domains\User\Models\Concerns\HandlesImpersonation;
use App\Domains\User\QueryBuilders\UserBuilder;
use App\Http\Middleware\EnvironmentLockdown;
use App\Providers\Filament\AdministrationPanelProvider;
use Database\Factories\Domains\User\Models\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string|null $hr_employee_id The myHR employee ID for the user. This will be populated if the user has a different
 *                                       employee ID and a student ID.
 * @property string|null $employee_id The student/employee ID for the user. In cases where the user has a difference between
 *                                    their student ID and employee ID, the student ID will be stored here.
 * @property list<string> $departments
 * @property list<string> $job_titles
 *
 * @method BelongsToMany<Role, $this> roles()
 *
 * @property Collection<int, Role> $roles
 */
class User extends Authenticatable implements Auditable, FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use AuditableConcern, AuditsRoles, HandlesImpersonation, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /** @var list<string> */
    protected array $auditExclude = [
        'last_directory_sync_at',
        'remember_token',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'full_name',
    ];

    protected $casts = [
        'auth_type' => AuthTypeEnum::class,
        'primary_affiliation' => AffiliationEnum::class,
        'departments' => 'array',
        'job_titles' => 'array',
        'wildcard_photo_last_synced_at' => 'datetime',
        'last_directory_sync_at' => 'datetime',
        'directory_sync_last_failed_at' => 'datetime',
    ];

    public function newEloquentBuilder($query): UserBuilder
    {
        return new UserBuilder($query);
    }

    public static function query(): UserBuilder
    {
        /** @var UserBuilder<static> $builder */
        $builder = parent::query();

        return $builder;
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

    /**
     * @return HasMany<AccessToken, $this>
     */
    public function access_tokens(): HasMany
    {
        return $this->hasMany(AccessToken::class);
    }

    /** @return HasMany<AccessToken, $this> */
    public function active_access_tokens(): HasMany
    {
        return $this->access_tokens()->active();
    }

    /**
     * @return HasMany<ApiRequestLog, $this>
     */
    public function api_request_logs(): HasMany
    {
        return $this->hasMany(ApiRequestLog::class);
    }

    /**
     * @comment The user's full name in the format of "First Last".
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => "{$this->first_name} {$this->last_name}",
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
            get: fn (): bool => $this->auth_type === AuthTypeEnum::LOCAL,
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
            get: fn (): bool => $this->auth_type === AuthTypeEnum::API,
        );
    }

    /**
     * @comment User's roles beyond the default "Northwestern User" system role.
     *
     * This excludes the "Northwestern User" role which is automatically assigned
     * to all SSO users. Use this when checking for real application access.
     *
     * @see EnvironmentLockdown
     *
     * @return Attribute<Collection<int, Role>, never>
     */
    protected function nonDefaultRoles(): Attribute
    {
        return Attribute::make(
            get: fn (): Collection => $this->roles->reject(
                fn ($role) => $role->name === SystemRoleEnum::NORTHWESTERN_USER->value
            ),
        );
    }

    public function getFilamentName(): string
    {
        return $this->full_name;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        /**
         * The exception from the unhandled case is desirable: if someone adds a new panel but doesn't put it
         * here, they'll get the "unknown case" exception.
         *
         * @phpstan-ignore match.unhandled
         */
        return match ($panel->getId()) {
            AdministrationPanelProvider::ID => $this->can(PermissionEnum::ACCESS_ADMINISTRATION_PANEL),
        };
    }
}
