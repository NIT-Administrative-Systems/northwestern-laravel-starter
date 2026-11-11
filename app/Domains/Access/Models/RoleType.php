<?php

declare(strict_types=1);

namespace App\Domains\Access\Models;

use App\Domains\Access\Enums\RoleTypeEnum;
use App\Domains\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of role, one of {@see RoleTypeEnum}.
 * The type decides which permissions a role
 * may hold and whether it can be edited.
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
