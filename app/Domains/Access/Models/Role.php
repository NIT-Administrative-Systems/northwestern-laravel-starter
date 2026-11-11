<?php

declare(strict_types=1);

namespace App\Domains\Access\Models;

use App\Domains\Access\Enums\RoleTypeEnum;
use App\Domains\Access\Models\Concerns\AuditsPermissions;
use App\Domains\Core\Models\Concerns\RecordsAuditEvents;
use App\Domains\User\Models\User;
use Database\Factories\Domains\Access\Models\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Northwestern\SysDev\Chassis\Models\Concerns\Auditable as AuditableConcern;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property bool $assignment_locked
 */
class Role extends SpatieRole implements Auditable
{
    /** @use HasFactory<RoleFactory> */
    use AuditableConcern, AuditsPermissions, HasFactory, RecordsAuditEvents, SoftDeletes;

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return parent::users();
    }

    /** @return BelongsTo<RoleType, $this> */
    public function role_type(): BelongsTo
    {
        return $this->belongsTo(RoleType::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function assignable(Builder $query): Builder
    {
        return $query->where('assignment_locked', false);
    }

    /**
     * Create a role, typed as this model rather than Spatie's.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function create(array $attributes = []): static
    {
        /** @var static $role */
        $role = parent::create($attributes);

        return $role;
    }

    /**
     * Determine whether the role is system-managed, which nobody may edit.
     */
    public function isSystemManagedType(): bool
    {
        return $this->role_type?->slug === RoleTypeEnum::SystemManaged;
    }

    /**
     * Determine whether this role suits `$user`: an API Integration
     * role only an API user, and every other role only a person.
     */
    public function suits(User $user): bool
    {
        return ($this->role_type?->slug === RoleTypeEnum::ApiIntegration) === $user->is_api_user;
    }

    /**
     * Determine whether only code may give or take the role, never the panel.
     */
    public function isAssignmentLocked(): bool
    {
        return $this->assignment_locked === true;
    }

    /**
     * Get every role's name by ID, once per request, for lists of each row's roles.
     *
     * @return array<int, string>
     */
    public static function namesById(): array
    {
        return once(fn (): array => self::query()->pluck('name', 'id')->all());
    }
}
