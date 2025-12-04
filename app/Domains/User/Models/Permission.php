<?php

declare(strict_types=1);

namespace App\Domains\User\Models;

use App\Domains\Core\Models\Concerns\Auditable as AuditableConcern;
use App\Domains\User\Enums\PermissionScopeEnum;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property string $name
 * @property string $label
 * @property PermissionScopeEnum $scope
 * @property string $description
 * @property string $guard_name
 * @property bool $system_managed
 * @property bool $api_relevant
 */
class Permission extends SpatiePermission implements Auditable
{
    use AuditableConcern;

    protected $casts = [
        'scope' => PermissionScopeEnum::class,
    ];

    /**
     * Override the create method to return the custom Permission model.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function create(array $attributes = []): static
    {
        /** @var static $permission */
        $permission = parent::create($attributes);

        return $permission;
    }
}
