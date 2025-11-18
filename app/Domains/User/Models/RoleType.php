<?php

declare(strict_types=1);

namespace App\Domains\User\Models;

use App\Domains\Core\Models\BaseModel;
use App\Domains\User\Enums\RoleTypeEnum;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Role Type category model.
 *
 * Role types provide a hierarchical organization of roles into logical categories.
 * For example, you might have:
 * - "Platform" type for system-wide administrative roles
 * - "Application" type for application-specific roles
 * - "Organization" type for organization-specific roles
 *
 * This allows grouping related roles together in the admin interface and
 * makes it easier to manage large numbers of roles.
 */
class RoleType extends BaseModel
{
    protected $casts = [
        'slug' => RoleTypeEnum::class,
    ];

    /** @return HasMany<Role, $this>  */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }
}
