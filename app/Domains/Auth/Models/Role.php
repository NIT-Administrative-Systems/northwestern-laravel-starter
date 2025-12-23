<?php

declare(strict_types=1);

namespace App\Domains\Auth\Models;

use App\Domains\Auth\Enums\PermissionEnum;
use App\Domains\Auth\Enums\RoleTypeEnum;
use App\Domains\Core\Models\Concerns\Auditable as AuditableConcern;
use App\Domains\User\Models\Concerns\AuditsPermissions;
use App\Domains\User\Models\User;
use Database\Factories\Domains\Auth\Models\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Models\Role as SpatieRole;

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
     * System Managed roles not be editable through the UI.
     */
    public function isSystemManagedType(): bool
    {
        return $this->role_type?->slug === RoleTypeEnum::SYSTEM_MANAGED;
    }

    /**
     * Determine if the given user can manage this role (assign/remove it from users).
     *
     * System Administrator roles require the MANAGE_ALL permission.
     * All other roles require the ASSIGN_ROLES permission.
     */
    public function canBeManaged(?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($this->isSystemManagedType()) {
            return $user->hasPermissionTo(PermissionEnum::MANAGE_ALL);
        }

        return $user->hasPermissionTo(PermissionEnum::ASSIGN_ROLES);
    }
}
