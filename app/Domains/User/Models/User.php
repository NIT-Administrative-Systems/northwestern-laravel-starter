<?php

declare(strict_types=1);

namespace App\Domains\User\Models;

use App\Domains\Core\Models\Concerns\Auditable as AuditableConcern;
use App\Domains\User\Enums\AffiliationEnum;
use App\Domains\User\Enums\AuthTypeEnum;
use App\Domains\User\Enums\PermissionEnum;
use App\Domains\User\Models\Concerns\HandlesImpersonation;
use App\Providers\Filament\AdministrationPanelProvider;
use Database\Factories\Domains\User\Models\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Events\AuditCustom;
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
    use AuditableConcern, HandlesImpersonation, HasFactory, HasRoles, Notifiable, SoftDeletes;

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

    /** @return HasMany<LoginLink, $this> */
    public function login_links(): HasMany
    {
        return $this->hasMany(LoginLink::class);
    }

    /**
     * @return HasMany<ApiToken, $this>
     */
    public function api_tokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    /** @return HasMany<ApiToken, $this> */
    public function active_api_tokens(): HasMany
    {
        return $this->api_tokens()->active();
    }

    /**
     * @return HasMany<ApiRequestLog, $this>
     */
    public function api_request_logs(): HasMany
    {
        return $this->hasMany(ApiRequestLog::class);
    }

    /**
     * Assigns a role to the user and creates a detailed audit log entry.
     *
     * This method captures the complete state of the user's roles both before and after
     * the assignment, creating a comprehensive audit trail. The audit log includes:
     * - All roles the user had before the change
     * - The specific role that was assigned
     * - All roles the user has after the change
     *
     * @param  Role  $role  The role to assign to the user
     *
     * @see auditRoleChange() for the audit event structure
     * @see removeRoleWithAudit() for the inverse operation
     */
    public function assignRoleWithAudit(Role $role): void
    {
        $this->loadMissing('roles.role_type');
        $oldRoles = $this->mapRolesToArray($this->roles);
        $this->assignRole($role);
        $this->auditRoleChange('role_assigned', $oldRoles, $role);
    }

    /**
     * Removes a role from the user and creates a detailed audit log entry.
     *
     * This method captures the complete state of the user's roles both before and after
     * the removal, creating a comprehensive audit trail. The audit log includes:
     * - All roles the user had before the change
     * - The specific role that was removed
     * - All roles the user has after the change
     *
     * @param  Role  $role  The role to remove from the user
     *
     * @see auditRoleChange() for the audit event structure
     * @see assignRoleWithAudit() for the inverse operation
     */
    public function removeRoleWithAudit(Role $role): void
    {
        $this->loadMissing('roles.role_type');
        $oldRoles = $this->mapRolesToArray($this->roles);
        $this->removeRole($role);
        $this->auditRoleChange('role_removed', $oldRoles, $role);
    }

    /**
     * Converts a collection of roles to a simplified array format.
     *
     * @param  BaseCollection<int, Role>  $roles
     * @return array<int, array{id: int, name: string, role_type: string}> Array of simplified role data
     */
    private function mapRolesToArray(BaseCollection $roles): array
    {
        return $roles->map(fn (Role $role): array => [
            'id' => (int) $role->id,
            'name' => $role->name,
            'role_type' => $role->role_type->slug->getLabel(),
        ])->toArray();
    }

    /**
     * Creates a custom audit log entry for role changes with before/after snapshots.
     *
     * This method constructs a specialized audit event that captures the complete context
     * of a role assignment or removal. Unlike standard model audits that only track
     * attribute changes, this creates a structured snapshot of the entire role collection.
     *
     * @param  'role_assigned'|'role_removed'  $event  The specific audit event type
     * @param  array<int, array{id: int, name: string, role_type: string}>  $oldRoles  The collection of roles before modification
     * @param  Role  $role  The role that was assigned or removed
     *
     * @see assignRoleWithAudit()
     * @see removeRoleWithAudit()
     */
    private function auditRoleChange(string $event, array $oldRoles, Role $role): void
    {
        // Get latest roles after the modification
        $newRoles = $this->mapRolesToArray(
            $this->fresh(['roles.role_type'])->roles
        );

        $isAssignment = $event === 'role_assigned';

        $auditData = [
            'auditEvent' => $event,
            'isCustomEvent' => true,
            'auditCustomOld' => [
                'roles_before_change' => $oldRoles,
            ],
            'auditCustomNew' => [
                $isAssignment ? 'assigned_role' :
                    'removed_role' => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'role_type' => $role->role_type->slug->getLabel(),
                    ],
                'roles_after_change' => $newRoles,
            ],
        ];

        foreach ($auditData as $key => $value) {
            $this->{$key} = $value;
        }

        Event::dispatch(new AuditCustom($this));
    }

    /**
     * @comment Case-insensitive search scope for any combination of a user's name.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function searchByName(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $query) use ($term) {
            $query->where('first_name', 'ilike', "%{$term}%")
                ->orWhere('last_name', 'ilike', "%{$term}%")
                ->orWhereRaw("CONCAT_WS(' ', first_name, last_name) ilike ?", ["%{$term}%"])
                ->orWhereRaw("CONCAT_WS(', ', last_name, first_name) ilike ?", ["%{$term}%"]);
        });
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
            AdministrationPanelProvider::ID => $this->can(PermissionEnum::ACCESS_ADMIN_PANEL),
        };
    }
}
