<?php

declare(strict_types=1);

namespace App\Domains\Auth\Models;

use App\Domains\Auth\Enums\RoleTypeEnum;
use App\Domains\User\Models\Concerns\AuditsPermissions;
use App\Domains\User\Models\User;
use Database\Factories\Domains\Auth\Models\RoleFactory;
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
    use AuditableConcern, AuditsPermissions, HasFactory, SoftDeletes;

    /**
     * Override the create method to return the custom Role model.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function create(array $attributes = []): static
    {
        /** @var static $role */
        $role = parent::create($attributes);

        return $role;
    }

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
     * Determine if this role is a System Managed role type.
     * System Managed roles cannot be edited through the UI.
     */
    public function isSystemManagedType(): bool
    {
        return $this->role_type?->slug === RoleTypeEnum::SystemManaged;
    }

    /**
     * Determine if this role has assignment locking enabled.
     */
    public function isAssignmentLocked(): bool
    {
        return $this->assignment_locked === true;
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
}
