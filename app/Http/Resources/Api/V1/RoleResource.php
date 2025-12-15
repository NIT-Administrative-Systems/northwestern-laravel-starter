<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domains\User\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'role_type' => $this->when(
                $this->relationLoaded('role_type'),
                fn () => [
                    'slug' => $this->role_type->slug->value,
                    'label' => $this->role_type->label,
                ]
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'users_count' => $this->when(
                isset($this->users_count),
                fn () => $this->users_count
            ),
        ];
    }
}
